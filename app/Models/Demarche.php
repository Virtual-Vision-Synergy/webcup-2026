<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Notifications\DemarcheStatutModifie;
use Database\Factories\DemarcheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Démarche administrative déposée par un habitant auprès d'un service municipal.
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir DemarchePolicy.
 *
 * user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['titre', 'description', 'service_id'])]
class Demarche extends Model
{
    /** @use HasFactory<DemarcheFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    public const STATUT_OPTIONS = ['deposee', 'en_cours', 'traitee', 'refusee'];

    /** Couleurs Flux des badges. */
    public const STATUT_COLORS = ['deposee' => 'amber', 'en_cours' => 'blue', 'traitee' => 'green', 'refusee' => 'red'];

    /** État affiché par le badge Terra Nova (la couleur indique un état : normal, perturbé, alerte, info). */
    public const STATUT_ETATS = ['deposee' => 'perturbe', 'en_cours' => 'info', 'traitee' => 'normal', 'refusee' => 'alerte'];

    /** Libellés affichés (avec accents). */
    public const STATUT_LABELS = ['deposee' => 'Déposée', 'en_cours' => 'En cours', 'traitee' => 'Traitée', 'refusee' => 'Refusée'];

    /** Ce que l'habitant doit savoir ou faire après chaque changement d'état (D11, F49). */
    public const STATUT_CONSEILS = [
        'deposee' => 'Votre demande attend d’être prise en charge par un agent. Vous n’avez rien à faire pour le moment.',
        'en_cours' => 'Un agent s’occupe de votre demande. Vous n’avez rien à faire : vous serez prévenu à la prochaine étape.',
        'traitee' => 'Votre demande est acceptée. Lisez le message de l’agent pour connaître la suite (retrait d’un document, rendez-vous…).',
        'refusee' => 'Votre demande n’a pas été acceptée. Lisez le motif ; vous pouvez déposer une nouvelle démarche ou écrire au service.',
    ];

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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Étapes datées du suivi (D11), de la plus ancienne à la plus récente.
     *
     * @return HasMany<DemarcheEtape, $this>
     */
    public function etapes(): HasMany
    {
        return $this->hasMany(DemarcheEtape::class)->oldest()->oldest('id');
    }

    public static function conseilStatut(string $statut): string
    {
        return self::STATUT_CONSEILS[$statut] ?? '';
    }

    public static function libelleStatut(string $statut): string
    {
        return self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
    }

    public function couleurStatut(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'zinc';
    }

    public function etatStatut(): string
    {
        return self::STATUT_ETATS[$this->statut] ?? 'info';
    }

    /**
     * Seul point de passage pour modifier le statut (réservé aux agents et admins : policy changerStatut).
     * Enregistre une étape datée du suivi (D11) et prévient l'habitant (F49, application + e-mail).
     * Sans effet si le statut ne change pas (ni étape ni notification en double).
     */
    public function changerStatut(string $statut, ?string $commentaire = null, ?User $agent = null): void
    {
        if (! in_array($statut, self::STATUT_OPTIONS, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$statut}");
        }

        if ($this->statut === $statut) {
            return;
        }

        $this->statut = $statut;
        $this->save();

        $etape = new DemarcheEtape;
        $etape->statut = $statut;
        $etape->commentaire = filled($commentaire) ? trim($commentaire) : null;
        $etape->user_id = $agent?->id;
        $this->etapes()->save($etape);
        $this->unsetRelation('etapes');

        $this->notifierHabitant($etape);
    }

    /**
     * F49 : l'auteur de la démarche, et lui seul, est prévenu du nouvel état.
     * Un e-mail en échec ne doit pas annuler le changement d'état : l'erreur est journalisée.
     */
    private function notifierHabitant(DemarcheEtape $etape): void
    {
        $auteur = $this->user;

        if ($auteur === null || ! $auteur->isActive()) {
            return;
        }

        try {
            $auteur->notify(new DemarcheStatutModifie($this, $etape));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Chronologie affichée à l'habitant (D11) : dépôt, puis chaque changement d'état daté,
     * puis l'étape suivante attendue tant que la démarche n'est pas terminée.
     *
     * @return list<array{label: string, date: Carbon|null, etat: string, fait: bool, texte: string|null}>
     */
    public function chronologie(): array
    {
        $items = [['label' => 'Démarche déposée', 'date' => $this->created_at, 'etat' => 'info', 'fait' => true, 'texte' => null]];

        foreach ($this->etapes as $etape) {
            $items[] = [
                'label' => match ($etape->statut) {
                    'en_cours' => 'Prise en charge par le service',
                    'traitee', 'refusee' => 'Décision : '.self::libelleStatut($etape->statut),
                    default => 'Revenue à l’état « '.self::libelleStatut($etape->statut).' »',
                },
                'date' => $etape->created_at,
                'etat' => self::STATUT_ETATS[$etape->statut] ?? 'info',
                'fait' => true,
                'texte' => $etape->commentaire !== null ? 'Message de l’agent : '.$etape->commentaire : null,
            ];
        }

        if (! in_array($this->statut, ['traitee', 'refusee'], true)) {
            $items[] = $this->statut === 'en_cours'
                ? ['label' => 'Décision', 'date' => null, 'etat' => 'info', 'fait' => false, 'texte' => 'La décision apparaîtra ici.']
                : ['label' => 'Prise en charge par le service', 'date' => null, 'etat' => 'info', 'fait' => false, 'texte' => 'En attente d’un agent municipal.'];
        }

        return $items;
    }
}
