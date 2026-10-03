<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\Soutenable;
use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Problème signalé par un citoyen dans l'espace public (lampadaire, voirie, propreté…).
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir SignalementPolicy.
 *
 * user_id, statut et doublon_de_id ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 *
 * @property int|null $doublon_de_id
 */
#[Fillable(['categorie', 'description', 'lieu', 'photo'])]
class Signalement extends Model
{
    /** @use HasFactory<SignalementFactory> */
    use Auditable, HasAuditHistory, HasFactory, Soutenable;

    public const CATEGORIE_OPTIONS = ['eclairage', 'voirie', 'proprete', 'eau', 'espaces_verts', 'mobilier', 'autre'];

    /** Libellés affichés (avec accents). */
    public const CATEGORIE_LABELS = [
        'eclairage' => 'Éclairage public',
        'voirie' => 'Voirie',
        'proprete' => 'Propreté',
        'eau' => 'Eau et assainissement',
        'espaces_verts' => 'Espaces verts',
        'mobilier' => 'Mobilier urbain',
        'autre' => 'Autre',
    ];

    public const STATUT_OPTIONS = ['nouveau', 'en_cours', 'resolu', 'rejete'];

    /** États où la demande est encore ouverte : on peut la soutenir (F52). */
    public const STATUTS_OUVERTS = ['nouveau', 'en_cours'];

    /** État affiché par le badge Terra Nova (la couleur indique un état : normal, perturbé, alerte, info). */
    public const STATUT_ETATS = ['nouveau' => 'perturbe', 'en_cours' => 'info', 'resolu' => 'normal', 'rejete' => 'alerte'];

    /** Libellés affichés (avec accents). */
    public const STATUT_LABELS = ['nouveau' => 'Nouveau', 'en_cours' => 'En cours', 'resolu' => 'Résolu', 'rejete' => 'Rejeté'];

    /**
     * Valeur par défaut du statut : la première de STATUT_OPTIONS (« Nouveau »).
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
     * Soutiens d'autres habitants (F52).
     *
     * @return HasMany<Soutien, $this>
     */
    public function soutiens(): HasMany
    {
        return $this->hasMany(Soutien::class);
    }

    /**
     * Demande principale dans laquelle ce signalement a été fusionné (F75).
     *
     * @return BelongsTo<Signalement, $this>
     */
    public function doublonDe(): BelongsTo
    {
        return $this->belongsTo(Signalement::class, 'doublon_de_id');
    }

    /**
     * Signalements similaires fusionnés dans celui-ci (F75).
     *
     * @return HasMany<Signalement, $this>
     */
    public function doublons(): HasMany
    {
        return $this->hasMany(Signalement::class, 'doublon_de_id');
    }

    /**
     * Rattache ce signalement à la demande principale de son groupe : il en prend l'état
     * et sort des regroupements (réservé au personnel : policy changerStatut).
     */
    public function fusionnerDans(Signalement $principal): void
    {
        if ($principal->is($this)) {
            return;
        }

        $this->doublon_de_id = $principal->id;
        $this->statut = $principal->statut;
        $this->save();
    }

    public function estOuvert(): bool
    {
        return in_array($this->statut, self::STATUTS_OUVERTS, true);
    }

    /**
     * Soutien non enregistré rattaché à ce signalement : user_id est assigné par le trait Soutenable
     * (estSoutenuPar, ajouterSoutien, retirerSoutien), jamais depuis le navigateur.
     */
    protected function nouveauSoutien(): Soutien
    {
        $soutien = new Soutien;
        $soutien->signalement()->associate($this);

        return $soutien;
    }

    /**
     * Nom lisible dans le journal d'audit (F47) : « Éclairage — Rue des Lumières ».
     */
    public function auditLabel(): string
    {
        return self::libelleCategorie((string) $this->categorie).' — '.$this->lieu;
    }

    public static function libelleCategorie(string $categorie): string
    {
        return self::CATEGORIE_LABELS[$categorie] ?? ucfirst(str_replace('_', ' ', $categorie));
    }

    public static function libelleStatut(string $statut): string
    {
        return __(self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut)));
    }

    public function etatStatut(): string
    {
        return self::STATUT_ETATS[$this->statut] ?? 'info';
    }

    /**
     * Seul point de passage pour modifier le statut (réservé aux agents et admins : policy changerStatut).
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
