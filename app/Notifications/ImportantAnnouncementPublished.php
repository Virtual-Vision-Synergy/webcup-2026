<?php

namespace App\Notifications;

use App\Models\Annonce;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * F30 : annonce importante publiée (cloche) et, pour le niveau le plus grave, e-mail.
 * Les clés sujet / lignes / libelle / url sont celles affichées par la cloche (comme Avis).
 */
class ImportantAnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Annonce $annonce) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database'];

        $niveauxEmail = (array) config($this->annonce->estCiblee() ? 'annonces.niveaux_email_quartier' : 'annonces.niveaux_email', []);

        if (in_array($this->annonce->niveau, $niveauxEmail, true)
            && $notifiable instanceof User
            && $notifiable->notifier_par_email
            && $notifiable->isActive()) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /**
     * Ce qu'il faut savoir tout de suite : « Danger — Montée des eaux, quartier Sud : consultez les consignes ».
     */
    public function sujet(): string
    {
        $sujet = $this->annonce->libelleNiveau().' — '.$this->annonce->titre;

        if ($this->annonce->nomQuartier() !== null) {
            $sujet .= ', quartier '.$this->annonce->nomQuartier();
        }

        return $sujet.' : '.($this->annonce->listeConsignes() !== [] ? 'consultez les consignes' : 'consultez l’annonce');
    }

    public function extrait(): string
    {
        return Str::limit(Str::squish($this->annonce->contenu), 140);
    }

    /**
     * F101 : « Depuis samedi 4 octobre à 14 h 30 · fin estimée samedi 4 octobre à 20 h 30 » (heure de Madagascar).
     * F104 : « Perturbation estimée à partir de … · fin de l’alerte … » quand un début de perturbation est annoncé.
     */
    public function periode(): string
    {
        if ($this->annonce->impact_prevu_le !== null) {
            return 'Perturbation estimée à partir de '.Annonce::heureLisible($this->annonce->impact_prevu_le).' · fin de l’alerte '.Annonce::heureLisible($this->annonce->fin);
        }

        return 'Depuis '.Annonce::heureLisible($this->annonce->debut).' · fin estimée '.Annonce::heureLisible($this->annonce->fin);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('['.$this->annonce->libelleNiveau().'] '.$this->annonce->titre)
            ->greeting('Annonce importante de la ville')
            ->line('Niveau : '.$this->annonce->libelleNiveau())
            ->line('Concerne : '.($this->annonce->nomQuartier() !== null ? 'quartier '.$this->annonce->nomQuartier() : 'toute la ville'))
            ->line($this->periode().' (heure de Madagascar).')
            ->line($this->extrait());

        $consignes = $this->annonce->listeConsignes();
        if ($consignes !== []) {
            $message->line('Consignes à suivre :');
            foreach ($consignes as $consigne) {
                $message->line('• '.$consigne);
            }
        }

        return $message
            ->action('Voir l’annonce', route('alertes.show', $this->annonce))
            ->line('Vous pouvez désactiver ces e-mails dans votre profil.')
            ->salutation('Le Haut Conseil de la Ville');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $consignes = $this->annonce->listeConsignes();

        return [
            'type' => 'annonce_importante',
            'sujet' => $this->sujet(),
            'lignes' => array_values(array_filter([
                $this->periode(),
                $this->extrait(),
                $consignes !== [] ? 'Consigne : '.$consignes[0] : null,
            ])),
            'libelle' => 'Voir l’annonce',
            // Ouvrir la notification la marque comme lue puis redirige vers l'annonce.
            'url' => route('notifications.open', $this->id, false),
            'annonce_id' => $this->annonce->id,
            'titre' => $this->annonce->titre,
            'niveau' => $this->annonce->niveau,
            'niveau_libelle' => $this->annonce->libelleNiveau(),
            'quartier' => $this->annonce->nomQuartier(),
        ];
    }
}
