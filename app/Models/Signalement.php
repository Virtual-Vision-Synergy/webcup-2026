<?php

namespace App\Models;

use App\Events\SignalementEtapeAjoutee;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\PrevientDuChangementDeStatut;
use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

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
    use Auditable, HasAuditHistory, HasFactory, PrevientDuChangementDeStatut;

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

    /** Couleurs Flux des badges (toujours accompagnées du libellé : la couleur n'est jamais seule). */
    public const STATUT_COLORS = ['nouveau' => 'amber', 'en_cours' => 'blue', 'resolu' => 'green', 'rejete' => 'red'];

    /** États terminés : la demande n'évoluera plus (onglet « Terminées » de Mes demandes). */
    public const STATUTS_TERMINES = ['resolu', 'rejete'];

    /** Intitulé public de l'étape atteinte à chaque état (suivi D11). */
    public const ETAPE_LABELS = [
        'nouveau' => 'Signalement reçu',
        'en_cours' => 'Pris en charge par les services',
        'resolu' => 'Problème résolu',
        'rejete' => 'Demande rejetée',
    ];

    /** Prochaine étape attendue tant que la demande est ouverte. */
    public const PROCHAINE_ETAPE = [
        'nouveau' => 'Prise en charge par les services',
        'en_cours' => 'Intervention programmée et résolution',
    ];

    /** Seul acteur affiché côté mairie dans le suivi citoyen : jamais le nom d'un agent. */
    public const ACTEUR_MAIRIE = 'Mairie de Nova Terra';

    /**
     * Valeur par défaut du statut : la première de STATUT_OPTIONS (« Nouveau »).
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0]];

    /**
     * Chaque dépôt et chaque changement d'état ajoute une étape au suivi (D11), quel que soit l'écran.
     * L'étape elle-même est lue dans le journal d'audit (F47) : aucune donnée dupliquée.
     */
    protected static function booted(): void
    {
        static::created(fn (self $signalement) => SignalementEtapeAjoutee::dispatch($signalement, (string) $signalement->statut));

        static::updated(function (self $signalement): void {
            if ($signalement->wasChanged('statut')) {
                SignalementEtapeAjoutee::dispatch($signalement, (string) $signalement->statut);
            }
        });
    }

    /**
     * Demandes déposées par cet habitant (Mes demandes D11 ; réutilisable par F26 et F56).
     *
     * @param  Builder<self>  $query
     */
    public function scopeDuCitoyen(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /**
     * Changements d'état enregistrés par le journal d'audit (F47), du plus ancien au plus récent.
     * Ne sert qu'à construire chronologie() : les colonnes de l'agent (nom, rôle, IP) ne sont jamais affichées au citoyen.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function etapes(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')
            ->where('subject_type', class_basename(self::class))
            ->where('action', 'status_changed')
            ->oldest('created_at')
            ->oldest('id');
    }

    /**
     * Suivi public de la demande, du dépôt à l'état actuel (dates à l'heure de Madagascar, en français).
     * Seuls l'état atteint et la date sont lus dans le journal ; l'acteur côté mairie est toujours « Mairie de Nova Terra ».
     * Si le journal est incomplet (données antérieures à F47, import), l'état actuel est ajouté à la date de dernière mise à jour.
     *
     * @return list<array{statut: string, label: string, texte: string, date: Carbon|null, etat: string, fait: bool, courant: bool}>
     */
    public function chronologie(): array
    {
        $etapes = [$this->etape('nouveau', $this->created_at, 'Déposé par vous en ligne')];
        $dernierStatut = 'nouveau';

        foreach ($this->etapes as $entree) {
            // getAttribute() : depuis un autre modèle, « ->changes » lirait la propriété interne d'Eloquent (toujours vide ici), pas la colonne.
            $statut = self::statutDepuisLibelle($entree->getAttribute('changes')['statut']['apres'] ?? null);

            if ($statut !== null && $statut !== $dernierStatut) {
                $etapes[] = $this->etape($statut, $entree->created_at, 'Par la '.self::ACTEUR_MAIRIE);
                $dernierStatut = $statut;
            }
        }

        if ($dernierStatut !== $this->statut) {
            $etapes[] = $this->etape((string) $this->statut, $this->updated_at, 'Par la '.self::ACTEUR_MAIRIE);
        }

        $courante = array_pop($etapes);
        $courante['courant'] = true;
        $etapes[] = $courante;

        return $etapes;
    }

    /**
     * @return array{statut: string, label: string, texte: string, date: Carbon|null, etat: string, fait: bool, courant: bool}
     */
    private function etape(string $statut, ?\DateTimeInterface $date, string $texte): array
    {
        return [
            'statut' => $statut,
            'label' => self::ETAPE_LABELS[$statut] ?? self::libelleStatut($statut),
            'texte' => $texte,
            'date' => $date === null ? null : Carbon::instance($date)->setTimezone(AuditLog::FUSEAU)->settings(['locale' => 'fr']),
            'etat' => self::STATUT_ETATS[$statut] ?? 'info',
            'fait' => true,
            'courant' => false,
        ];
    }

    /**
     * Le journal stocke le libellé (« En cours ») : on retrouve la valeur (« en_cours »), null si inconnue.
     */
    public static function statutDepuisLibelle(mixed $libelle): ?string
    {
        if (! is_string($libelle)) {
            return null;
        }

        if (in_array($libelle, self::STATUT_OPTIONS, true)) {
            return $libelle;
        }

        $statut = array_search($libelle, self::STATUT_LABELS, true);

        return $statut === false ? null : $statut;
    }

    public function statutLibelle(): string
    {
        return self::libelleStatut((string) $this->statut);
    }

    public function statutCouleur(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'zinc';
    }

    public function estTermine(): bool
    {
        return in_array($this->statut, self::STATUTS_TERMINES, true);
    }

    public function prochaineEtape(): ?string
    {
        return self::PROCHAINE_ETAPE[$this->statut] ?? null;
    }

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

    public function estSoutenuPar(User $user): bool
    {
        return $this->soutiens()->whereBelongsTo($user)->exists();
    }

    /**
     * Enregistre le soutien de l'habitant (sans effet s'il soutient déjà). Seul point de passage :
     * user_id et signalement_id sont assignés ici, jamais depuis le navigateur.
     */
    public function ajouterSoutien(User $user): void
    {
        if ($this->estSoutenuPar($user)) {
            return;
        }

        $soutien = new Soutien;
        $soutien->user()->associate($user);
        $soutien->signalement()->associate($this);

        try {
            $soutien->save();
        } catch (UniqueConstraintViolationException) {
            // Double clic simultané : la contrainte unique a déjà enregistré le soutien.
        }
    }

    public function retirerSoutien(User $user): void
    {
        $this->soutiens()->whereBelongsTo($user)->delete();
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
     * Si l'état change vraiment, le propriétaire est prévenu (F49).
     */
    public function changerStatut(string $statut): void
    {
        if (! in_array($statut, self::STATUT_OPTIONS, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$statut}");
        }

        $statutAvant = (string) $this->statut;

        $this->statut = $statut;
        $this->save();

        $this->prevenirProprietaire($statutAvant);
    }
}
