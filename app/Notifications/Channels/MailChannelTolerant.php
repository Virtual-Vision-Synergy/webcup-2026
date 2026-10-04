<?php

namespace App\Notifications\Channels;

use App\Support\DependancesExternes;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * F93 : si le serveur d'e-mails est en panne, la page de l'habitant s'affiche quand même (démarche, réponse,
 * avis… sont déjà enregistrés et visibles dans la cloche) : l'erreur est journalisée et un bandeau la signale.
 */
class MailChannelTolerant extends MailChannel
{
    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            return parent::send($notifiable, $notification);
        } catch (Throwable $e) {
            report($e);
            DependancesExternes::signalerPanne(DependancesExternes::EMAIL);

            return null;
        }
    }
}
