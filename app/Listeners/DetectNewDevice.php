<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\DeviceRecognizer;
use Illuminate\Auth\Events\Login;

/**
 * F54 : à chaque connexion réussie (mot de passe, double authentification, lien D02, « se souvenir de moi »),
 * compare l'appareil aux appareils connus et alerte s'il est nouveau. Les comptes désactivés (F34) et les
 * connexions bloquées (F37) n'arrivent jamais jusqu'ici : l'événement Login n'est émis qu'après ces contrôles.
 */
class DetectNewDevice
{
    public function __construct(private DeviceRecognizer $recognizer) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $this->recognizer->handleLogin($event->user, request());
    }
}
