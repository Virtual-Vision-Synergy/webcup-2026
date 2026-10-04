<?php

namespace App\Notifications;

use App\Models\Demarche;
use App\Models\ReponseDemarche;
use Illuminate\Support\Str;

/**
 * F84 : prévient l'habitant qu'un agent a répondu à sa démarche (cloche + e-mail).
 * Étend Avis : mêmes clés sujet / lignes / libelle / url, plus demarche_id pour rouvrir la fiche
 * (le lien est reconstruit depuis l'identifiant à l'ouverture, jamais l'URL stockée).
 */
class ReponseDemarcheRecue extends Avis
{
    public int $demarcheId;

    public function __construct(Demarche $demarche, ReponseDemarche $reponse)
    {
        $this->demarcheId = (int) $demarche->getKey();

        parent::__construct(
            __('La mairie a répondu à votre demande « :demande »', ['demande' => (string) $demarche->titre]),
            [
                __('Un agent municipal vous a écrit :'),
                '« '.Str::limit($reponse->message, 300).' »',
                __('Vous pouvez lui répondre directement depuis votre demande.'),
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
