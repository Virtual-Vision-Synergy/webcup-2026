<?php

namespace App\Models;

use App\Models\Concerns\HasCoordinates;
use Database\Factories\ProjetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projet de la ville (F67) : consultable par tous, créé et mis à jour par les agents.
 *
 * user_id (agent qui a publié le projet) n'est volontairement PAS remplissable : il est assigné dans le code.
 * Les étapes sont saisies une par ligne ; etapes_terminees = nombre d'étapes déjà réalisées, dans l'ordre.
 *
 * @property int $id
 * @property string $titre
 * @property string $description
 * @property string $etat
 * @property string|null $etapes
 * @property int $etapes_terminees
 */
#[Fillable(['titre', 'description', 'etat', 'quartier_id', 'date_debut', 'date_fin', 'budget', 'etapes', 'etapes_terminees', 'latitude', 'longitude'])]
class Projet extends Model
{
    /** @use HasFactory<ProjetFactory> */
    use HasCoordinates, HasFactory;

    public const ETAT_OPTIONS = ['a_l_etude', 'en_cours', 'termine'];

    public const ETAT_LABELS = [
        'a_l_etude' => 'À l’étude',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
    ];

    /** État du badge <x-tn.status-badge> pour chaque état du projet. */
    public const ETAT_BADGES = [
        'a_l_etude' => 'info',
        'en_cours' => 'perturbe',
        'termine' => 'normal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'budget' => 'decimal:2',
            'etapes_terminees' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }

    public static function libelleEtat(string $etat): string
    {
        return self::ETAT_LABELS[$etat] ?? $etat;
    }

    public function badgeEtat(): string
    {
        return self::ETAT_BADGES[$this->etat] ?? 'info';
    }

    /**
     * Étapes du projet, une par ligne saisie (lignes vides ignorées).
     *
     * @return array<int, string>
     */
    public function listeEtapes(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $this->etapes) ?: []),
            fn (string $etape): bool => $etape !== '',
        ));
    }

    /**
     * Pourcentage d'avancement : 100 % si terminé, sinon étapes réalisées / étapes prévues.
     */
    public function avancement(): int
    {
        if ($this->etat === 'termine') {
            return 100;
        }

        $total = count($this->listeEtapes());

        if ($total === 0) {
            return 0;
        }

        return (int) round(min($this->etapes_terminees, $total) / $total * 100);
    }

    public function budgetFormate(): ?string
    {
        if ($this->budget === null) {
            return null;
        }

        return number_format((float) $this->budget, 0, ',', ' ').' Ar';
    }
}
