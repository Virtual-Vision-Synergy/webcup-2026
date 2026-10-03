<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\Soutenable;
use Carbon\CarbonInterface;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Idée d'un habitant pour améliorer la colonie (F68) : publiée tout de suite, soutenue par les autres habitants
 * (mécanisme F52), étudiée puis retenue ou non par un agent, qui répond. Un agent peut la masquer (modération).
 *
 * Seuls title, description et category sont remplissables. Tout le reste (reference, user_id, status, réponse,
 * dates, masquage) est assigné dans le code : proposer(), changerStatut(), masquer(), reafficher().
 *
 * @property int $id
 * @property string|null $reference
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string $title
 * @property string $description
 * @property string $category
 * @property string $status
 * @property string|null $response
 * @property CarbonInterface|null $responded_at
 * @property int|null $responded_by
 * @property CarbonInterface|null $status_changed_at
 * @property CarbonInterface|null $hidden_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['title', 'description', 'category'])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use Auditable, HasAuditHistory, HasFactory, Soutenable;

    public const CATEGORY_OPTIONS = ['cadre_de_vie', 'environnement', 'mobilite', 'culture_loisirs', 'services_publics', 'solidarite', 'autre'];

    public const CATEGORY_LABELS = [
        'cadre_de_vie' => 'Cadre de vie',
        'environnement' => 'Environnement',
        'mobilite' => 'Mobilité',
        'culture_loisirs' => 'Culture et loisirs',
        'services_publics' => 'Services publics',
        'solidarite' => 'Solidarité',
        'autre' => 'Autre',
    ];

    public const STATUS_OPTIONS = ['recue', 'a_l_etude', 'retenue', 'non_retenue'];

    public const STATUS_LABELS = ['recue' => 'Reçue', 'a_l_etude' => 'À l’étude', 'retenue' => 'Retenue', 'non_retenue' => 'Non retenue'];

    /** État du badge Terra Nova (la couleur n'est jamais seule : icône + libellé). */
    public const STATUS_ETATS = ['recue' => 'info', 'a_l_etude' => 'perturbe', 'retenue' => 'normal', 'non_retenue' => 'alerte'];

    /** États où l'idée attend encore la décision de la ville : on peut la soutenir (comme F52). */
    public const STATUTS_OUVERTS = ['recue', 'a_l_etude'];

    /** Décisions qui exigent une réponse écrite de la ville. */
    public const STATUTS_DECISION = ['retenue', 'non_retenue'];

    /** Préfixe du numéro de suivi : IDE-2026-000012. */
    public const PREFIXE_REFERENCE = 'IDE';

    /**
     * Le texte libre n'est pas copié dans le journal d'audit immuable : seules les étapes y sont tracées.
     *
     * @var list<string>
     */
    protected array $auditIgnore = ['description', 'response'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => self::STATUS_OPTIONS[0]];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'hidden_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Soutiens d'autres habitants (mécanisme F52, trait Soutenable).
     *
     * @return HasMany<IdeaSupport, $this>
     */
    public function soutiens(): HasMany
    {
        return $this->hasMany(IdeaSupport::class);
    }

    protected function nouveauSoutien(): IdeaSupport
    {
        $soutien = new IdeaSupport;
        $soutien->idea()->associate($this);

        return $soutien;
    }

    /**
     * Idées affichées publiquement (non masquées par la modération).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibles(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * Seul point de création : auteur, état « Reçue » et numéro de suivi fixés ici.
     *
     * @param  array{title: string, description: string, category: string}  $donnees
     */
    public static function proposer(User $auteur, array $donnees): self
    {
        return DB::transaction(function () use ($auteur, $donnees): self {
            $idea = new self($donnees);
            $idea->user()->associate($auteur);
            $idea->status = 'recue';
            $idea->save();

            // Numéro unique dérivé de l'identifiant : aucune collision possible, rien de devinable côté client.
            $idea->reference = self::referencePour($idea);
            $idea->save();

            return $idea;
        });
    }

    public static function referencePour(self $idea): string
    {
        return sprintf('%s-%s-%06d', self::PREFIXE_REFERENCE, ($idea->created_at ?? now())->format('Y'), $idea->id);
    }

    /**
     * Seul point de passage pour l'état et la réponse (réservé au personnel : policy updateStatus / respond).
     *
     * @throws \InvalidArgumentException si l'état est inconnu
     * @throws \DomainException si une décision n'a pas de réponse écrite
     */
    public function changerStatut(User $agent, string $status, ?string $response = null): void
    {
        if (! in_array($status, self::STATUS_OPTIONS, true)) {
            throw new \InvalidArgumentException("État inconnu : {$status}");
        }

        $response = $response !== null && trim($response) !== '' ? trim($response) : null;

        if (in_array($status, self::STATUTS_DECISION, true) && $response === null) {
            throw new \DomainException('Rédigez la réponse de la ville pour retenir ou non cette idée.');
        }

        if ($status !== $this->status) {
            $this->status = $status;
            $this->status_changed_at = now();
        }

        if ($response !== null && $response !== $this->response) {
            $this->response = $response;
            $this->responded_at = now();
            $this->respondedBy()->associate($agent);
        }

        $this->save();
    }

    public function masquer(): void
    {
        $this->hidden_at = now();
        $this->save();
    }

    public function reafficher(): void
    {
        $this->hidden_at = null;
        $this->save();
    }

    public function estMasquee(): bool
    {
        return $this->hidden_at !== null;
    }

    public function estOuverte(): bool
    {
        return in_array($this->status, self::STATUTS_OUVERTS, true) && ! $this->estMasquee();
    }

    public static function libelleStatut(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function libelleCategorie(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }

    public function etatStatut(): string
    {
        return self::STATUS_ETATS[$this->status] ?? 'info';
    }

    /**
     * Date affichée à l'heure de Madagascar (stockage en UTC).
     */
    public static function dateLocale(?CarbonInterface $date, string $format = 'd/m/Y à H:i'): string
    {
        return $date?->copy()->timezone(Annonce::FUSEAU)->format($format) ?? '—';
    }

    /**
     * Nom affiché dans le journal d'audit (F47).
     */
    public function auditLabel(): string
    {
        return $this->reference ?? $this->title;
    }

    /**
     * Frise de suivi : Envoyée → À l'étude → Réponse de la ville. Seules les dates enregistrées sont affichées.
     *
     * @return list<array{label: string, date: CarbonInterface|null, etat: string, fait: bool, texte: string|null}>
     */
    public function frise(): array
    {
        $etudiee = $this->status !== 'recue';
        $decidee = in_array($this->status, self::STATUTS_DECISION, true);

        return [
            ['label' => 'Envoyée', 'date' => $this->created_at?->copy()->timezone(Annonce::FUSEAU), 'etat' => 'info', 'fait' => true, 'texte' => 'Votre idée a bien été reçue et publiée.'],
            [
                'label' => 'À l’étude',
                'date' => $this->status === 'a_l_etude' ? $this->status_changed_at?->copy()->timezone(Annonce::FUSEAU) : null,
                'etat' => 'info',
                'fait' => $etudiee,
                'texte' => $etudiee ? 'Un agent de la ville étudie votre idée.' : 'En attente d’un agent de la ville.',
            ],
            [
                'label' => $decidee ? 'Réponse de la ville : '.self::libelleStatut($this->status) : 'Réponse de la ville',
                'date' => $decidee ? $this->responded_at?->copy()->timezone(Annonce::FUSEAU) : null,
                'etat' => $this->status === 'non_retenue' ? 'alerte' : 'normal',
                'fait' => $decidee,
                'texte' => $decidee ? 'La réponse de la ville est affichée ci-dessus.' : 'La réponse apparaîtra ici.',
            ],
        ];
    }
}
