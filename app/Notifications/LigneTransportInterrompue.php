<?php

namespace App\Notifications;

use App\Models\InterruptionTransport;
use App\Models\LigneTransport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * F97 : la ligne habituelle de l'habitant est interrompue : cloche toujours, e-mail s'il l'a accepté dans son profil.
 * Les clés sujet / lignes / libelle / url sont celles affichées par la cloche (comme Avis).
 */
class LigneTransportInterrompue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InterruptionTransport $interruption, public LigneTransport $ligne) {}

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
        return 'Ligne '.$this->ligne->numero.' interrompue '.$this->interruption->libelleFin().' : voici comment faire';
    }

    /**
     * @return list<string>
     */
    public function lignes(): array
    {
        $lignes = [Str::limit(Str::squish($this->interruption->cause), 160)];

        $solution = $this->interruption->solutionsAffichables()[0] ?? null;

        if ($solution !== null) {
            $lignes[] = 'Solution conseillée : '.$solution['titre'].'.';
        }

        return $lignes;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('[Transports] Ligne '.$this->ligne->numero.' interrompue')
            ->greeting('Votre ligne '.$this->ligne->numero.' est interrompue');

        foreach ($this->lignes() as $ligne) {
            $message->line($ligne);
        }

        return $message
            ->action('Voir les solutions de remplacement', route('transports.show', $this->ligne))
            ->line('Vous recevez ce message car cette ligne fait partie de vos trajets habituels.');
    }

    /**
     * @return array{sujet: string, lignes: list<string>, libelle: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sujet' => $this->sujet(),
            'lignes' => $this->lignes(),
            'libelle' => 'Voir comment faire',
            'url' => route('transports.show', $this->ligne),
        ];
    }
}
