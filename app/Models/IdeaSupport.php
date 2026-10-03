<?php

namespace App\Models;

use Database\Factories\IdeaSupportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soutien d'un habitant à une idée proposée par un autre (F68, même mécanisme que F52).
 * Un seul soutien par habitant et par idée (contrainte unique en base).
 *
 * Aucun champ remplissable : user_id et idea_id sont assignés dans le code (trait Soutenable).
 *
 * @property int $id
 * @property int $user_id
 * @property int $idea_id
 */
class IdeaSupport extends Model
{
    /** @use HasFactory<IdeaSupportFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Idea, $this>
     */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }
}
