<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F84 : message du fil d'une démarche, écrit par un agent (réponse de la mairie) ou par l'habitant.
 * demarche_id, user_id et de_agent ne sont volontairement PAS remplissables : ils sont assignés dans le code
 * (Demarche::ajouterReponse), jamais depuis le navigateur.
 *
 * @property int $id
 * @property int $demarche_id
 * @property int|null $user_id
 * @property bool $de_agent
 * @property string $message
 */
#[Fillable(['message'])]
class ReponseDemarche extends Model
{
    protected $table = 'reponses_demarche';

    /** Longueur maximale d'un message. */
    public const MESSAGE_MAX = 2000;

    /**
     * Réponses types proposées aux agents (insérées dans le champ, modifiables avant envoi).
     *
     * @var array<string, array{titre: string, contenu: string}>
     */
    public const MODELES = [
        'accuse' => [
            'titre' => 'Accusé de réception',
            'contenu' => "Bonjour,\n\nNous avons bien reçu votre demande. Elle est en cours d'étude par nos services et nous reviendrons vers vous dès que possible.\n\nCordialement.",
        ],
        'piece' => [
            'titre' => 'Pièce manquante',
            'contenu' => "Bonjour,\n\nPour poursuivre le traitement de votre demande, il nous manque une pièce justificative. Merci de nous préciser ici quand vous pourrez nous la transmettre (au guichet ou en ligne).\n\nCordialement.",
        ],
        'rdv' => [
            'titre' => 'Passage au guichet',
            'contenu' => "Bonjour,\n\nVotre demande nécessite un passage au guichet de la mairie. Vous pouvez prendre rendez-vous depuis la rubrique « Rendez-vous ».\n\nCordialement.",
        ],
        'traitee' => [
            'titre' => 'Demande traitée',
            'contenu' => "Bonjour,\n\nVotre demande a été traitée. Vous pouvez consulter le résultat dans votre suivi.\n\nCordialement.",
        ],
        'precision' => [
            'titre' => 'Demande de précision',
            'contenu' => "Bonjour,\n\nPourriez-vous nous apporter quelques précisions sur votre demande ? Vous pouvez répondre directement sous ce message.\n\nCordialement.",
        ],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['de_agent' => 'boolean'];
    }

    /**
     * @return BelongsTo<Demarche, $this>
     */
    public function demarche(): BelongsTo
    {
        return $this->belongsTo(Demarche::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nom affiché dans le fil : l'agent est nommé (« Nom — Mairie »), l'habitant aussi.
     */
    public function auteurAffiche(): string
    {
        $nom = $this->user?->name;

        if ($this->de_agent) {
            return ($nom ?? 'Agent').' — Mairie';
        }

        return $nom ?? 'Habitant';
    }
}
