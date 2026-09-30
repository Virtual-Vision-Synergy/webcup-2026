<?php

namespace App\Models;

use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 */
#[Fillable(['titre', 'description', 'niveau', 'zone', 'photo', 'date_incident', 'latitude', 'longitude'])]
class Signalement extends Model
{
    /** @use HasFactory<SignalementFactory> */
    use HasFactory;

    public const NIVEAU_OPTIONS = ['faible', 'moyen', 'critique'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_incident' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
