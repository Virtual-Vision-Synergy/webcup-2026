<?php

namespace Database\Seeders;

use App\Models\Demarche;
use App\Models\Onboarding;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * F70 : permissions fines par service et données confidentielles (relançable sans doublon).
 *
 * Comptes de démo (mot de passe : password, hors production) :
 *   - agent.etat-civil@example.com  Agent rattaché à « État civil »
 *   - agent.social@example.com      Agent rattaché à « Action sociale (CCAS) »
 *   - jury.agent@example.com        Agent rattaché à « État civil » (démonstration des refus)
 *   - agent@example.com             Agent rattaché à tous les services (démos existantes)
 *   - admin@example.com             Administrateur : accès à tout
 * Chaque service reçoit 3 démarches d'habitants fictifs avec téléphone, quartier et situation renseignés (confidentiels).
 * En production : rien n'est créé ; affecter les comptes jury avec `php artisan app:affecter-service`.
 */
class PermissionsServicesSeeder extends Seeder
{
    public const ETAT_CIVIL = 'État civil';

    public const ACTION_SOCIALE = 'Action sociale (CCAS)';

    /** @var array<string, list<array{titre: string, description: string, statut: string, habitant: string, email: string, telephone: string, situation: list<string>}>> */
    private const DEMARCHES = [
        self::ETAT_CIVIL => [
            ['titre' => 'Copie intégrale d’acte de naissance', 'description' => 'Je dois fournir une copie intégrale de mon acte de naissance pour un dossier de passeport.', 'statut' => 'deposee', 'habitant' => 'Voahangy Rasoanaivo', 'email' => 'voahangy.rasoa@example.com', 'telephone' => '0341122334', 'situation' => ['logement']],
            ['titre' => 'Transcription d’un mariage célébré à l’étranger', 'description' => 'Mariage célébré à La Réunion en juin, je souhaite le faire transcrire sur les registres de Nova Terra.', 'statut' => 'en_cours', 'habitant' => 'Andry Rakotobe', 'email' => 'andry.rakotobe@example.com', 'telephone' => '0329988776', 'situation' => ['famille']],
            ['titre' => 'Certificat de résidence', 'description' => 'Certificat demandé par mon employeur pour mon dossier d’embauche.', 'statut' => 'traitee', 'habitant' => 'Soa Randrianarisoa', 'email' => 'soa.randria@example.com', 'telephone' => '0335544332', 'situation' => ['emploi']],
        ],
        self::ACTION_SOCIALE => [
            ['titre' => 'Demande d’aide alimentaire d’urgence', 'description' => 'Famille de quatre enfants, perte d’emploi le mois dernier : nous demandons une aide alimentaire.', 'statut' => 'deposee', 'habitant' => 'Lalao Ravelojaona', 'email' => 'lalao.ravelo@example.com', 'telephone' => '0347766554', 'situation' => ['famille', 'emploi']],
            ['titre' => 'Aide au paiement du loyer', 'description' => 'Retard de deux mois de loyer suite à une hospitalisation, je demande un accompagnement.', 'statut' => 'en_cours', 'habitant' => 'Hery Andrianjafy', 'email' => 'hery.andrianjafy@example.com', 'telephone' => '0321234987', 'situation' => ['logement']],
            ['titre' => 'Dossier d’aide aux personnes âgées', 'description' => 'Demande d’aide à domicile pour ma mère de 82 ans qui vit seule.', 'statut' => 'deposee', 'habitant' => 'Nirina Rabemananjara', 'email' => 'nirina.rabe@example.com', 'telephone' => '0336677889', 'situation' => ['famille']],
        ],
    ];

    public function run(): void
    {
        // Données fictives avec des e-mails connus : jamais en production.
        if (app()->isProduction()) {
            return;
        }

        $services = Service::query()->whereIn('nom', [self::ETAT_CIVIL, self::ACTION_SOCIALE])->get()->keyBy('nom');

        if ($services->count() < 2) {
            return;
        }

        $this->agent('agent.etat-civil@example.com', 'Agent État civil')->services()->syncWithoutDetaching([$services[self::ETAT_CIVIL]->id]);
        $this->agent('agent.social@example.com', 'Agent Action sociale')->services()->syncWithoutDetaching([$services[self::ACTION_SOCIALE]->id]);

        User::query()->where('email', 'jury.agent@example.com')->first()?->services()->syncWithoutDetaching([$services[self::ETAT_CIVIL]->id]);
        User::query()->where('email', 'agent@example.com')->first()?->services()->syncWithoutDetaching(Service::query()->pluck('id')->all());

        foreach (self::DEMARCHES as $nomService => $demarches) {
            foreach ($demarches as $donnees) {
                $this->demarche($services[$nomService], $donnees);
            }
        }
    }

    private function agent(string $email, string $nom): User
    {
        return User::query()->where('email', $email)->first()
            ?? User::factory()->agent()->create(['name' => $nom, 'email' => $email]);
    }

    /**
     * @param  array{titre: string, description: string, statut: string, habitant: string, email: string, telephone: string, situation: list<string>}  $donnees
     */
    private function demarche(Service $service, array $donnees): void
    {
        $habitant = User::query()->where('email', $donnees['email'])->first()
            ?? User::factory()->citoyen()->profilComplet()->create([
                'name' => $donnees['habitant'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'],
            ]);

        if ($habitant->role_id !== Role::idFor(Role::CITOYEN)) {
            return;
        }

        $onboarding = Onboarding::query()->firstOrNew(['user_id' => $habitant->id]);
        $onboarding->forceFill(['user_id' => $habitant->id, 'situation' => $donnees['situation'], 'completed_at' => $onboarding->completed_at ?? now()])->save();

        $demarche = Demarche::query()->firstOrNew(['titre' => $donnees['titre'], 'service_id' => $service->id, 'user_id' => $habitant->id]);
        $demarche->description = $donnees['description'];
        $demarche->statut = $donnees['statut'];
        $demarche->service_id = $service->id;
        $demarche->user()->associate($habitant);
        $demarche->save();
    }
}
