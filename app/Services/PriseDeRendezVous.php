<?php

namespace App\Services;

use App\Exceptions\CreneauIndisponible;
use App\Models\ActionLog;
use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Réservation et annulation des rendez-vous (F39). Les droits sont vérifiés par l'appelant (RendezVousPolicy).
 *
 * Pas de double réservation : verrou sur le créneau (lockForUpdate), mise à jour conditionnelle
 * « rendez_vous_id IS NULL » dont on vérifie le nombre de lignes, et index unique en dernier rempart.
 */
class PriseDeRendezVous
{
    /**
     * @throws CreneauIndisponible
     */
    public function reserver(User $user, Service $service, int $creneauId, ?string $motif = null): RendezVous
    {
        // F63 : service désactivé par un administrateur (relu en base, l'objet peut dater de l'affichage).
        if (Service::query()->whereKey($service->id)->whereNotNull('indisponible_depuis')->exists()) {
            throw CreneauIndisponible::serviceIndisponible();
        }

        try {
            $rendezVous = DB::transaction(function () use ($user, $service, $creneauId, $motif): RendezVous {
                $creneau = CreneauRendezVous::query()->lockForUpdate()->find($creneauId);

                if ($creneau === null || $creneau->service_id !== $service->id || ! $creneau->estReservable()) {
                    throw CreneauIndisponible::invalide();
                }

                if (! $creneau->estLibre()) {
                    throw CreneauIndisponible::dejaPris();
                }

                $chevauche = RendezVous::query()
                    ->whereBelongsTo($user)
                    ->where('statut', 'confirme')
                    ->whereHas('creneau', fn (Builder $q) => $q->where('debut', '<', $creneau->fin)->where('fin', '>', $creneau->debut))
                    ->exists();

                if ($chevauche) {
                    throw CreneauIndisponible::chevauchement();
                }

                $rendezVous = new RendezVous(['motif' => $motif === null || trim($motif) === '' ? null : trim($motif)]);
                $rendezVous->user()->associate($user);
                $rendezVous->service()->associate($service);
                $rendezVous->creneau()->associate($creneau);
                $rendezVous->statut = 'confirme';
                $rendezVous->save();

                $pris = CreneauRendezVous::query()
                    ->whereKey($creneau->id)
                    ->whereNull('rendez_vous_id')
                    ->update(['rendez_vous_id' => $rendezVous->id]);

                if ($pris !== 1) {
                    throw CreneauIndisponible::dejaPris();
                }

                return $rendezVous;
            });
        } catch (UniqueConstraintViolationException) {
            throw CreneauIndisponible::dejaPris();
        }

        ActionLog::record('rendez_vous_reserve', $rendezVous);

        return $rendezVous;
    }

    /**
     * Annule le rendez-vous et libère son créneau, qui redevient proposé.
     */
    public function annuler(RendezVous $rendezVous): void
    {
        DB::transaction(function () use ($rendezVous): void {
            $rendezVous->statut = 'annule';
            $rendezVous->annule_le = now();
            $rendezVous->save();

            CreneauRendezVous::query()
                ->whereKey($rendezVous->creneau_id)
                ->where('rendez_vous_id', $rendezVous->id)
                ->update(['rendez_vous_id' => null]);
        });

        ActionLog::record('rendez_vous_annule', $rendezVous);
    }
}
