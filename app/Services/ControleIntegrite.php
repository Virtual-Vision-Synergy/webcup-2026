<?php

namespace App\Services;

use App\Models\AnomalieDonnee;
use App\Models\Demarche;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * F85 : contrôle d'intégrité des données métier.
 *
 * Repère les incohérences (statut impossible, référence orpheline, date future, doublon), les enregistre dans
 * anomalies_donnees (une ligne par anomalie, grâce à la signature) et propose une correction sûre pour chacune.
 * Lecture par requêtes simples et limitées (serveur mutualisé) ; aucune donnée n'est supprimée.
 *
 * Signature : « type:table:id:complément » (complément = champ visé, ou id de l'original pour un doublon).
 */
class ControleIntegrite
{
    /** Nombre maximal d'anomalies relevées par vérification (évite un passage trop lourd). */
    private const LIMITE = 200;

    /** Tolérance avant de considérer une date comme « dans le futur » (décalage d'horloge). */
    private const TOLERANCE_FUTUR_MINUTES = 60;

    /** Tables contrôlées → modèle utilisé pour corriger (corrections journalisées par F47). */
    public const TABLES = [
        'signalements' => Signalement::class,
        'demarches' => Demarche::class,
    ];

    /**
     * Lance toutes les vérifications, enregistre les nouvelles anomalies et clôt celles qui ont disparu.
     *
     * @return array{detectees: int, nouvelles: int, disparues: int}
     */
    public function executer(): array
    {
        $trouvees = collect($this->analyser())->keyBy('signature');
        $nouvelles = 0;

        foreach ($trouvees as $signature => $anomalie) {
            $ligne = AnomalieDonnee::firstWhere('signature', $signature) ?? new AnomalieDonnee;
            $ligne->signature = (string) $signature;

            // Un faux positif ignoré par un admin le reste.
            if ($ligne->exists && $ligne->resolution === AnomalieDonnee::RESOLUTION_IGNOREE) {
                continue;
            }

            if (! $ligne->exists || ! $ligne->estOuverte()) {
                $nouvelles++;
                $ligne->detectee_le = now();
                $ligne->resolue_le = null;
                $ligne->resolue_par = null;
                $ligne->resolution = null;
            }

            $ligne->type = $anomalie['type'];
            $ligne->table_concernee = $anomalie['table'];
            $ligne->enregistrement_id = $anomalie['id'];
            $ligne->description = $anomalie['description'];
            $ligne->save();
        }

        $disparues = 0;
        AnomalieDonnee::query()->ouvertes()->whereNotIn('signature', $trouvees->keys()->all())->get()
            ->each(function (AnomalieDonnee $ligne) use (&$disparues): void {
                $ligne->marquerResolue(AnomalieDonnee::RESOLUTION_DISPARUE);
                $disparues++;
            });

        return ['detectees' => $trouvees->count(), 'nouvelles' => $nouvelles, 'disparues' => $disparues];
    }

    /**
     * @return list<array{signature: string, type: string, table: string, id: int, description: string}>
     */
    public function analyser(): array
    {
        return [
            ...$this->statutsImpossibles('signalements', Signalement::STATUT_OPTIONS, 'Signalement'),
            ...$this->statutsImpossibles('demarches', Demarche::STATUT_OPTIONS, 'Démarche'),
            ...$this->referencesOrphelines('signalements', 'doublon_de_id', 'signalements', 'Signalement', 'signalement d’origine'),
            ...$this->referencesOrphelines('demarches', 'service_id', 'services', 'Démarche', 'service'),
            ...$this->referencesOrphelines('demarches', 'pris_en_charge_par', 'users', 'Démarche', 'agent responsable'),
            ...$this->datesFutures('signalements', 'Signalement'),
            ...$this->datesFutures('demarches', 'Démarche'),
            ...$this->doublonsSignalements(),
        ];
    }

    /**
     * Applique la correction proposée, puis marque l'anomalie comme corrigée.
     * Renvoie faux si l'enregistrement n'existe plus (anomalie alors close comme « disparue »).
     */
    public function corriger(AnomalieDonnee $anomalie, ?User $par = null): bool
    {
        $classe = self::TABLES[$anomalie->table_concernee] ?? null;
        /** @var Model|null $modele */
        $modele = $classe === null ? null : $classe::find($anomalie->enregistrement_id);

        if ($modele === null) {
            $anomalie->marquerResolue(AnomalieDonnee::RESOLUTION_DISPARUE, $par);

            return false;
        }

        $complement = (string) last(explode(':', $anomalie->signature));

        DB::transaction(function () use ($anomalie, $modele, $complement): void {
            match ($anomalie->type) {
                // Statut inconnu → statut initial (« nouveau » / « déposée ») pour qu'un agent le retraite.
                AnomalieDonnee::TYPE_STATUT_IMPOSSIBLE => $modele->forceFill(['statut' => constant($modele::class.'::STATUT_OPTIONS')[0]])->save(),
                // Référence vers un élément disparu → vidée (toutes ces colonnes sont facultatives).
                AnomalieDonnee::TYPE_REFERENCE_ORPHELINE => $modele->forceFill([$complement => null])->save(),
                // Date de création dans le futur → ramenée à maintenant (journalisée à la main : created_at est ignoré par F47).
                AnomalieDonnee::TYPE_DATE_FUTURE => $this->corrigerDate($modele),
                // Doublon → rattaché à l'original (même mécanisme que le regroupement F57), rien n'est supprimé.
                AnomalieDonnee::TYPE_DOUBLON => $modele->forceFill(['doublon_de_id' => (int) $complement])->save(),
                default => null,
            };
        });

        $anomalie->marquerResolue(AnomalieDonnee::RESOLUTION_CORRIGEE, $par);

        return true;
    }

    /**
     * Libellé de la correction proposée (affiché à l'admin avant confirmation).
     */
    public static function correctionProposee(AnomalieDonnee $anomalie): string
    {
        $complement = (string) last(explode(':', $anomalie->signature));

        return match ($anomalie->type) {
            AnomalieDonnee::TYPE_STATUT_IMPOSSIBLE => 'Remettre le statut initial pour qu’un agent le retraite.',
            AnomalieDonnee::TYPE_REFERENCE_ORPHELINE => 'Vider la référence « '.$complement.' » vers l’élément disparu.',
            AnomalieDonnee::TYPE_DATE_FUTURE => 'Ramener la date de création à maintenant.',
            AnomalieDonnee::TYPE_DOUBLON => 'Rattacher au signalement n° '.$complement.' (aucune suppression).',
            default => 'Aucune correction automatique.',
        };
    }

    private function corrigerDate(Model $modele): void
    {
        $avant = $modele->getAttribute('created_at');

        $modele->timestamps = false;
        $modele->forceFill(['created_at' => now()])->save();
        $modele->timestamps = true;

        AuditLogger::log('updated', $modele, ['created_at' => ['avant' => $avant, 'apres' => now()]]);
    }

    /**
     * @param  list<string>  $options
     * @return list<array{signature: string, type: string, table: string, id: int, description: string}>
     */
    private function statutsImpossibles(string $table, array $options, string $libelle): array
    {
        return DB::table($table)
            ->where(fn ($q) => $q->whereNotIn('statut', $options)->orWhereNull('statut'))
            ->limit(self::LIMITE)
            ->get(['id', 'statut'])
            ->map(fn (object $ligne): array => [
                'signature' => AnomalieDonnee::TYPE_STATUT_IMPOSSIBLE.':'.$table.':'.$ligne->id.':statut',
                'type' => AnomalieDonnee::TYPE_STATUT_IMPOSSIBLE,
                'table' => $table,
                'id' => (int) $ligne->id,
                'description' => $libelle.' n° '.$ligne->id.' : statut « '.($ligne->statut ?? 'vide').' » inconnu.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{signature: string, type: string, table: string, id: int, description: string}>
     */
    private function referencesOrphelines(string $table, string $colonne, string $tableCible, string $libelle, string $cible): array
    {
        return DB::table($table)
            ->whereNotNull($colonne)
            ->whereNotIn($colonne, DB::table($tableCible)->select('id'))
            ->limit(self::LIMITE)
            ->get(['id', $colonne])
            ->map(fn (object $ligne): array => [
                'signature' => AnomalieDonnee::TYPE_REFERENCE_ORPHELINE.':'.$table.':'.$ligne->id.':'.$colonne,
                'type' => AnomalieDonnee::TYPE_REFERENCE_ORPHELINE,
                'table' => $table,
                'id' => (int) $ligne->id,
                'description' => $libelle.' n° '.$ligne->id.' : '.$cible.' n° '.$ligne->{$colonne}.' introuvable.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{signature: string, type: string, table: string, id: int, description: string}>
     */
    private function datesFutures(string $table, string $libelle): array
    {
        return DB::table($table)
            ->where('created_at', '>', now()->addMinutes(self::TOLERANCE_FUTUR_MINUTES))
            ->limit(self::LIMITE)
            ->get(['id', 'created_at'])
            ->map(fn (object $ligne): array => [
                'signature' => AnomalieDonnee::TYPE_DATE_FUTURE.':'.$table.':'.$ligne->id.':created_at',
                'type' => AnomalieDonnee::TYPE_DATE_FUTURE,
                'table' => $table,
                'id' => (int) $ligne->id,
                'description' => $libelle.' n° '.$ligne->id.' : créé(e) le '.$ligne->created_at.', date dans le futur.',
            ])
            ->values()
            ->all();
    }

    /**
     * Signalements identiques (même auteur, même catégorie, même lieu, même description) non encore rattachés.
     *
     * @return list<array{signature: string, type: string, table: string, id: int, description: string}>
     */
    private function doublonsSignalements(): array
    {
        $groupes = DB::table('signalements')
            ->whereNull('doublon_de_id')
            ->select('user_id', 'categorie', 'lieu', 'description', DB::raw('MIN(id) as original_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id', 'categorie', 'lieu', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->limit(self::LIMITE)
            ->get();

        $anomalies = [];

        foreach ($groupes as $groupe) {
            $copies = DB::table('signalements')
                ->whereNull('doublon_de_id')
                ->where('user_id', $groupe->user_id)
                ->where('categorie', $groupe->categorie)
                ->where('lieu', $groupe->lieu)
                ->where('description', $groupe->description)
                ->where('id', '!=', $groupe->original_id)
                ->limit(self::LIMITE)
                ->pluck('id');

            foreach ($copies as $id) {
                $anomalies[] = [
                    'signature' => AnomalieDonnee::TYPE_DOUBLON.':signalements:'.$id.':'.$groupe->original_id,
                    'type' => AnomalieDonnee::TYPE_DOUBLON,
                    'table' => 'signalements',
                    'id' => (int) $id,
                    'description' => 'Signalement n° '.$id.' : copie identique du signalement n° '.$groupe->original_id.' (même auteur, lieu et description).',
                ];
            }
        }

        return $anomalies;
    }
}
