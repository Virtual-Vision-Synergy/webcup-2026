<?php

namespace App\Models;

use Database\Factories\GenTestFicheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['titre', 'description', 'niveau', 'gen_test_zone_id'])]
class GenTestFiche extends Model
{
    /** @use HasFactory<GenTestFicheFactory> */
    use HasFactory;

    public const NIVEAU_OPTIONS = ['faible', 'moyen', 'critique'];

    public const STATUT_OPTIONS = ['en_attente', 'valide', 'refuse'];

    /** Couleurs Flux des badges. */
    public const STATUT_COLORS = ['en_attente' => 'amber', 'valide' => 'green', 'refuse' => 'red'];

    /**
     * Valeur par défaut du statut : la première de STATUT_OPTIONS.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0]];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GenTestZone, $this>
     */
    public function genTestZone(): BelongsTo
    {
        return $this->belongsTo(GenTestZone::class);
    }

    public static function libelleStatut(string $statut): string
    {
        return ucfirst(str_replace('_', ' ', $statut));
    }

    public function couleurStatut(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'zinc';
    }

    /**
     * Seul point de passage pour modifier le statut (réservé à l'admin : policy changerStatut).
     */
    public function changerStatut(string $statut): void
    {
        if (! in_array($statut, self::STATUT_OPTIONS, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$statut}");
        }

        $this->statut = $statut;
        $this->save();
    }
}
