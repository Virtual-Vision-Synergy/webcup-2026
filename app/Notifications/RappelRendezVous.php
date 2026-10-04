<?php

namespace App\Notifications;

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * F40 : rappel envoyé avant le rendez-vous par appointments:send-reminders (cloche + e-mail).
 * Reprend la formulation de F39 (CreneauRendezVous::libelleDate / libelleHoraire) pour lever toute ambiguïté.
 * Les clés sujet / lignes / libelle / url sont celles affichées par la cloche (comme Avis).
 */
class RappelRendezVous extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RendezVous $rendezVous) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        if ($notifiable instanceof User && $notifiable->isActive()) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /**
     * « Rappel : rendez-vous État civil demain, mardi 6 octobre 2026 de 09 h 30 à 10 h 00 (heure de Nova Terra). »
     */
    public function sujet(): string
    {
        $creneau = $this->rendezVous->creneau;
        $quand = $this->jourRelatif();

        return 'Rappel : rendez-vous '.$this->rendezVous->service->nom.' '
            .($quand !== null ? $quand.', ' : 'le ')
            .Str::lcfirst($creneau->libelleDate()).' de '.$creneau->libelleHoraire().'.';
    }

    /**
     * @return array<int, string>
     */
    public function piecesAApporter(): array
    {
        return $this->rendezVous->service->piecesAFournir();
    }

    public function lieu(): string
    {
        return (string) $this->rendezVous->service->lieuRendezVous();
    }

    public function urlFiche(): string
    {
        return route('appointments.show', $this->rendezVous);
    }

    /** Mène au bouton d'annulation de la fiche, qui demande confirmation (jamais d'annulation en un clic). */
    public function urlAnnulation(): string
    {
        return $this->urlFiche().'#annuler';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $creneau = $this->rendezVous->creneau;

        $message = (new MailMessage)
            ->subject('Rappel : votre rendez-vous '.$this->rendezVous->service->nom.' — '.$creneau->libelleDate())
            ->greeting('Rappel de votre rendez-vous')
            ->line($this->sujet())
            ->line('Service : '.$this->rendezVous->service->nom)
            ->line('Date : '.$creneau->libelleDate())
            ->line('Horaire : '.$creneau->libelleHoraire())
            ->line('Lieu : '.$this->lieu());

        if ($this->rendezVous->motif !== null && $this->rendezVous->motif !== '') {
            $message->line('Motif indiqué : '.$this->rendezVous->motif);
        }

        $pieces = $this->piecesAApporter();
        if ($pieces !== []) {
            $message->line('Pièces à apporter :');
            foreach ($pieces as $piece) {
                $message->line('• '.$piece);
            }
        }

        return $message
            ->action('Voir mon rendez-vous', $this->urlFiche())
            ->line('Vous ne pouvez plus venir ? Annulez ce rendez-vous depuis sa fiche (une confirmation vous sera demandée) : '.$this->urlAnnulation())
            ->salutation('Le Haut Conseil de la Ville');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $lignes = ['Lieu : '.$this->lieu()];
        $pieces = $this->piecesAApporter();
        if ($pieces !== []) {
            $lignes[] = 'Pièces à apporter : '.implode(', ', $pieces).'.';
        }

        return [
            'type' => 'rappel_rendez_vous',
            'sujet' => $this->sujet(),
            'lignes' => $lignes,
            'libelle' => 'Voir mon rendez-vous',
            // Ouvrir la notification la marque comme lue puis mène à la fiche du rendez-vous.
            'url' => route('notifications.open', $this->id, false),
            'rendez_vous_id' => $this->rendezVous->id,
        ];
    }

    /**
     * « aujourd'hui » ou « demain » (jour local de Nova Terra), sinon null.
     */
    private function jourRelatif(): ?string
    {
        $fuseau = CreneauRendezVous::fuseau();
        $jour = $this->rendezVous->creneau->debut->setTimezone($fuseau)->startOfDay();
        $aujourdhui = CarbonImmutable::now($fuseau)->startOfDay();

        return match ((int) round($aujourdhui->diffInDays($jour))) {
            0 => 'aujourd’hui',
            1 => 'demain',
            default => null,
        };
    }
}
