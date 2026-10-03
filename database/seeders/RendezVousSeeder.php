<?php

namespace Database\Seeders;

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Prise de rendez-vous (F39). Idempotent.
 *
 * - Ouvre les rendez-vous de 4 services (lieu, durée, pièces à apporter) et génère 14 jours ouvrés de créneaux.
 * - Hors production : des créneaux aujourd'hui (même un samedi ou un dimanche, pour la démo de l'agenda agent),
 *   dont plusieurs réservés, et les rendez-vous de user@example.com (2 à venir, 1 annulé, 1 passé).
 */
class RendezVousSeeder extends Seeder
{
    /** @var array<string, array{lieu_rendez_vous: string, duree_rendez_vous: int, pieces_a_fournir: string}> */
    public const SERVICES = [
        'État civil' => [
            'lieu_rendez_vous' => 'Hôtel de ville, rez-de-chaussée, guichet 2 (état civil)',
            'duree_rendez_vous' => 30,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nLivret de famille (si vous en avez un)\nJustificatif de domicile de moins de 3 mois",
        ],
        'Accueil de la Mairie' => [
            'lieu_rendez_vous' => 'Hôtel de ville, hall d’accueil, bureau 1',
            'duree_rendez_vous' => 15,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nTout courrier ou document lié à votre demande",
        ],
        'Urbanisme' => [
            'lieu_rendez_vous' => 'Centre administratif, 2e étage, bureau 204 (service urbanisme)',
            'duree_rendez_vous' => 45,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nPlan de situation du terrain\nTitre de propriété ou autorisation du propriétaire\nCroquis ou plans du projet",
        ],
        'Action sociale (CCAS)' => [
            'lieu_rendez_vous' => 'Maison des solidarités, 8 rue des Baobabs, accueil du CCAS',
            'duree_rendez_vous' => 30,
            'pieces_a_fournir' => "Pièce d'identité en cours de validité\nJustificatif de domicile de moins de 3 mois\nJustificatifs de ressources des 3 derniers mois\nLivret de famille (si vous en avez un)",
        ],
    ];

    /** Motifs de démo. */
    private const MOTIFS = [
        'Demande de copie intégrale d’acte de naissance.',
        'Préparation du dossier de mariage.',
        'Reconnaissance anticipée d’un enfant.',
        'Dépôt d’une déclaration préalable de travaux.',
        'Demande d’aide pour les frais de cantine.',
        null,
    ];

    public function run(): void
    {
        foreach (self::SERVICES as $nom => $infos) {
            Service::query()->where('nom', $nom)->whereNull('duree_rendez_vous')->update($infos);
        }

        Artisan::call('appointments:generate-slots', ['--days' => 14]);

        if (app()->isProduction() || RendezVous::query()->exists()) {
            return;
        }

        $services = Service::query()->prendRendezVous()->whereIn('nom', array_keys(self::SERVICES))->get()->keyBy('nom');
        $citoyens = User::query()->citizens()->whereNull('deactivated_at')->where('email', '!=', 'user@example.com')->get();
        $demo = User::query()->where('email', 'user@example.com')->first();

        if ($services->isEmpty() || $citoyens->isEmpty()) {
            return;
        }

        $fuseau = CreneauRendezVous::fuseau();
        $aujourdhui = CarbonImmutable::now($fuseau)->startOfDay();
        $maintenant = now();

        // Agenda du jour : un créneau sur deux est réservé, passés « honoré » ou « absent », à venir « confirmé ».
        $i = 0;
        // toBase() : sur une collection Eloquent, only() filtrerait par clé primaire et non par nom.
        foreach ($services->toBase()->only(['État civil', 'Urbanisme', 'Action sociale (CCAS)']) as $service) {
            foreach ($this->creneauxDuJour($service, $aujourdhui) as $index => $creneau) {
                if ($index % 2 === 1 || $creneau->rendez_vous_id !== null) {
                    continue;
                }

                $statut = $creneau->debut->isPast() ? ($i % 4 === 3 ? 'absent' : 'honore') : 'confirme';
                $this->reserver($citoyens[$i % $citoyens->count()], $creneau, $statut, self::MOTIFS[$i % count(self::MOTIFS)]);
                $i++;
            }
        }

        // Quelques réservations sur les jours suivants, pour que des créneaux disparaissent de la liste.
        CreneauRendezVous::query()
            ->libres()
            ->where('debut', '>', $maintenant->addDay())
            ->inRandomOrder()
            ->limit(12)
            ->get()
            ->each(function (CreneauRendezVous $creneau, int $index) use ($citoyens): void {
                $this->reserver($citoyens[$index % $citoyens->count()], $creneau, 'confirme', self::MOTIFS[$index % count(self::MOTIFS)]);
            });

        if ($demo === null) {
            return;
        }

        // Compte de démo : un rendez-vous État civil et un Urbanisme à venir, un annulé, un passé honoré.
        foreach (['État civil' => 2, 'Urbanisme' => 4] as $nom => $dansJours) {
            $creneau = isset($services[$nom])
                ? CreneauRendezVous::query()->whereBelongsTo($services[$nom])->libres()->where('debut', '>', $maintenant->addDays($dansJours))->orderBy('debut')->first()
                : null;

            if ($creneau !== null) {
                $this->reserver($demo, $creneau, 'confirme', $nom === 'État civil' ? 'Demande de copie intégrale d’acte de naissance.' : 'Projet d’extension de la maison.');
            }
        }

        $annule = isset($services['Action sociale (CCAS)'])
            ? CreneauRendezVous::query()->whereBelongsTo($services['Action sociale (CCAS)'])->libres()->where('debut', '>', $maintenant->addDays(3))->orderBy('debut')->first()
            : null;
        if ($annule !== null) {
            $this->reserver($demo, $annule, 'annule', 'Demande d’aide pour les frais de cantine.');
        }

        if (isset($services['État civil'])) {
            $passe = $this->creneauxDuJour($services['État civil'], $aujourdhui->subWeek())->first();
            if ($passe !== null && $passe->rendez_vous_id === null) {
                $this->reserver($demo, $passe, 'honore', 'Retrait du livret de famille.');
            }
        }
    }

    /**
     * Créneaux d'un jour local pour un service (créés s'ils manquent, y compris dans le passé).
     *
     * @return Collection<int, CreneauRendezVous>
     */
    private function creneauxDuJour(Service $service, CarbonImmutable $jour): Collection
    {
        $duree = (int) $service->duree_rendez_vous;
        $creneaux = collect();

        /** @var array<int, array{0: string, 1: string}> $plages */
        $plages = config('rendez_vous.plages', []);

        foreach ($plages as [$ouverture, $fermeture]) {
            $debut = CarbonImmutable::parse($jour->format('Y-m-d').' '.$ouverture, $jour->getTimezone());
            $limite = CarbonImmutable::parse($jour->format('Y-m-d').' '.$fermeture, $jour->getTimezone());

            while ($debut->addMinutes($duree)->lessThanOrEqualTo($limite)) {
                $creneau = CreneauRendezVous::query()->whereBelongsTo($service)->where('debut', $debut->utc())->first();

                if ($creneau === null) {
                    $creneau = new CreneauRendezVous(['debut' => $debut->utc(), 'fin' => $debut->addMinutes($duree)->utc()]);
                    $creneau->service()->associate($service);
                    $creneau->save();
                }

                $creneaux->push($creneau);
                $debut = $debut->addMinutes($duree);
            }
        }

        return $creneaux;
    }

    private function reserver(User $user, CreneauRendezVous $creneau, string $statut, ?string $motif): void
    {
        RendezVous::factory()->for($user)->create([
            'creneau_id' => $creneau->id,
            'service_id' => $creneau->service_id,
            'statut' => $statut,
            'motif' => $motif,
            'annule_le' => $statut === 'annule' ? now() : null,
        ]);
    }
}
