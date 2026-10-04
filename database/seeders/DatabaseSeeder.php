<?php

namespace Database\Seeders;

use App\Models\Actualite;
use App\Models\AlerteCanicule;
use App\Models\Annonce;
use App\Models\Demarche;
use App\Models\Message;
use App\Models\Onboarding;
use App\Models\Role;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Réinitialisation depuis l'admin (App\Services\ReinitialisationDonnees) : les comptes existent déjà,
     * ils sont conservés et les comptes de démo sont remis dans leur état d'origine au lieu d'être recréés.
     */
    public bool $reinitialisation = false;

    /**
     * Compte à ne pas modifier pendant la réinitialisation (l'admin connecté qui l'a lancée).
     */
    public ?int $compteProtegeId = null;

    /**
     * Données de démonstration.
     *
     * En local, un compte par rôle (mot de passe : password) :
     *   - admin@example.com       Administrateur
     *   - agent@example.com       Agent municipal
     *   - jury.agent@example.com  Agent municipal (compte jury agent)
     *   - user@example.com        Citoyen
     *   - desactive@example.com   Citoyen désactivé (connexion refusée)
     *   - user@example.com        Citoyen (prise en main terminée)
     *   - nouveau@example.com     Citoyen tout neuf (prise en main jamais vue → /bienvenue)
     *   - parcours@example.com    Citoyen à mi-parcours (profil complet, 1/3)
     *   - passe@example.com       Citoyen ayant passé la prise en main
     * En production : aucun compte avec un mot de passe connu n'est créé ;
     * les comptes jury sont créés à la main (/register) puis passés agent ou admin dans /admin/users.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(QuartierSeeder::class);

        // D09 : seeder relançable sans doublon. Les comptes de démo existent déjà → rien à recréer
        // (php artisan migrate:fresh --seed pour repartir de zéro, en local uniquement).
        if (! $this->reinitialisation && User::where('email', 'admin@example.com')->exists()) {
            return;
        }

        if (! app()->isProduction()) {
            $this->compteDemo(User::factory()->admin(), 'Admin Démo', 'admin@example.com');

            $this->compteDemo(User::factory()->agent(), 'Agent Démo', 'agent@example.com');

            $this->compteDemo(User::factory()->agent(), 'Jury Agent', 'jury.agent@example.com');

            $citoyenDemo = $this->compteDemo(User::factory()->profilComplet(), 'Citoyen Démo', 'user@example.com');
            // Compte de démo historique : parcours de prise en main déjà terminé (pas de redirection vers /bienvenue).
            Onboarding::factory()->termine()->for($citoyenDemo)->create();

            // Parcours de prise en main (D12) : un nouvel habitant, un à mi-parcours, un qui l'a passé.
            $this->compteDemo(User::factory(), 'Fanja Nouvelle', 'nouveau@example.com');

            $this->compteDemo(User::factory()->profilComplet(), 'Tahina Enchemin', 'parcours@example.com');

            $citoyenPasse = $this->compteDemo(User::factory(), 'Mialy Pressée', 'passe@example.com');
            Onboarding::factory()->passe()->for($citoyenPasse)->create();

            // F34 : citoyens aux noms variés pour démontrer la recherche, dont un compte déjà désactivé.
            foreach ([
                ['Hanitra Rakotomalala', 'hanitra.rakoto@example.com'],
                ['Tojo Andriamanana', 'tojo.andria@example.com'],
                ['Awa Diallo', 'awa.diallo@example.com'],
                ['Lucas Moreau', 'lucas.moreau@example.com'],
                ['Fanja Razafindrabe', 'fanja.razaf@example.com'],
                ['Inès Benali', 'ines.benali@example.com'],
                ['Mamy Rasolofo', 'mamy.rasolofo@example.com'],
                ['Chloé Martin', 'chloe.martin@example.com'],
                ['Kevin Ramanantsoa', 'kevin.ramanantsoa@example.com'],
            ] as [$name, $email]) {
                $this->compteDemo(User::factory()->citoyen(), $name, $email);
            }

            $this->compteDemo(User::factory()->citoyen()->deactivated(), 'Compte Désactivé', 'desactive@example.com');
        }

        // Habitants anonymes : créés au premier seed seulement (la réinitialisation réutilise les comptes existants).
        if (! $this->reinitialisation) {
            User::factory(8)->create([
                'password' => Str::password(32),
            ]);
        }

        // Les comptes de démo du parcours de prise en main ne reçoivent pas de démarches aléatoires (sinon l'étape 3 serait faite).
        $users = User::query()->whereNotIn('email', ['nouveau@example.com', 'parcours@example.com', 'passe@example.com'])->get();

        $this->call(ServiceSeeder::class);

        // D10 : synonymes d'orientation (« poubelle » → Environnement et propreté), éditables dans Filament.
        $this->call(MotsClesServiceSeeder::class);

        // F91 : règles de l'assistant d'orientation (questions fréquentes), éditables dans Filament.
        $this->call(ReglesAssistantSeeder::class);

        // F31 : recommandations canicule écrites (profil × niveau), éditables dans Filament.
        $this->call(RecommandationsCaniculeSeeder::class);

        // F31 : alerte canicule de démonstration sur le quartier du citoyen de démo (bandeau + page « Canicule »).
        $quartierDemo = User::query()->where('email', 'user@example.com')->value('quartier_id');
        if (! app()->isProduction() && $quartierDemo !== null) {
            $canicule = AlerteCanicule::factory()->create([
                'user_id' => User::query()->where('email', 'admin@example.com')->value('id'),
                'niveau' => 'alerte',
                'temperature_max' => 39,
                'message' => 'Vague de chaleur extrême jusqu’à jeudi : jusqu’à 39 °C l’après-midi. Protégez les personnes âgées, les jeunes enfants et les malades.',
                'notified_at' => now(),
            ]);
            $canicule->quartiers()->sync([$quartierDemo]);
            AlerteCanicule::oublierCache();
        }

        Actualite::factory(20)->recycle($users)->create();

        Message::factory(20)->recycle($users)->create();

        $services = Service::all();
        Demarche::factory(20)->recente()->recycle($users)->recycle($services)->create();

        if (! app()->isProduction()) {
            // Quelques démarches pour le compte citoyen de démo : son espace personnel n'est pas vide.
            Demarche::factory(4)->recycle($services)->for(User::where('email', 'user@example.com')->firstOrFail())->create();
            // F86 : deux urgences médicales ouvertes (tête de la liste agent, compteur « À traiter en priorité »).
            Demarche::factory(2)->urgenceMedicale()->recycle($services)->for(User::where('email', 'user@example.com')->firstOrFail())->create();
        }

        // Messages généraux (D18) : un en cours, un programmé, un expiré, publiés par un agent.
        $auteur = User::where('role_id', Role::idFor(Role::AGENT))->first() ?? $users->first();
        Annonce::factory()->active()->for($auteur)->create([
            'titre' => 'Coupure d’eau à Ambohijanahary',
            'contenu' => 'Travaux sur le réseau : l’eau sera coupée dans le quartier Ambohijanahary aujourd’hui de 9 h à 16 h. Faites une réserve et évitez les lessives ; un camion-citerne stationne place des Pionniers.',
            'niveau' => 'vigilance',
        ]);
        Annonce::factory()->programmee()->for($auteur)->create([
            'titre' => 'Alerte météo : vents violents demain',
            'contenu' => 'Rafales jusqu’à 110 km/h attendues. Rentrez le mobilier extérieur, limitez vos déplacements et suivez les consignes du Haut Conseil sur cette plateforme.',
            'niveau' => 'alerte',
        ]);
        Annonce::factory()->expiree()->for($auteur)->create([
            'titre' => 'Fermeture exceptionnelle de la mairie annexe',
            'contenu' => 'La mairie annexe du secteur Nord était fermée pour inventaire. Les démarches restaient possibles en ligne.',
            'niveau' => 'information',
        ]);

        // Alerte ciblée F29 « Montée des eaux — quartier sud » + habitants sud@example.com et nord@example.com.
        $this->call(AlerteMonteeDesEauxSeeder::class);

        // F101 : alerte « Panne électrique — secteur nord » en cours (nord@example.com la voit et est notifié).
        $this->call(AlertePanneElectriqueSeeder::class);

        if (! app()->isProduction()) {
            // F30 : notifications lues et non lues pour user@example.com + annonce « Danger » programmée dans 5 min.
            $this->call(NotificationsAnnoncesSeeder::class);
        }

        Signalement::factory(15)->recycle($users)->create();
        Signalement::factory(5)->nouveau()->recycle($users)->create();

        if (! app()->isProduction()) {
            // Signalements du compte citoyen de démo, dont un lampadaire cassé tout juste signalé.
            $citoyen = User::where('email', 'user@example.com')->firstOrFail();
            Signalement::factory()->nouveau()->for($citoyen)->create([
                'categorie' => 'eclairage',
                'description' => 'Le lampadaire devant chez moi est cassé, la rue est plongée dans le noir depuis trois jours.',
                'lieu' => 'Rue des Lumières, devant le n° 12',
            ]);
            Signalement::factory(2)->for($citoyen)->create();
        }

        // F75 : groupes de signalements qui décrivent le même problème (regroupement côté agent).
        $this->call(SignalementsSimilairesSeeder::class);

        // Soutiens d'habitants aux demandes encore ouvertes (F52) : chaque citoyen soutient au plus une fois.
        $citoyens = $users->filter(fn (User $user): bool => $user->isCitoyen());
        Signalement::query()->whereIn('statut', Signalement::STATUTS_OUVERTS)->get()
            ->each(function (Signalement $signalement) use ($citoyens): void {
                $citoyens->reject(fn (User $user): bool => $user->id === $signalement->user_id)
                    ->shuffle()
                    ->take(random_int(0, 5))
                    ->each(fn (User $user) => $signalement->ajouterSoutien($user));
            });

        if (! app()->isProduction()) {
            $this->call(LoginAttemptSeeder::class);

            // F81 : envois de formulaires bloqués (robots) pour l'écran admin « Robots bloqués ».
            $this->call(TentativeBloqueeSeeder::class);
        }

        if (! app()->isProduction()) {
            // Journal d'audit de démo (F47). Les autres seeders n'écrivent rien dans le journal (WithoutModelEvents).
            $this->call(AuditLogSeeder::class);
        }

        $this->call(LigneTransportSeeder::class);

        // F39 : services ouverts aux rendez-vous, créneaux sur 14 jours ouvrés, agenda du jour pour agent@example.com.
        $this->call(RendezVousSeeder::class);

        // F38 : État civil en incident, Médiathèque en maintenance, une interruption passée (historique).
        $this->call(ServiceInterruptionSeeder::class);

        if (! app()->isProduction()) {
            // D11 : « Mes demandes » de user@example.com (4 suivis), voisin@example.com (403), sans.demande@example.com (état vide).
            $this->call(MesDemandesSeeder::class);
        }

        // F67 : projets en cours dans la ville (voirie, école, parc, réseau d'eau, énergie).
        $this->call(ProjetSeeder::class);

        // F51 : remontées sur les données de user@example.com (Reçue, Prise en compte, Répondue).
        $this->call(RemonteeSeeder::class);

        if (! app()->isProduction()) {
            // F54 : deux appareils connus, historique de connexions et alerte « nouvel appareil » pour user@example.com.
            $this->call(KnownDeviceSeeder::class);
        }

        // F40 : rendez-vous de démo rappelé automatiquement ~10 min après le seed (hors production).
        $this->call(RappelRendezVousSeeder::class);

        // F70 : agents rattachés à leurs services (État civil, Action sociale) et dossiers aux données confidentielles.
        $this->call(PermissionsServicesSeeder::class);

        // F74 : 4 partenaires de Nova Terra (page publique /partenaires).
        $this->call(PartnerSeeder::class);

        // F64 : un service perturbé (Urbanisme) et la Médiathèque indisponible, après les interruptions F38.
        $this->call(EtatServicesSeeder::class);

        // F68 : six idées de la boîte à idées (états variés, soutiens, deux réponses de la ville).
        $this->call(IdeaSeeder::class);

        if (! app()->isProduction()) {
            // F84 : échanges agent / habitant sur les démarches de user@example.com.
            $this->call(ReponseDemarcheSeeder::class);
        }

        // F76 : avis des habitants sur trois services (notes variées, deux réponses, un avis masqué).
        $this->call(ServiceReviewSeeder::class);

        if (! app()->isProduction()) {
            // F98 : 120 jours de consultations et de démarches pour le tableau de bord « Usage des services ».
            $this->call(UsageServicesSeeder::class);
        }

        // make:feature:seeders
    }

    /**
     * Crée un compte de démo, ou le remet dans son état d'origine s'il existe déjà (mot de passe, rôle, activation…).
     * Le compte de l'admin qui lance la réinitialisation n'est pas modifié : sa session reste valide.
     */
    private function compteDemo(UserFactory $factory, string $name, string $email): User
    {
        $attributs = ['name' => $name, 'email' => $email];
        $existant = User::where('email', $email)->first();

        if ($existant === null) {
            return $factory->createOne($attributs);
        }

        if ($existant->id !== $this->compteProtegeId) {
            $existant->forceFill(['deactivated_at' => null, ...$factory->makeOne($attributs)->getAttributes()])->save();
        }

        return $existant;
    }
}
