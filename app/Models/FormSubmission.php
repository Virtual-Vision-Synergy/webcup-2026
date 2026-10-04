<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * F82 : envoi de formulaire déjà traité. Sert à refuser un second envoi identique (double clic, retour arrière,
 * rafraîchissement) : même jeton, ou même contenu du même habitant dans la fenêtre `security.doublons`.
 *
 * Aucun champ n'est remplissable : les lignes sont créées uniquement par App\Services\EnvoisUniques
 * (affectation explicite côté serveur). Le contenu saisi n'est pas conservé, seulement son empreinte.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $form_key
 * @property string $token
 * @property string $content_hash
 * @property string|null $submittable_type
 * @property int|null $submittable_id
 * @property CarbonInterface|null $created_at
 * @property-read User|null $user
 * @property-read Model|null $submittable
 */
class FormSubmission extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const FORM_KEY_OPTIONS = ['demarche', 'signalement', 'contact', 'avis'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function submittable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * « 4 octobre 2026 à 9 h 32 », dans le fuseau de la ville.
     */
    public function envoyeLe(): string
    {
        return $this->created_at === null
            ? '—'
            : $this->created_at->copy()->timezone(Annonce::FUSEAU)->locale('fr')->translatedFormat('j F Y \à G \h i');
    }

    /**
     * Inutile au-delà d'une journée (la fenêtre de doublon est de quelques minutes) : `php artisan model:prune`.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
