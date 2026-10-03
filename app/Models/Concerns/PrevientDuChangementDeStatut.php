<?php

namespace App\Models\Concerns;

use App\Models\Demarche;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\StatutDemandeChange;
use Illuminate\Support\Facades\DB;

/**
 * F49 : après un changement d'état réel, prévient le seul propriétaire de la demande (cloche + e-mail).
 * Utilisé uniquement par Demarche::changerStatut() et Signalement::changerStatut().
 *
 * @mixin Demarche|Signalement
 */
trait PrevientDuChangementDeStatut
{
    protected function prevenirProprietaire(string $statutAvant): void
    {
        if (! $this->wasChanged('statut')) {
            return;
        }

        // Rechargé en entier : un user chargé partiellement (with('user:id,name')) n'aurait pas d'e-mail.
        $proprietaire = User::query()->find($this->user_id);

        if ($proprietaire === null) {
            return;
        }

        $avis = new StatutDemandeChange($this, $statutAvant, (string) $this->statut);

        // Envoyé seulement si la transaction en cours aboutit (immédiatement s'il n'y en a pas).
        DB::afterCommit(function () use ($proprietaire, $avis): void {
            // La cloche d'abord : elle reste enregistrée même si l'e-mail échoue.
            $proprietaire->notifyNow($avis, ['database']);

            try {
                $proprietaire->notifyNow($avis, ['mail']);
            } catch (\Throwable $e) {
                // Un échec d'envoi (sendmail synchrone en production) ne doit jamais bloquer l'agent.
                report($e);
            }
        });
    }
}
