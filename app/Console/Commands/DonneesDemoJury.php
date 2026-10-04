<?php

namespace App\Console\Commands;

use App\Models\Annonce;
use App\Models\AnomalieDonnee;
use App\Models\Demarche;
use App\Models\SecurityEvent;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\TentativeBloquee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nettoie les saisies de test (demandes, signalements, annonces) et ajoute des donnÃ©es de dÃ©mo rÃ©alistes
 * rattachÃ©es aux comptes jury existants. Idempotente : relancÃ©e, elle ne recrÃ©e rien en double.
 *
 * Sans --force, rien n'est modifiÃ© (simulation) : la liste des suppressions et des crÃ©ations est seulement affichÃ©e.
 * Aucune autre donnÃ©e n'est supprimÃ©e.
 */
class DonneesDemoJury extends Command
{
    protected $signature = 'app:donnees-demo-jury
        {--citoyen=user@example.com : E-mail du compte jury citoyen}
        {--agent=jury.agent@example.com : E-mail du compte jury agent}
        {--dry-run : Simulation, rien n\'est modifiÃ© (comportement par dÃ©faut)}
        {--force : Applique rÃ©ellement les suppressions et les crÃ©ations}';

    protected $description = 'Supprime les saisies de test et crÃ©e des donnÃ©es de dÃ©mo rÃ©alistes pour le jury (simulation par dÃ©faut)';

    /**
     * Texte contenu dans une saisie de test.
     *
     * @var list<string>
     */
    private const MOTIFS_TEST = [
        '/test recette/iu',
        '/<script/iu',
        '/(?<![\p{L}\p{N}])p93(?![\p{L}\p{N}])/iu',
        '/[aÃ ] supprimer/iu',
    ];

    /**
     * Valeurs complÃ¨tes d'un champ (titreâ€¦) qui signalent une saisie de test : comparÃ©es au champ entier,
     * pour ne pas viser Â« sa carte Â» ou une description qui parle de date de naissance.
     *
     * @var list<string>
     */
    private const VALEURS_TEST = ['sa', 'qw', 'date de naissance'];

    /**
     * Champs texte examinÃ©s pour chaque modÃ¨le.
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
            $this->components->error('Compte jury introuvable. PrÃ©ciser --citoyen=â€¦ et --agent=â€¦ (comptes dÃ©jÃ  inscrits).');

            return self::FAILURE;
        }

        $this->components->info($appliquer ? 'Mode rÃ©el : les modifications sont appliquÃ©es.' : 'Simulation (par dÃ©faut) : rien nâ€™est modifiÃ©. Relancer avec --force pour appliquer.');

        $aSupprimer = $this->saisiesDeTest();

        if ($aSupprimer->isEmpty()) {
            $this->components->info('Aucune saisie de test Ã  supprimer.');
        } else {
            $this->components->warn($aSupprimer->count().' saisie(s) de test Ã  supprimer :');
            $this->table(['Type', 'ID', 'Texte'], $aSupprimer->map(fn (Model $modele): array => [
                class_basename($modele),
                $modele->getKey(),
                Str::limit($this->texte($modele), 70),
            ])->all());
        }

        if (! $appliquer) {
            $this->components->info('DonnÃ©es de dÃ©mo prÃ©vues : 5 demandes, 5 signalements, 3 Ã©vÃ©nements de sÃ©curitÃ©, 1 anomalie de donnÃ©es, 2 tentatives de robots bloquÃ©es (dÃ©jÃ  prÃ©sentes = ignorÃ©es).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($aSupprimer, $citoyen, $agent): void {
            $aSupprimer->each(fn (Model $modele) => $modele->delete());

            $this->creerDemandes($citoyen);
            $signalements = $this->creerSignalements($citoyen);
            $this->creerEvenementsSecurite($citoyen, $agent);
            $this->creerAnomalie($signalements);
            $this->creerTentativesBloquees();
        });

        $this->components->info($aSupprimer->count().' saisie(s) de test supprimÃ©e(s), donnÃ©es de dÃ©mo en place.');

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

            if (in_array(Str::lower(trim(trim($valeur), '.!?')), self::VALEURS_TEST, true)) {
                return true;
            }

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
            ->implode(' â€” ');
    }

    private function creerDemandes(User $citoyen): void
    {
        $demandes = [
            [
                'titre' => 'Personne Ã¢gÃ©e faisant un malaise, quartier Nord',
                'description' => 'Ma voisine de 82 ans vient de faire un malaise devant chez elle, rue des Jacarandas (quartier Nord). Elle est consciente mais trÃ¨s faible et dÃ©sorientÃ©e. Le 124 a Ã©tÃ© appelÃ© ; nous demandons une visite du centre de santÃ© et un suivi Ã  domicile, elle vit seule.',
                'categorie' => 'sante',
                'urgence' => true,
            ],
            [
                'titre' => 'Copie intÃ©grale dâ€™acte de naissance pour inscription scolaire',
                'description' => 'Bonjour, jâ€™ai besoin dâ€™une copie intÃ©grale de lâ€™acte de naissance de mon fils (nÃ© en 2019 Ã  Nova Terra) pour son inscription Ã  lâ€™Ã©cole primaire avant la fin du mois.',
                'categorie' => 'administratif',
                'urgence' => false,
            ],
            [
                'titre' => 'Aide pour les frais de cantine scolaire',
                'description' => 'MÃ¨re de trois enfants, je suis sans emploi depuis juin. Je souhaite savoir si le CCAS peut prendre en charge une partie des frais de cantine pour ce trimestre.',
                'categorie' => 'social',
                'urgence' => false,
            ],
            [
                'titre' => 'Autorisation de travaux pour une clÃ´ture',
                'description' => 'Je voudrais remplacer la haie de ma parcelle (lot 14, quartier Est) par un mur de clÃ´ture dâ€™1,80 m. Quelles piÃ¨ces dois-je fournir pour la dÃ©claration prÃ©alable ?',
                'categorie' => 'urbanisme',
                'urgence' => false,
            ],
            [
                'titre' => 'RÃ©servation de la salle polyvalente pour une fÃªte de quartier',
                'description' => 'Lâ€™association des habitants du quartier Sud souhaite rÃ©server la salle polyvalente le samedi 17 octobre, de 14 h Ã  20 h, pour la fÃªte de quartier annuelle (environ 80 personnes).',
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
        $lieuLampadaire = 'Rue Andrianampoinimerina, quartier Nord, devant la pharmacie';
        $lieuFuite = 'Angle rue Rainandriamampandry et avenue de lâ€™IndÃ©pendance';

        $signalements = [
            [
                'categorie' => 'eclairage',
                'lieu' => $lieuLampadaire,
                'description' => 'Lampadaire cassÃ© rue Andrianampoinimerina : le globe est tombÃ© et lâ€™ampoule ne sâ€™allume plus, le trottoir est dans le noir le soir.',
            ],
            [
                'categorie' => 'eclairage',
                'lieu' => $lieuLampadaire,
                'description' => 'Lampadaire cassÃ© rue Andrianampoinimerina, devant la pharmacie : plus de lumiÃ¨re depuis trois soirs, câ€™est dangereux pour les piÃ©tons.',
            ],
            [
                'categorie' => 'eclairage',
                'lieu' => $lieuLampadaire,
                'description' => 'Lampadaire cassÃ© rue Andrianampoinimerina, le poteau penche et la lampe est Ã©teinte, les enfants rentrent de lâ€™Ã©cole dans le noir.',
            ],
            [
                'categorie' => 'eau',
                'lieu' => $lieuFuite,
                'description' => 'Fuite dâ€™eau sur la canalisation sous le trottoir : lâ€™eau coule en continu depuis ce matin et inonde la chaussÃ©e.',
            ],
            [
                'categorie' => 'eau',
                'lieu' => $lieuFuite,
                'description' => 'Fuite dâ€™eau importante au coin de la rue, une flaque Ã©norme se forme et la pression a baissÃ© dans les maisons voisines.',
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
            [$citoyen, SecurityEvent::TYPE_CONNEXION_BLOQUEE, SecurityEvent::NIVEAU_MOYEN, '5 mots de passe erronÃ©s en 10 minutes : connexion bloquÃ©e temporairement.', '102.16.44.9', ['tentatives' => 5], 9],
            [$agent, SecurityEvent::TYPE_ACCES_REFUSES, SecurityEvent::NIVEAU_ELEVE, '8 accÃ¨s refusÃ©s en 5 minutes sur des dossiers dâ€™un autre service (Action sociale).', '154.126.80.3', ['refus' => 8], 3],
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
     * Le 2e signalement dÃ©crit le mÃªme lampadaire que le 1er : doublon repÃ©rÃ© par le contrÃ´le d'intÃ©gritÃ©.
     *
     * @param  list<Signalement>  $signalements
     */
    private function creerAnomalie(array $signalements): void
    {
        [$original, $doublon] = $signalements;
        $signature = AnomalieDonnee::TYPE_DOUBLON.':signalements:'.$doublon->id.':'.$original->id;

        if (AnomalieDonnee::where('signature', $signature)->exists()) {
            return;
        }

        $anomalie = new AnomalieDonnee;
        $anomalie->signature = $signature;
        $anomalie->type = AnomalieDonnee::TYPE_DOUBLON;
        $anomalie->table_concernee = 'signalements';
        $anomalie->enregistrement_id = $doublon->id;
        $anomalie->description = "Signalement #{$doublon->id} : mÃªme problÃ¨me que le signalement #{$original->id} (lampadaire cassÃ©, rue Andrianampoinimerina).";
        $anomalie->detectee_le = now();
        $anomalie->save();
    }

    /**
     * F81 : deux envois de formulaire bloquÃ©s par la protection anti-robots (aucune donnÃ©e saisie conservÃ©e).
     */
    private function creerTentativesBloquees(): void
    {
        $tentatives = [
            [TentativeBloquee::FORMULAIRE_INSCRIPTION, TentativeBloquee::MOTIF_HONEYPOT, '185.220.101.34', 'python-requests/2.31.0', 5],
            [TentativeBloquee::FORMULAIRE_CONTACT, TentativeBloquee::MOTIF_TROP_RAPIDE, '45.146.164.110', 'Mozilla/5.0 (compatible; HeadlessChrome/120.0)', 2],
        ];

        foreach ($tentatives as [$formulaire, $motif, $ip, $userAgent, $ilYaHeures]) {
            if (TentativeBloquee::where('formulaire', $formulaire)->where('motif', $motif)->where('ip', $ip)->exists()) {
                continue;
            }

            $tentative = new TentativeBloquee;
            $tentative->formulaire = $formulaire;
            $tentative->motif = $motif;
            $tentative->ip = $ip;
            $tentative->user_agent = $userAgent;
            $tentative->setAttribute('created_at', now()->subHours($ilYaHeures));
            $tentative->save();
        }
    }
}
