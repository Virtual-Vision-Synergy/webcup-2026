<?php

namespace App\Models;

use App\Models\Concerns\HasCoordinates;
use Database\Factories\ProjetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Projet en cours dans la ville (F67) : consultable par tous, géré par les agents et les admins.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $quartier_id
 * @property-read Quartier|null $quartier
 * @property string $titre
 * @property string $categorie
 * @property string $resume
 * @property string $description
 * @property string $etat
 * @property string|null $etapes Une étape par ligne, dans l'ordre.
 * @property int $etapes_terminees Nombre d'étapes déjà franchies (les premières de la liste).
 * @property int $avancement Pourcentage de 0 à 100.
 * @property Carbon|null $date_debut
 * @property Carbon|null $date_fin
 * @property int|null $budget En ariary.
 * @property string|null $lieu
 * @property string|null $latitude
 * @property string|null $longitude
 *
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code (agent auteur).
 */
#[Fillable(['titre', 'categorie', 'quartier_id', 'resume', 'description', 'etat', 'etapes', 'etapes_terminees', 'avancement', 'date_debut', 'date_fin', 'budget', 'lieu', 'latitude', 'longitude'])]
class Projet extends Model
{
    /** @use HasFactory<ProjetFactory> */
    use HasCoordinates, HasFactory;

    public const ETAT_OPTIONS = ['etude', 'en_cours', 'termine'];

    public const ETAT_LABELS = [
        'etude' => 'À l\'étude',
        'en_cours' => 'En cours',
        'termine' => 'Terminé',
    ];

    public const CATEGORIE_OPTIONS = ['voirie', 'ecole', 'parc', 'eau', 'energie', 'autre'];

    public const CATEGORIE_LABELS = [
        'voirie' => 'Voirie',
        'ecole' => 'École',
        'parc' => 'Parc et espaces verts',
        'eau' => 'Réseau d\'eau',
        'energie' => 'Énergie',
        'autre' => 'Autre',
    ];

    public const CATEGORIE_ICONES = [
        'voirie' => 'truck',
        'ecole' => 'academic-cap',
        'parc' => 'sparkles',
        'eau' => 'beaker',
        'energie' => 'bolt',
        'autre' => 'building-office-2',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quartier_id' => 'integer',
            'etapes_terminees' => 'integer',
            'avancement' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'budget' => 'integer',
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

    /**
     * Étapes dans l'ordre.
     *
     * @return array<int, string>
     */
    public function listeEtapes(): array
    {
        return collect(preg_split('/\R/', (string) $this->etapes) ?: [])
            ->map(fn (string $etape) => trim($etape))
            ->filter()
            ->values()
            ->all();
    }

    public function etatLabel(): string
    {
        return __(self::ETAT_LABELS[$this->etat] ?? (string) $this->etat);
    }

    public function categorieLabel(): string
    {
        return __(self::CATEGORIE_LABELS[$this->categorie] ?? ucfirst((string) $this->categorie));
    }

    public function categorieIcone(): string
    {
        return self::CATEGORIE_ICONES[$this->categorie] ?? 'building-office-2';
    }

    /**
     * Valeur attendue par <x-tn.status-badge>.
     */
    public function etatBadge(): string
    {
        return match ($this->etat) {
            'termine' => 'normal',
            'en_cours' => 'perturbe',
            default => 'info',
        };
    }

    public function nomQuartier(): string
    {
        return $this->quartier?->nom ?? __('Toute la ville');
    }

    public function budgetFormate(): ?string
    {
        return $this->budget === null ? null : number_format($this->budget, 0, ',', ' ').' Ar';
    }

    public function periode(): ?string
    {
        $debut = $this->date_debut?->translatedFormat('M Y');
        $fin = $this->date_fin?->translatedFormat('M Y');

        return match (true) {
            $debut !== null && $fin !== null => $debut.' → '.$fin,
            $debut !== null => __('Depuis :date', ['date' => $debut]),
            $fin !== null => __('Fin prévue :date', ['date' => $fin]),
            default => null,
        };
    }
}
