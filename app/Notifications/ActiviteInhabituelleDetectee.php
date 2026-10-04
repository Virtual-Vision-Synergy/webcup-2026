<?php

namespace App\Notifications;

use App\Models\SecurityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * F85 : prévient l'utilisateur dans la cloche qu'une activité inhabituelle a été détectée sur son compte
 * et l'invite, si ce n'était pas lui, à déconnecter ses autres appareils (page F54).
 * Les clés sujet / lignes / libelle / url sont celles de la cloche (F30).
 */
class ActiviteInhabituelleDetectee extends Notification
{
    use Queueable;

    public const SUJET = 'Activité inhabituelle détectée sur votre compte';

    public function __construct(public SecurityEvent $event) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{sujet: string, lignes: array<int, string>, libelle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => self::SUJET,
            'lignes' => [
                $this->event->libelleType().' : '.$this->event->description,
                'Ce n’était pas vous ? Déconnectez vos autres appareils puis changez votre mot de passe. Sinon, vous n’avez rien à faire.',
            ],
            'libelle' => 'Voir mes appareils',
            'url' => route('profile.devices.index'),
        ];
    }
}
