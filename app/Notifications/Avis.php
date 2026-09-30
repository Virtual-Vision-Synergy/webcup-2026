<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification générique (application + e-mail), sans lien avec un sujet.
 *
 * Exemple : $user->notify(new Avis('Signalement validé', ['Votre signalement a été publié.'], 'Voir', route('signalements.show', $s)));
 */
class Avis extends Notification
{
    /**
     * @param  array<int, string>  $lignes
     */
    public function __construct(
        public string $sujet,
        public array $lignes = [],
        public ?string $libelle = null,
        public ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->sujet);

        foreach ($this->lignes as $ligne) {
            $message->line($ligne);
        }

        if ($this->url !== null) {
            $message->action($this->libelle ?? __('Open'), $this->url);
        }

        return $message;
    }

    /**
     * @return array{sujet: string, lignes: array<int, string>, libelle: string|null, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => $this->sujet,
            'lignes' => $this->lignes,
            'libelle' => $this->libelle,
            'url' => $this->url,
        ];
    }
}
