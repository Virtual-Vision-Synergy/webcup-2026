<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\VerificationSauvegarde;
use App\Notifications\AlerteSauvegarde;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * F87 : sauvegardes de la base (lecture seule de ~/backups, création, vérification, alertes).
 *
 * - Production (MariaDB) : les fichiers sont créés par scripts/sauvegarde-base.sh (cron 30 min ou bouton admin).
 * - Local (SQLite) : un export SQL équivalent est écrit en PHP, pour que la page fonctionne aussi en local.
 *
 * Aucune restauration n'est possible depuis l'application, aucun fichier n'est supprimé,
 * et aucun identifiant de base n'est lu, affiché ni journalisé ici (le script les lit lui-même dans .env).
 */
class Sauvegardes
{
    /** Au-delà, la dernière sauvegarde est considérée comme trop ancienne (rouge). */
    public const ALERTE_MINUTES = 60;

    /** Au-delà, un passage du cron (toutes les 30 min) a probablement été manqué (orange). */
    public const ATTENTION_MINUTES = 35;

    /**
     * Tables importantes détaillées dans le rapport, avec leur libellé au pluriel.
     *
     * @var array<string, string>
     */
    public const TABLES_IMPORTANTES = [
        'users' => 'comptes',
        'demarches' => 'demandes',
        'reponses_demarche' => 'réponses aux demandes',
        'signalements' => 'signalements',
        'rendez_vous' => 'rendez-vous',
        'remontees' => 'remontées',
        'annonces' => 'annonces',
        'services' => 'services',
    ];

    private const MARQUEUR_FIN = '-- Dump completed';

    public function dossier(): string
    {
        $configure = config('services.sauvegardes.dossier');

        if (is_string($configure) && $configure !== '') {
            return rtrim($configure, '/\\');
        }

        if ($this->estMysql()) {
            $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);

            if (is_string($home) && $home !== '') {
                return rtrim($home, '/\\').'/backups';
            }
        }

        return storage_path('app/private/sauvegardes');
    }

    /**
     * Dernière sauvegarde présente dans le dossier (la plus récente), ou null s'il n'y en a aucune.
     *
     * @return array{nom: string, chemin: string, taille: int, date: Carbon}|null
     */
    public function derniere(): ?array
    {
        $fichiers = glob($this->dossier().'/*.sql.gz') ?: [];

        if ($fichiers === []) {
            return null;
        }

        usort($fichiers, fn (string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));
        $chemin = $fichiers[0];

        return [
            'nom' => basename($chemin),
            'chemin' => $chemin,
            'taille' => (int) filesize($chemin),
            'date' => Carbon::createFromTimestamp((int) filemtime($chemin))->timezone(config('app.timezone')),
        ];
    }

    /**
     * État de la dernière sauvegarde : ok (vert), attention (orange) ou critique (rouge).
     *
     * @return array{niveau: string, libelle: string, couleur: string}
     */
    public function etat(): array
    {
        $derniere = $this->derniere();
        $minutes = $derniere === null ? null : (int) $derniere['date']->diffInMinutes(now(), true);

        return match (true) {
            $minutes === null => ['niveau' => 'critique', 'libelle' => 'Aucune sauvegarde trouvée', 'couleur' => 'danger'],
            $minutes > self::ALERTE_MINUTES => ['niveau' => 'critique', 'libelle' => 'Dernière sauvegarde trop ancienne', 'couleur' => 'danger'],
            $minutes > self::ATTENTION_MINUTES => ['niveau' => 'attention', 'libelle' => 'Sauvegarde en retard', 'couleur' => 'warning'],
            default => ['niveau' => 'ok', 'libelle' => 'Sauvegardes à jour', 'couleur' => 'success'],
        };
    }

    /**
     * Crée une sauvegarde maintenant et retourne le chemin du fichier créé.
     */
    public function lancer(): string
    {
        $dossier = $this->dossier();

        if (! is_dir($dossier) && ! mkdir($dossier, 0700, true) && ! is_dir($dossier)) {
            throw new RuntimeException('Dossier de sauvegarde inaccessible.');
        }

        if ($this->estMysql()) {
            $resultat = Process::timeout(300)
                ->env(['APP_DIR' => base_path(), 'BACKUP_DIR' => $dossier])
                ->run(['bash', base_path('scripts/sauvegarde-base.sh'), 'interface']);

            // Sortie du script volontairement non reprise : seul le code de retour est exposé.
            if ($resultat->failed()) {
                throw new RuntimeException('Le script de sauvegarde a échoué (code '.$resultat->exitCode().').');
            }
        } else {
            $this->exporterEnPhp($dossier.'/sqlite-'.now()->format('Ymd-His').'-interface.sql.gz');
        }

        $derniere = $this->derniere();

        if ($derniere === null) {
            throw new RuntimeException('Aucun fichier de sauvegarde produit.');
        }

        return $derniere['chemin'];
    }

    /**
     * Vérifie la dernière sauvegarde : fichier lisible et non vide, export terminé,
     * tables présentes et lignes des tables importantes comparées à la base. Alerte les admins si elle échoue.
     */
    public function verifierDerniere(?User $auteur = null): VerificationSauvegarde
    {
        $derniere = $this->derniere();

        if ($derniere === null) {
            return $this->enregistrer($auteur, [
                'fichier' => '—',
                'taille' => 0,
                'sauvegarde_le' => null,
                'statut' => VerificationSauvegarde::ECHEC,
                'rapport' => 'Aucune sauvegarde trouvée : rien à vérifier ❌',
                'details' => null,
            ]);
        }

        return $this->verifier($derniere, $auteur);
    }

    /**
     * Le fichier le plus récent a-t-il déjà été vérifié ? (évite de relire la même sauvegarde à chaque passage)
     */
    public function derniereDejaVerifiee(): bool
    {
        $derniere = $this->derniere();

        return $derniere !== null && VerificationSauvegarde::query()->where('fichier', $derniere['nom'])->exists();
    }

    /**
     * Prévient les admins actifs (e-mail + cloche).
     */
    public function alerterAdmins(string $sujet, string $message): void
    {
        $admins = User::query()
            ->where('role_id', Role::idFor(Role::ADMIN))
            ->whereNull('deactivated_at')
            ->get();

        Notification::send($admins, new AlerteSauvegarde($sujet, $message));
    }

    /**
     * Rapport d'une sauvegarde qui n'a pas pu être créée (historique + alerte).
     */
    public function enregistrerEchecCreation(?User $auteur): VerificationSauvegarde
    {
        return $this->enregistrer($auteur, [
            'fichier' => '—',
            'taille' => 0,
            'sauvegarde_le' => now(),
            'statut' => VerificationSauvegarde::ECHEC,
            'rapport' => 'Sauvegarde du '.now()->format('d/m/Y à H:i').' : la création a échoué ❌',
            'details' => null,
        ]);
    }

    /**
     * @param  array{nom: string, chemin: string, taille: int, date: Carbon}  $fichier
     */
    private function verifier(array $fichier, ?User $auteur): VerificationSauvegarde
    {
        $quand = 'Sauvegarde du '.$fichier['date']->format('d/m/Y à H:i');
        $base = [
            'fichier' => $fichier['nom'],
            'taille' => $fichier['taille'],
            'sauvegarde_le' => $fichier['date'],
        ];

        $lecture = $fichier['taille'] > 0 && is_readable($fichier['chemin'])
            ? $this->lire($fichier['chemin'])
            : null;

        if ($lecture === null) {
            return $this->enregistrer($auteur, $base + [
                'statut' => VerificationSauvegarde::ECHEC,
                'rapport' => $quand.' : fichier illisible ou vide ❌',
                'details' => null,
            ]);
        }

        $tablesBase = $this->tablesDeLaBase();
        $manquantes = array_values(array_diff($tablesBase, array_keys($lecture['tables'])));

        $importantes = [];
        $videsAnormales = [];

        foreach (self::TABLES_IMPORTANTES as $table => $libelle) {
            if (! in_array($table, $tablesBase, true)) {
                continue;
            }

            $lignesBase = DB::table($table)->count();
            $lignesSauvegarde = $lecture['tables'][$table] ?? 0;
            $importantes[$table] = ['libelle' => $libelle, 'sauvegarde' => $lignesSauvegarde, 'base' => $lignesBase];

            if ($lignesSauvegarde === 0 && $lignesBase > 0) {
                $videsAnormales[] = $libelle;
            }
        }

        $complete = $lecture['termine'] && $manquantes === [] && $videsAnormales === [];

        $resume = collect($importantes)
            ->filter(fn (array $ligne): bool => $ligne['sauvegarde'] > 0)
            ->take(3)
            ->map(fn (array $ligne): string => self::nombre($ligne['sauvegarde']).' '.$ligne['libelle'])
            ->prepend(self::nombre(count($lecture['tables'])).' tables')
            ->implode(', ');

        $problemes = array_filter([
            $lecture['termine'] ? null : 'export interrompu',
            $manquantes === [] ? null : count($manquantes).' table(s) absente(s)',
            $videsAnormales === [] ? null : 'vide : '.implode(', ', $videsAnormales),
        ]);

        return $this->enregistrer($auteur, $base + [
            'statut' => $complete ? VerificationSauvegarde::COMPLETE : VerificationSauvegarde::INCOMPLETE,
            'nb_tables' => count($lecture['tables']),
            'nb_tables_base' => count($tablesBase),
            'rapport' => $quand.' : '.$resume.' — '.($complete ? 'complète ✅' : 'incomplète ⚠️ ('.implode(' ; ', $problemes).')'),
            'details' => [
                'termine' => $lecture['termine'],
                'tables_manquantes' => $manquantes,
                'importantes' => $importantes,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function enregistrer(?User $auteur, array $attributs): VerificationSauvegarde
    {
        $verification = new VerificationSauvegarde($attributs);
        $verification->user()->associate($auteur);
        $verification->save();

        if (! $verification->estComplete()) {
            $this->alerterAdmins('Vérification de sauvegarde en échec', $verification->rapport);
        }

        return $verification;
    }

    /**
     * Lit un export .sql.gz : tables créées, lignes insérées par table, présence du marqueur de fin.
     *
     * @return array{tables: array<string, int>, termine: bool}|null
     */
    private function lire(string $chemin): ?array
    {
        $flux = @gzopen($chemin, 'rb');

        if ($flux === false) {
            return null;
        }

        $tables = [];
        $termine = false;

        while (($ligne = gzgets($flux)) !== false) {
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?[`"]?([^`"\s(]+)/i', $ligne, $m)) {
                $tables[$m[1]] ??= 0;
            } elseif (preg_match('/^INSERT INTO [`"]?([^`"\s(]+)[`"]?[^(]*VALUES\s*/i', $ligne, $m, PREG_OFFSET_CAPTURE)) {
                $table = $m[1][0];
                $tables[$table] = ($tables[$table] ?? 0) + self::compterTuples(substr($ligne, strlen($m[0][0])));
            } elseif (str_starts_with($ligne, self::MARQUEUR_FIN)) {
                $termine = true;
            }
        }

        $erreur = ! gzeof($flux);
        gzclose($flux);

        if ($erreur && $tables === []) {
            return null;
        }

        return ['tables' => $tables, 'termine' => $termine && ! $erreur];
    }

    /**
     * Compte les n-uplets « (…),(…) » d'un INSERT, en ignorant les parenthèses à l'intérieur des chaînes.
     */
    private static function compterTuples(string $valeurs): int
    {
        $nombre = 0;
        $profondeur = 0;
        $dansChaine = false;
        $longueur = strlen($valeurs);

        for ($i = 0; $i < $longueur; $i++) {
            $c = $valeurs[$i];

            if ($dansChaine) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === "'") {
                    $dansChaine = false;
                }

                continue;
            }

            if ($c === "'") {
                $dansChaine = true;
            } elseif ($c === '(') {
                if ($profondeur === 0) {
                    $nombre++;
                }
                $profondeur++;
            } elseif ($c === ')') {
                $profondeur--;
            }
        }

        return $nombre;
    }

    /**
     * Export SQL compressé de la base SQLite (développement local), au même format que mysqldump.
     */
    private function exporterEnPhp(string $chemin): void
    {
        $flux = gzopen($chemin, 'wb6');

        if ($flux === false) {
            throw new RuntimeException('Impossible d’écrire le fichier de sauvegarde.');
        }

        foreach ($this->tablesDeLaBase() as $table) {
            $sql = DB::scalar('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);
            gzwrite($flux, preg_replace('/\s+/', ' ', (string) $sql).";\n");

            foreach (DB::table($table)->cursor() as $ligne) {
                $valeurs = array_map(fn (mixed $v): string => match (true) {
                    $v === null => 'NULL',
                    is_int($v), is_float($v) => (string) $v,
                    default => "'".str_replace(['\\', "'", "\n", "\r", "\0"], ['\\\\', "\\'", '\\n', '\\r', '\\0'], (string) $v)."'",
                }, (array) $ligne);

                gzwrite($flux, 'INSERT INTO `'.$table.'` VALUES ('.implode(',', $valeurs).");\n");
            }
        }

        gzwrite($flux, self::MARQUEUR_FIN.' on '.now()->format('Y-m-d H:i:s')."\n");
        gzclose($flux);
        chmod($chemin, 0600);
    }

    /**
     * @return array<int, string>
     */
    private function tablesDeLaBase(): array
    {
        $schema = $this->estMysql() ? DB::connection()->getDatabaseName() : null;

        return collect(Schema::getTables($schema))
            ->pluck('name')
            ->reject(fn (string $nom): bool => str_starts_with($nom, 'sqlite_'))
            ->values()
            ->all();
    }

    private function estMysql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public static function nombre(int $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
