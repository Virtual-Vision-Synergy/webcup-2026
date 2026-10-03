<?php

namespace App\Services;

use App\Models\RendezVous;
use App\Notifications\RappelRendezVous;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * F40 : rappels envoyés avant les rendez-vous confirmés (commande planifiée appointments:send-reminders).
 *
 * - Fenêtre : le rendez-vous commence entre maintenant et maintenant + rappel_heures_avant ; un rendez-vous
 *   réservé moins de 24 h à l'avance est donc rappelé au passage suivant.
 * - Annulé, passé ou compte désactivé : aucun rappel.
 * - reminder_sent_at est réservé de façon atomique : jamais deux rappels, même si deux exécutions se chevauchent.
 */
class RappelsRendezVous
{
    /**
     * @return Builder<RendezVous>
     */
    public function aRappeler(): Builder
    {
        $maintenant = now();
        $limite = $maintenant->copy()->addHours((int) config('rendez_vous.rappel_heures_avant', 24));

        return RendezVous::query()
            ->where('statut', 'confirme')
            ->whereNull('reminder_sent_at')
            ->whereHas('creneau', fn (Builder $q) => $q->where('debut', '>', $maintenant)->where('debut', '<=', $limite))
            ->whereHas('user', fn (Builder $q) => $q->whereNull('deactivated_at'))
            ->with(['user', 'service', 'creneau']);
    }

    /**
     * @return array{envoyes: int, erreurs: int}
     */
    public function envoyerTous(): array
    {
        $resultat = ['envoyes' => 0, 'erreurs' => 0];

        foreach ($this->aRappeler()->get() as $rendezVous) {
            $statut = $this->envoyer($rendezVous);

            if ($statut !== null) {
                $resultat[$statut]++;
            }
        }

        return $resultat;
    }

    /**
     * @return 'envoyes'|'erreurs'|null null si le rappel a déjà été pris par une autre exécution
     */
    public function envoyer(RendezVous $rendezVous): ?string
    {
        $reserve = RendezVous::query()
            ->whereKey($rendezVous->id)
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        if ($reserve !== 1) {
            return null;
        }

        try {
            $rendezVous->user->notify(new RappelRendezVous($rendezVous));
        } catch (Throwable $e) {
            // Envoi raté (mailer indisponible…) : on libère le rappel, il sera retenté au passage suivant.
            RendezVous::query()->whereKey($rendezVous->id)->update(['reminder_sent_at' => null]);
            report($e);

            return 'erreurs';
        }

        return 'envoyes';
    }
}
