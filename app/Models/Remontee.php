<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Carbon\CarbonInterface;
use Database\Factories\RemonteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Inquiétude d'un habitant sur l'usage de ses données (F51), avec un numéro de suivi et une trace de traitement.
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir RemonteePolicy.
 *
 * Seuls categorie, objet et message sont remplissables. Tout le reste (reference, user_id, statut, dates,
 * agents, réponse) est assigné dans le code : envoyer(), prendreEnCompte(), repondre(), cloturer().
 * Si le compte de l'habitant est supprimé, user_id passe à null : la remontée reste, anonymisée.
 *
 * @property int $id
 * @property string|null $reference
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string $categorie
 * @property string $objet
 * @property string $message
 * @property string $statut
 * @property CarbonInterface|null $envoyee_le
 * @property CarbonInterface|null $prise_en_compte_le
 * @property int|null $pris_en_charge_par
 * @property-read User|null $agentPriseEnCharge
 * @property string|null $reponse
 * @property CarbonInterface|null $repondue_le
 * @property int|null $repondue_par
 * @property-read User|null $agentReponse
 * @property CarbonInterface|null $cloturee_le
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['categorie', 'objet', 'message'])]
class Remontee extends Model
{
    /** @use HasFactory<RemonteeFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    public const CATEGORIE_OPTIONS = ['comprendre', 'acceder', 'corriger', 'supprimer', 'autre'];

    public const CATEGORIE_LABELS = [
        'comprendre' => 'Comprendre l’usage de mes données',
        'acceder' => 'Accéder à mes données',
        'corriger' => 'Corriger mes données',
        'supprimer' => 'Supprimer mes données',
        'autre' => 'Autre',
    ];

    public const STATUT_OPTIONS = ['recue', 'prise_en_compte', 'repondue', 'cloturee'];

    /** Libellés affichés (lus aussi par le journal d'audit F47). */
    public const STATUT_LABELS = ['recue' => 'Reçue', 'prise_en_compte' => 'Prise en compte', 'repondue' => 'Répondue', 'cloturee' => 'Clôturée'];

    /** État du badge Terra Nova (la couleur n'est jamais seule : icône + libellé). */
    public const STATUT_ETATS = ['recue' => 'perturbe', 'prise_en_compte' => 'info', 'repondue' => 'normal', 'cloturee' => 'normal'];

    /** États qui attendent encore une action d'un agent. */
    public const STATUTS_EN_ATTENTE = ['recue', 'prise_en_compte'];

    /** Préfixe du numéro de suivi : DON-2026-000042. */
    public const PREFIXE_REFERENCE = 'DON';

    /**
     * Le contenu libre (message, réponse) n'est pas copié dans le journal d'audit immuable :
     * seules les étapes du traitement y sont tracées.
     *
     * @var list<string>
     */
    protected array $auditIgnore = ['message', 'reponse'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0]];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'envoyee_le' => 'datetime',
            'prise_en_compte_le' => 'datetime',
            'repondue_le' => 'datetime',
            'cloturee_le' => 'datetime',
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
    public function agentPriseEnCharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pris_en_charge_par');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agentReponse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repondue_par');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnAttente(Builder $query): void
    {
        $query->whereIn('statut', self::STATUTS_EN_ATTENTE);
    }

    /**
     * Enregistre la remontée d'un habitant : seul point de création (dates, auteur et numéro fixés ici).
     *
     * @param  array{categorie: string, objet: string, message: string}  $donnees
     */
    public static function envoyer(User $auteur, array $donnees): self
    {
        return DB::transaction(function () use ($auteur, $donnees): self {
            $remontee = new self($donnees);
            $remontee->user()->associate($auteur);
            $remontee->statut = 'recue';
            $remontee->envoyee_le = now();
            $remontee->save();

            // Numéro unique dérivé de l'identifiant : aucune collision possible, rien de devinable côté client.
            $remontee->reference = sprintf('%s-%s-%06d', self::PREFIXE_REFERENCE, $remontee->envoyee_le->format('Y'), $remontee->id);
            $remontee->save();

            return $remontee;
        });
    }

    /**
     * @throws \DomainException si la remontée n'est plus au stade « Reçue »
     */
    public function prendreEnCompte(User $agent): void
    {
        if ($this->statut !== 'recue') {
            throw new \DomainException('Cette remontée a déjà été prise en compte.');
        }

        $this->marquerPriseEnCompte($agent);
        $this->statut = 'prise_en_compte';
        $this->save();
    }

    /**
     * Publie la réponse de l'agent. Une remontée encore « Reçue » est prise en compte au passage
     * (la frise reste complète).
     *
     * @throws \DomainException si la remontée a déjà une réponse ou est clôturée
     */
    public function repondre(User $agent, string $reponse): void
    {
        if (! in_array($this->statut, self::STATUTS_EN_ATTENTE, true)) {
            throw new \DomainException('Cette remontée a déjà reçu une réponse.');
        }

        if ($this->prise_en_compte_le === null) {
            $this->marquerPriseEnCompte($agent);
        }

        $this->reponse = $reponse;
        $this->repondue_le = now();
        $this->agentReponse()->associate($agent);
        $this->statut = 'repondue';
        $this->save();
    }

    /**
     * @throws \DomainException si aucune réponse n'a encore été donnée
     */
    public function cloturer(): void
    {
        if ($this->statut === 'cloturee') {
            throw new \DomainException('Cette remontée est déjà clôturée.');
        }

        if ($this->statut !== 'repondue') {
            throw new \DomainException('Une remontée ne peut être clôturée qu’après une réponse à l’habitant.');
        }

        $this->cloturee_le = now();
        $this->statut = 'cloturee';
        $this->save();
    }

    private function marquerPriseEnCompte(User $agent): void
    {
        $this->prise_en_compte_le = now();
        $this->agentPriseEnCharge()->associate($agent);
    }

    /**
     * Date affichée à l'heure de Madagascar (stockage en UTC).
     */
    public static function dateLocale(?CarbonInterface $date, string $format = 'd/m/Y à H:i'): string
    {
        return $date?->copy()->timezone(Annonce::FUSEAU)->format($format) ?? '—';
    }

    public static function libelleStatut(string $statut): string
    {
        return self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
    }

    public static function libelleCategorie(string $categorie): string
    {
        return self::CATEGORIE_LABELS[$categorie] ?? ucfirst($categorie);
    }

    public function etatStatut(): string
    {
        return self::STATUT_ETATS[$this->statut] ?? 'info';
    }

    /**
     * Nom affiché dans le journal d'audit (F47).
     */
    public function auditLabel(): string
    {
        return $this->reference ?? $this->objet;
    }

    /**
     * Frise de suivi (citoyen et agent) : seules les dates réellement enregistrées sont affichées.
     *
     * @return list<array{label: string, date: CarbonInterface|null, etat: string, fait: bool, texte: string|null}>
     */
    public function frise(): array
    {
        return [
            ['label' => 'Envoyée', 'date' => $this->envoyee_le?->copy()->timezone(Annonce::FUSEAU), 'etat' => 'info', 'fait' => true, 'texte' => 'Votre message a bien été reçu par la mairie.'],
            [
                'label' => 'Prise en compte',
                'date' => $this->prise_en_compte_le?->copy()->timezone(Annonce::FUSEAU),
                'etat' => 'info',
                'fait' => $this->prise_en_compte_le !== null,
                'texte' => $this->prise_en_compte_le !== null ? 'Un agent du service municipal s’occupe de votre remontée.' : 'En attente d’un agent municipal.',
            ],
            [
                'label' => 'Réponse',
                'date' => $this->repondue_le?->copy()->timezone(Annonce::FUSEAU),
                'etat' => 'normal',
                'fait' => $this->repondue_le !== null,
                'texte' => $this->repondue_le !== null ? 'La réponse de la mairie est affichée ci-dessous.' : 'La réponse apparaîtra ici.',
            ],
            [
                'label' => 'Clôturée',
                'date' => $this->cloturee_le?->copy()->timezone(Annonce::FUSEAU),
                'etat' => 'normal',
                'fait' => $this->cloturee_le !== null,
                'texte' => $this->cloturee_le !== null ? 'Le traitement de votre remontée est terminé.' : null,
            ],
        ];
    }
}
