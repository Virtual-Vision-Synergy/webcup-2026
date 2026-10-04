<?php

namespace App\Models;

use Database\Factories\GenTestZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 */
#[Fillable(['nom'])]
class GenTestZone extends Model
{
    /** @use HasFactory<GenTestZoneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
