<?php

namespace App\Notifications;

use App\Models\LienConnexion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * D02 : e-mail contenant le lien de connexion sans mot de passe (URL signée, 15 minutes, usage unique).
 */
class LienDeConnexion extends Notification
{
    public function __construct(public string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre lien de connexion')
            ->line('Vous avez demandé à vous connecter sans mot de passe.')
            ->action('Me connecter', $this->url)
            ->line('Ce lien est valable '.LienConnexion::DUREE_MINUTES.' minutes et ne fonctionne qu\'une seule fois.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail : personne ne pourra se connecter sans ce lien.');
    }
}
