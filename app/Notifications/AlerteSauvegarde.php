<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F87 : alerte aux admins quand aucune sauvegarde récente n'existe ou qu'une vérification échoue.
 */
class AlerteSauvegarde extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sujet,
        public string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * La cloche est enregistrée tout de suite ; l'e-mail part par la file d'attente.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Alerte] '.$this->sujet)
            ->greeting('Bonjour,')
            ->line($this->message)
            ->action('Voir les sauvegardes', url('/admin/sauvegardes'))
            ->line('Aucune restauration n’est possible depuis l’application : en cas de besoin, passer par le serveur.');
    }

    /**
     * Même format que la notification générique Avis (affichée par la cloche).
     *
     * @return array{sujet: string, lignes: array<int, string>, libelle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => $this->sujet,
            'lignes' => [$this->message],
            'libelle' => 'Voir les sauvegardes',
            'url' => url('/admin/sauvegardes'),
        ];
    }
}
