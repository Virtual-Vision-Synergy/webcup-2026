<?php

namespace Database\Seeders;

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Démo du rappel avant rendez-vous (F40), hors production. Idempotent.
 *
 * Pour user@example.com : un rendez-vous État civil dans ~24 h 10 min, que appointments:send-reminders rappelle
 * tout seul une dizaine de minutes plus tard, et un rendez-vous annulé dans ~20 h, qui ne reçoit rien.
 */
class RappelRendezVousSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $demo = User::query()->where('email', 'user@example.com')->first();
        $service = Service::query()->prendRendezVous()->where('nom', 'État civil')->first();

        if ($demo === null || $service === null) {
            return;
        }

        $heures = (int) config('rendez_vous.rappel_heures_avant', 24);

        $dejaPrevu = RendezVous::query()
            ->whereBelongsTo($demo)
            ->where('statut', 'confirme')
            ->whereNull('reminder_sent_at')
            ->whereHas('creneau', fn ($q) => $q->where('debut', '>', now()->addHours($heures))->where('debut', '<=', now()->addHours($heures)->addMinutes(30)))
            ->exists();

        if (! $dejaPrevu) {
            $this->reserver($demo, $service, now()->addHours($heures)->addMinutes(10), 'confirme', 'Demande de copie intégrale d’acte de naissance.');
        }

        $dejaAnnule = RendezVous::query()
            ->whereBelongsTo($demo)
            ->where('statut', 'annule')
            ->whereHas('creneau', fn ($q) => $q->whereBetween('debut', [now(), now()->addHours($heures)]))
            ->exists();

        if (! $dejaAnnule) {
            $this->reserver($demo, $service, now()->addHours(max(1, $heures - 4)), 'annule', 'Reconnaissance anticipée d’un enfant.');
        }
    }

    /**
     * Crée un créneau dédié (arrondi aux 5 minutes) et le rendez-vous qui l'occupe.
     */
    private function reserver(User $user, Service $service, CarbonImmutable $debut, string $statut, string $motif): void
    {
        $debut = $debut->utc()->ceilMinutes(5)->startOfMinute();
        $duree = (int) $service->duree_rendez_vous;

        $creneau = CreneauRendezVous::query()->whereBelongsTo($service)->where('debut', $debut)->first();
        if ($creneau === null) {
            $creneau = new CreneauRendezVous(['debut' => $debut, 'fin' => $debut->addMinutes($duree)]);
            $creneau->service()->associate($service);
            $creneau->save();
        }

        if ($creneau->rendez_vous_id !== null) {
            return;
        }

        // La factory relie le créneau au rendez-vous confirmé.
        RendezVous::factory()->for($user)->create([
            'creneau_id' => $creneau->id,
            'service_id' => $service->id,
            'statut' => $statut,
            'motif' => $motif,
            'annule_le' => $statut === 'annule' ? now() : null,
        ]);
    }
}
