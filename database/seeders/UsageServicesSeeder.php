<?php

namespace Database\Seeders;

use App\Models\Demarche;
use App\Models\Quartier;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\VueService;
use App\Services\UsageServices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * F98 : 120 jours de consultations anonymisées et de démarches datées, pour que le tableau de bord
 * « Usage des services » montre un vrai résultat (classement, hausse, abandons, service très consulté peu converti).
 * Idempotent : ne fait rien si des consultations existent déjà pour ces services.
 */
class UsageServicesSeeder extends Seeder
{
    /**
     * Profil d'usage : consultations / jour, démarches / semaine, croissance récente (×), part abandonnée (0-1).
     *
     * @var array<string, array{vues: int, demarches: int, hausse: float, abandon: float}>
     */
    private const PROFILS = [
        'État civil' => ['vues' => 18, 'demarches' => 9, 'hausse' => 1.4, 'abandon' => 0.08],
        'Urbanisme' => ['vues' => 9, 'demarches' => 4, 'hausse' => 1.0, 'abandon' => 0.45],
        'Action sociale (CCAS)' => ['vues' => 8, 'demarches' => 5, 'hausse' => 1.1, 'abandon' => 0.15],
        'Services techniques et voirie' => ['vues' => 6, 'demarches' => 3, 'hausse' => 0.8, 'abandon' => 0.2],
        'Médiathèque Ravinala' => ['vues' => 14, 'demarches' => 0, 'hausse' => 1.0, 'abandon' => 0.0],
        'Petite enfance et écoles' => ['vues' => 7, 'demarches' => 3, 'hausse' => 1.6, 'abandon' => 0.1],
        'Santé publique' => ['vues' => 10, 'demarches' => 2, 'hausse' => 1.2, 'abandon' => 0.1],
        'Accueil de la Mairie' => ['vues' => 5, 'demarches' => 1, 'hausse' => 1.0, 'abandon' => 0.1],
    ];

    /** @var array<string, list<string>> Objets de démarches crédibles par service. */
    private const TITRES = [
        'État civil' => ["Demande d'acte de naissance", 'Copie intégrale d’acte de mariage', 'Déclaration de naissance', 'Certificat de vie'],
        'Urbanisme' => ['Demande de permis de construire', 'Déclaration préalable de travaux', 'Certificat d’urbanisme'],
        'Action sociale (CCAS)' => ['Demande d’aide au loyer', 'Dossier d’aide alimentaire', 'Domiciliation administrative'],
        'Services techniques et voirie' => ['Nid-de-poule à reboucher', 'Éclairage public défaillant', 'Autorisation d’occupation du trottoir'],
        'Petite enfance et écoles' => ['Inscription scolaire en école primaire', 'Inscription à la cantine', 'Place en crèche municipale'],
        'Santé publique' => ['Rendez-vous de vaccination', 'Certificat médical scolaire'],
        'Accueil de la Mairie' => ['Légalisation de signature', 'Demande de renseignements'],
    ];

    public function run(): void
    {
        $services = Service::query()->whereIn('nom', array_keys(self::PROFILS))->get()->keyBy('nom');
        $quartiers = Quartier::query()->pluck('id')->all();
        $habitants = User::query()->where('role_id', Role::idFor(Role::CITOYEN))->get();

        if ($services->isEmpty() || $habitants->isEmpty()) {
            return;
        }

        if (VueService::query()->whereIn('service_id', $services->pluck('id'))->exists()) {
            return;
        }

        $vues = [];
        $maintenant = now();

        foreach (self::PROFILS as $nom => $profil) {
            $service = $services->get($nom);

            if ($service === null) {
                continue;
            }

            for ($jour = 119; $jour >= 0; $jour--) {
                $date = Carbon::today()->subDays($jour);
                // La « hausse » s'applique sur les 7 derniers jours : visible en comparaison semaine / semaine.
                $facteur = ($jour < 7 ? $profil['hausse'] : 1.0) * ($date->isWeekend() ? 0.4 : 1.0);

                foreach ($quartiers ?: [null] as $quartierId) {
                    $nombre = (int) round($profil['vues'] * $facteur / max(count($quartiers), 1) * (mt_rand(70, 130) / 100));

                    if ($nombre > 0) {
                        $vues[] = ['service_id' => $service->id, 'quartier_id' => $quartierId, 'jour' => $date->toDateString(), 'nombre' => $nombre, 'created_at' => $maintenant, 'updated_at' => $maintenant];
                    }
                }

                $parJour = $profil['demarches'] * $facteur / 7;
                $nombreDemarches = (int) floor($parJour) + (mt_rand() / mt_getrandmax() < fmod($parJour, 1) ? 1 : 0);

                for ($i = 0; $i < $nombreDemarches; $i++) {
                    $this->creerDemarche($service, $habitants->random(), $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59)), $profil['abandon']);
                }
            }
        }

        foreach (array_chunk($vues, 500) as $paquet) {
            VueService::query()->insert($paquet);
        }
    }

    private function creerDemarche(Service $service, User $habitant, Carbon $date, float $abandon): void
    {
        $tirage = mt_rand() / mt_getrandmax();
        $ancienne = $date->lt(now()->subDays(UsageServices::JOURS_AVANT_ABANDON));

        $statut = match (true) {
            $tirage < $abandon => $ancienne && mt_rand(0, 1) === 1 ? 'deposee' : 'refusee',
            ! $ancienne => mt_rand(0, 1) === 1 ? 'en_cours' : 'deposee',
            default => 'traitee',
        };

        $demarche = Demarche::factory()->for($habitant)->for($service)->make([
            'titre' => fake()->randomElement(self::TITRES[$service->nom] ?? ['Demande de renseignements']),
            'created_at' => $date,
            'updated_at' => $date,
        ]);
        $demarche->statut = $statut;
        $demarche->saveQuietly();
    }
}
