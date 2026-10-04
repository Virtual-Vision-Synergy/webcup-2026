<?php

namespace App\Notifications;

use App\Models\AlerteCanicule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * F31 : alerte canicule pour le quartier de l'habitant : cloche toujours, e-mail s'il l'a accepté dans son profil.
 * Les clés sujet / lignes / libelle / url sont celles affichées par la cloche (comme Avis).
 */
class AlerteCaniculePubliee extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AlerteCanicule $alerte) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if ($notifiable instanceof User && $notifiable->notifier_par_email && $notifiable->isActive()) {
            $canaux[] = 'mail';
        }

        return $canaux;
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

    public function sujet(): string
    {
        return 'Canicule — '.$this->alerte->libelleNiveau().', quartier '.$this->alerte->libelleQuartiers().' : consultez les conseils adaptés';
    }

    /**
     * @return list<string>
     */
    public function lignes(): array
    {
        $lignes = [Str::limit(Str::squish($this->alerte->message), 160)];

        if ($this->alerte->temperature_max !== null) {
            $lignes[] = 'Jusqu’à '.$this->alerte->temperature_max.' °C attendus.';
        }

        return $lignes;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('[Canicule — '.$this->alerte->libelleNiveau().'] Quartier '.$this->alerte->libelleQuartiers())
            ->greeting('Alerte canicule de l’Agence sanitaire');

        foreach ($this->lignes() as $ligne) {
            $message->line($ligne);
        }

        return $message
            ->action('Voir les conseils pour moi et mes proches', route('canicule'))
            ->line('Urgence médicale : appelez le 124 (SAMU).')
            ->line('Vous pouvez désactiver ces e-mails dans votre profil.');
    }

    /**
     * @return array{sujet: string, lignes: list<string>, libelle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => $this->sujet(),
            'lignes' => $this->lignes(),
            'libelle' => 'Voir les conseils',
            'url' => route('canicule'),
        ];
    }
}
