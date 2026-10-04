<?php

namespace App\Console\Commands;

use App\Models\Annonce;
use App\Models\AnomalieDonnee;
use App\Models\Demarche;
use App\Models\SecurityEvent;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nettoie les saisies de test (demandes, signalements, annonces) et ajoute des données de démo réalistes
 * rattachées aux comptes jury existants. Idempotente : relancée, elle ne recrée rien en double.
 *
 * Sans --force, rien n'est modifié (simulation) : la liste des suppressions et des créations est seulement affichée.
 * Aucune autre donnée n'est supprimée.
 */
class DonneesDemoJury extends Command
{
    protected $signature = 'app:donnees-demo-jury
        {--citoyen=user@example.com : E-mail du compte jury citoyen}
        {--agent=jury.agent@example.com : E-mail du compte jury agent}
        {--dry-run : Simulation, rien n\'est modifié (comportement par défaut)}
        {--force : Applique réellement les suppressions et les créations}';

    protected $description = 'Supprime les saisies de test et crée des données de démo réalistes pour le jury (simulation par défaut)';

    /**
     * Motifs repérant une saisie de test. « sa » seul : champ entier uniquement, pour ne pas viser « sa carte », « salle »…
     *
     * @var list<string>
     */
    private const MOTIFS_TEST = [
        '/(?<![\p{L}\p{N}])test(?![\p{L}\p{N}])/iu',
        '/test recette/iu',
        '/<script/iu',
        '/^\s*(sa[\s\p{P}]*)+$/iu',
        '/qw/iu',
        '/(?<![\p{L}\p{N}])p93(?![\p{L}\p{N}])/iu',
        '/[aà] supprimer/iu',
    ];

    /**
     * Champs texte examinés pour chaque modèle.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const CHAMPS = [
        Demarche::class => ['titre', 'description'],
        Signalement::class => ['description', 'lieu'],
        Annonce::class => ['titre', 'contenu', 'consignes'],
    ];

    public function handle(): int
    {
        $appliquer = (bool) $this->option('force');

        $citoyen = $this->compte('citoyen');
        $agent = $this->compte('agent');

        if ($citoyen === null || $agent === null) {
            $this->components->error('Compte jury introuvable. Préciser --citoyen=… et --agent=… (comptes déjà inscrits).');

            return self::FAILURE;
        }

        $this->components->info($appliquer ? 'Mode réel : les modifications sont appliquées.' : 'Simulation (par défaut) : rien n’est modifié. Relancer avec --force pour appliquer.');

        $aSupprimer = $this->saisiesDeTest();

        if ($aSupprimer->isEmpty()) {
            $this->components->info('Aucune saisie de test à supprimer.');
        } else {
            $this->components->warn($aSupprimer->count().' saisie(s) de test à supprimer :');
            $this->table(['Type', 'ID', 'Texte'], $aSupprimer->map(fn (Model $modele): array => [
                class_basename($modele),
                $modele->getKey(),
                Str::limit($this->texte($modele), 70),
            ])->all());
        }

        if (! $appliquer) {
            $this->components->info('Données de démo prévues : 5 demandes, 3 signalements, 3 événements de sécurité, 1 anomalie de données (déjà présentes = ignorées).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($aSupprimer, $citoyen, $agent): void {
            $aSupprimer->each(fn (Model $modele) => $modele->delete());

            $this->creerDemandes($citoyen);
            $signalements = $this->creerSignalements($citoyen);
            $this->creerEvenementsSecurite($citoyen, $agent);
            $this->creerAnomalie($signalements);
        });

        $this->components->info($aSupprimer->count().' saisie(s) de test supprimée(s), données de démo en place.');

        return self::SUCCESS;
    }

    private function compte(string $option): ?User
    {
        $email = $this->option($option);

        return is_string($email) ? User::where('email', $email)->first() : null;
    }

    /**
     * @return Collection<int, Model>
     */
    private function saisiesDeTest(): Collection
    {
        $resultat = collect();

        foreach (self::CHAMPS as $classe => $champs) {
            $classe::query()->select(['id', ...$champs])->lazyById()->each(function (Model $modele) use ($resultat): void {
                if ($this->estSaisieDeTest($modele)) {
                    $resultat->push($modele);
                }
            });
        }

        return $resultat;
    }

    private function estSaisieDeTest(Model $modele): bool
    {
        foreach (self::CHAMPS[$modele::class] as $champ) {
            $valeur = (string) $modele->getAttribute($champ);

            foreach (self::MOTIFS_TEST as $motif) {
                if (preg_match($motif, $valeur) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function texte(Model $modele): string
    {
        return collect(self::CHAMPS[$modele::class])
            ->map(fn (string $champ): string => trim((string) $modele->getAttribute($champ)))
            ->filter()
            ->implode(' — ');
    }

    private function creerDemandes(User $citoyen): void
    {
        $demandes = [
            [
                'titre' => 'Personne âgée faisant un malaise, quartier Nord',
                'description' => 'Ma voisine de 82 ans vient de faire un malaise devant chez elle, rue des Jacarandas (quartier Nord). Elle est consciente mais très faible et désorientée. Le 124 a été appelé ; nous demandons une visite du centre de santé et un suivi à domicile, elle vit seule.',
                'categorie' => 'sante',
                'urgence' => true,
            ],
            [
                'titre' => 'Copie intégrale d’acte de naissance pour inscription scolaire',
                'description' => 'Bonjour, j’ai besoin d’une copie intégrale de l’acte de naissance de mon fils (né en 2019 à Nova Terra) pour son inscription à l’école primaire avant la fin du mois.',
                'categorie' => 'administratif',
                'urgence' => false,
            ],
            [
                'titre' => 'Aide pour les frais de cantine scolaire',
                'description' => 'Mère de trois enfants, je suis sans emploi depuis juin. Je souhaite savoir si le CCAS peut prendre en charge une partie des frais de cantine pour ce trimestre.',
                'categorie' => 'social',
                'urgence' => false,
            ],
            [
                'titre' => 'Autorisation de travaux pour une clôture',
                'description' => 'Je voudrais remplacer la haie de ma parcelle (lot 14, quartier Est) par un mur de clôture d’1,80 m. Quelles pièces dois-je fournir pour la déclaration préalable ?',
                'categorie' => 'urbanisme',
                'urgence' => false,
            ],
            [
                'titre' => 'Réservation de la salle polyvalente pour une fête de quartier',
                'description' => 'L’association des habitants du quartier Sud souhaite réserver la salle polyvalente le samedi 17 octobre, de 14 h à 20 h, pour la fête de quartier annuelle (environ 80 personnes).',
                'categorie' => 'culture',
                'urgence' => false,
            ],
        ];

        foreach ($demandes as $donnees) {
            $demarche = Demarche::where('user_id', $citoyen->id)->where('titre', $donnees['titre'])->first() ?? new Demarche(['titre' => $donnees['titre']]);

            if ($demarche->exists) {
                continue;
            }

            $demarche->description = $donnees['description'];
            $demarche->service_id = Service::where('categorie', $donnees['categorie'])->value('id');
            $demarche->urgence_medicale = $donnees['urgence'];
            $demarche->user()->associate($citoyen);
            $demarche->save();
        }
    }

    /**
     * @return list<Signalement>
     */
    private function creerSignalements(User $citoyen): array
    {
        $signalements = [
            [
                'categorie' => 'eclairage',
                'lieu' => 'Rue des Flamboyants, quartier Nord, devant l’école primaire',
                'description' => 'Trois lampadaires sont éteints depuis lundi soir : la rue est plongée dans le noir à la sortie des cours du soir.',
            ],
            [
                'categorie' => 'voirie',
                'lieu' => 'Avenue de l’Indépendance, près du marché Sud',
                'description' => 'Nid-de-poule d’environ 40 cm de profondeur au milieu de la chaussée, deux motos sont déjà tombées ce matin.',
            ],
            [
                'categorie' => 'eclairage',
                'lieu' => 'Rue des Flamboyants, quartier Nord',
                'description' => 'Lampadaires en panne devant l’école primaire de la rue des Flamboyants, il fait tout noir le soir.',
            ],
        ];

        $resultat = [];

        foreach ($signalements as $donnees) {
            $signalement = Signalement::where('user_id', $citoyen->id)->where('description', $donnees['description'])->first() ?? new Signalement(['description' => $donnees['description']]);

            if (! $signalement->exists) {
                $signalement->categorie = $donnees['categorie'];
                $signalement->lieu = $donnees['lieu'];
                $signalement->user()->associate($citoyen);
                $signalement->save();
            }

            $resultat[] = $signalement;
        }

        return $resultat;
    }

    private function creerEvenementsSecurite(User $citoyen, User $agent): void
    {
        $evenements = [
            [$citoyen, SecurityEvent::TYPE_NOUVEL_APPAREIL, SecurityEvent::NIVEAU_INFO, 'Connexion depuis un nouvel appareil : Chrome sur Android, adresse 41.188.12.47.', '41.188.12.47', ['appareil' => 'Chrome sur Android'], 26],
            [$citoyen, SecurityEvent::TYPE_CONNEXION_BLOQUEE, SecurityEvent::NIVEAU_MOYEN, '5 mots de passe erronés en 10 minutes : connexion bloquée temporairement.', '102.16.44.9', ['tentatives' => 5], 9],
            [$agent, SecurityEvent::TYPE_ACCES_REFUSES, SecurityEvent::NIVEAU_ELEVE, '8 accès refusés en 5 minutes sur des dossiers d’un autre service (Action sociale).', '154.126.80.3', ['refus' => 8], 3],
        ];

        foreach ($evenements as [$user, $type, $niveau, $description, $ip, $details, $ilYaHeures]) {
            if (SecurityEvent::where('user_id', $user->id)->where('type', $type)->where('description', $description)->exists()) {
                continue;
            }

            $evenement = new SecurityEvent;
            $evenement->user_id = $user->id;
            $evenement->type = $type;
            $evenement->niveau = $niveau;
            $evenement->description = $description;
            $evenement->details = $details;
            $evenement->ip = $ip;
            $evenement->user_agent = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';
            $evenement->created_at = now()->subHours($ilYaHeures);
            $evenement->save();
        }
    }

    /**
     * Le 3e signalement décrit le même lampadaire que le 1er : doublon repéré par le contrôle d'intégrité.
     *
     * @param  list<Signalement>  $signalements
     */
    private function creerAnomalie(array $signalements): void
    {
        [$original, , $doublon] = $signalements;
        $signature = AnomalieDonnee::TYPE_DOUBLON.':signalements:'.$doublon->id.':'.$original->id;

        if (AnomalieDonnee::where('signature', $signature)->exists()) {
            return;
        }

        $anomalie = new AnomalieDonnee;
        $anomalie->signature = $signature;
        $anomalie->type = AnomalieDonnee::TYPE_DOUBLON;
        $anomalie->table_concernee = 'signalements';
        $anomalie->enregistrement_id = $doublon->id;
        $anomalie->description = "Signalement #{$doublon->id} : même problème que le signalement #{$original->id} (éclairage, rue des Flamboyants).";
        $anomalie->detectee_le = now();
        $anomalie->save();
    }
}
