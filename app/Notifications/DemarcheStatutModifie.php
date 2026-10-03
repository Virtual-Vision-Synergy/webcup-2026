<?php

namespace App\Notifications;

use App\Models\Demarche;
use App\Models\DemarcheEtape;

/**
 * F49 : l'état d'une démarche a changé. Cloche + e-mail (comme Avis), envoyé au seul auteur de la démarche
 * par Demarche::changerStatut(). Le message dit quelle demande, le nouvel état et quoi faire ensuite.
 */
class DemarcheStatutModifie extends Avis
{
    public function __construct(public Demarche $demarche, public DemarcheEtape $etape)
    {
        $lignes = [
            'Votre demande « '.$demarche->titre.' » est maintenant : '.Demarche::libelleStatut($etape->statut).'.',
            Demarche::conseilStatut($etape->statut),
        ];

        if ($etape->commentaire !== null) {
            $lignes[] = 'Message de l’agent : '.$etape->commentaire;
        }

        parent::__construct(
            'Votre demande « '.$demarche->titre.' » : '.Demarche::libelleStatut($etape->statut),
            array_values(array_filter($lignes)),
            'Voir le suivi de ma demande',
            route('demarches.show', $demarche),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + [
            'type' => 'demarche_statut',
            'demarche_id' => $this->demarche->id,
            'statut' => $this->etape->statut,
            // Badge de la page « Mes notifications » : le nouvel état en toutes lettres.
            'niveau' => $this->etape->statut === 'refusee' ? 'alerte' : 'info',
            'niveau_libelle' => Demarche::libelleStatut($this->etape->statut),
        ];
    }
}
