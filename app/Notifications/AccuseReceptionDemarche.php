<?php

namespace App\Notifications;

use App\Models\Demarche;

/**
 * F83 : accusé de réception d'une démarche (cloche + e-mail par la file) : référence, date et heure, objet, service.
 * Étend Avis : la cloche est enregistrée tout de suite, l'e-mail part par la file d'attente si elle est configurée.
 * Aucune donnée sensible : ni description, ni coordonnées.
 */
class AccuseReceptionDemarche extends Avis
{
    public int $demarcheId;

    public function __construct(Demarche $demarche)
    {
        $this->demarcheId = (int) $demarche->getKey();

        parent::__construct(
            __('Accusé de réception de votre demande :reference', ['reference' => $demarche->numeroSuivi()]),
            [
                __('La mairie a bien reçu votre demande.'),
                __('Référence : :reference', ['reference' => $demarche->numeroSuivi()]),
                __('Reçue le : :date', ['date' => $demarche->dateReceptionLocale()]),
                __('Objet : :objet', ['objet' => (string) $demarche->titre]),
                __('Service : :service', ['service' => $demarche->service->nom ?? __('Non précisé (la mairie orientera votre demande)')]),
                __('Conservez cette référence : elle permet de retrouver votre demande ou de la citer auprès de la mairie.'),
            ],
            __('Voir ma demande'),
            route('demarches.show', $demarche),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return parent::toArray($notifiable) + ['demarche_id' => $this->demarcheId];
    }
}
