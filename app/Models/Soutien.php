<?php

namespace App\Models;

use Database\Factories\SoutienFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soutien d'un habitant à un signalement déposé par un autre (F52).
 * Un seul soutien par habitant et par signalement (contrainte unique en base).
 *
 * Aucun champ remplissable : user_id et signalement_id sont assignés dans le code
 * (Signalement::ajouterSoutien()).
 *
 * @property int $id
 * @property int $user_id
 * @property int $signalement_id
 */
class Soutien extends Model
{
    /** @use HasFactory<SoutienFactory> */
    use HasFactory;

    protected $table = 'soutiens';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Signalement, $this>
     */
    public function signalement(): BelongsTo
    {
        return $this->belongsTo(Signalement::class);
    }
}
