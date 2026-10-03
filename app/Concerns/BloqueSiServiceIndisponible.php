<?php

namespace App\Concerns;

use App\Models\Service;
use App\Models\ServiceInterruption;

/**
 * F38 : à l'ouverture d'une démarche liée à un service (mount d'un composant Livewire),
 * renvoie l'habitant vers la fiche du service s'il est interrompu, avec un message clair.
 *
 *   if ($this->redirigerSiServiceIndisponible($service)) { return; }
 *
 * Les actions d'envoi appellent en plus $service->assertDisponible('champ') (le statut a pu changer entre-temps).
 */
trait BloqueSiServiceIndisponible
{
    protected function redirigerSiServiceIndisponible(?Service $service): bool
    {
        if ($service === null || ! $service->estIndisponible()) {
            return false;
        }

        session()->flash('service-indisponible', ServiceInterruption::MESSAGE_DEMARCHE_SUSPENDUE);

        $this->redirectRoute('services.show', $service, navigate: true);

        return true;
    }
}
