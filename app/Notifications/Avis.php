<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification générique (application + e-mail), sans lien avec un sujet.
 *
 * Exemple : $user->notify(new Avis('Incident validé', ['Votre incident a été publié.'], 'Voir', route('incidents.show', $s)));
 */
class Avis extends Notification implements ShouldQueue
{
    use Queueable;

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

    /**
     * F78 : la cloche est enregistrée tout de suite ; l'e-mail (lent, sendmail) part par la file d'attente.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
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
