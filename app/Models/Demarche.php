<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\HasConfidentialFields;
use App\Models\Concerns\PrevientDuChangementDeStatut;
use App\Notifications\ReponseDemarcheRecue;
use App\Services\ParOuCommencer;
use Database\Factories\DemarcheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Démarche administrative déposée par un habitant auprès d'un service municipal.
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir DemarchePolicy.
 *
 * user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 *
 * F70 : un agent ne voit que les démarches de ses services (scope visibleTo + DemarchePolicy) ;
 * les coordonnées et la situation du demandeur sont confidentielles (confidentialFields).
 */
#[Fillable(['titre', 'description', 'service_id'])]
class Demarche extends Model
{
    /** @use HasFactory<DemarcheFactory> */
    use Auditable, HasAuditHistory, HasConfidentialFields, HasFactory, PrevientDuChangementDeStatut;

    public const STATUT_OPTIONS = ['deposee', 'en_cours', 'traitee', 'refusee'];

    /** Couleurs Flux des badges. */
    public const STATUT_COLORS = ['deposee' => 'amber', 'en_cours' => 'blue', 'traitee' => 'green', 'refusee' => 'red'];

    /** État affiché par le badge Terra Nova (la couleur indique un état : normal, perturbé, alerte, info). */
    public const STATUT_ETATS = ['deposee' => 'perturbe', 'en_cours' => 'info', 'traitee' => 'normal', 'refusee' => 'alerte'];

    /** Libellés affichés (avec accents). */
    public const STATUT_LABELS = ['deposee' => 'Déposée', 'en_cours' => 'En cours', 'traitee' => 'Traitée', 'refusee' => 'Refusée'];

    /** États « en attente de prise en charge » : aucun agent ne s'en occupe encore (D17). */
    public const STATUTS_EN_ATTENTE_PRISE_EN_CHARGE = ['deposee'];

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
     * F70 : démarches visibles par l'utilisateur. Admin : toutes ; agent : celles de ses services
     * (une démarche sans service est réservée à l'admin) ; habitant : les siennes.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match (true) {
            $user->isAdmin() => null,
            $user->isAgent() => $query->whereIn('service_id', $user->serviceIds()),
            default => $query->where('user_id', $user->id),
        };
    }

    /**
     * F70 : coordonnées et situation du demandeur, masquées par défaut dans l'espace agent.
     *
     * @return array<string, array{label: string, valeur: \Closure(): (string|null)}>
     */
    public function confidentialFields(): array
    {
        return [
            'telephone_demandeur' => ['label' => 'Téléphone du demandeur', 'valeur' => fn (): ?string => $this->user->telephone],
            'email_demandeur' => ['label' => 'E-mail du demandeur', 'valeur' => fn (): ?string => $this->user->emailAffichable()],
            'quartier_demandeur' => ['label' => 'Quartier du demandeur', 'valeur' => fn (): ?string => $this->user->quartierResidence->nom ?? $this->user->quartier],
            'situation_demandeur' => ['label' => 'Situation déclarée', 'valeur' => fn (): string => collect($this->user->onboarding->situation ?? [])
                ->map(fn (string $cle): ?string => ParOuCommencer::SITUATIONS[$cle]['label'] ?? null)
                ->filter()
                ->implode(', ')],
        ];
    }

    /**
     * F84 : fil de messages (réponses des agents et de l'habitant), du plus ancien au plus récent.
     *
     * @return HasMany<ReponseDemarche, $this>
     */
    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseDemarche::class)->oldest('id');
    }

    /**
     * F84 : dernier message du fil (sert au badge « Réponse envoyée / En attente de réponse »).
     *
     * @return HasOne<ReponseDemarche, $this>
     */
    public function derniereReponse(): HasOne
    {
        return $this->hasOne(ReponseDemarche::class)->latestOfMany();
    }

    /**
     * F84 : démarches dont le dernier message n'est pas une réponse d'agent (aucune réponse, ou l'habitant a relancé).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSansReponse(Builder $query): void
    {
        $query->whereDoesntHave('derniereReponse', fn ($reponse) => $reponse->where('de_agent', true));
    }

    /**
     * F84 : vrai si le dernier message du fil est une réponse d'agent (charger derniereReponse avant dans une liste).
     */
    public function reponseEnvoyee(): bool
    {
        return (bool) $this->derniereReponse?->de_agent;
    }

    /**
     * F84 : seul point de passage pour écrire dans le fil (droits vérifiés avant : policy repondre).
     * demarche_id, user_id et de_agent sont assignés ici, jamais depuis le navigateur.
     * Une réponse d'agent prévient l'habitant (cloche + e-mail).
     */
    public function ajouterReponse(User $auteur, string $message): ReponseDemarche
    {
        $reponse = new ReponseDemarche(['message' => $message]);
        $reponse->demarche()->associate($this);
        $reponse->user()->associate($auteur);
        $reponse->de_agent = $auteur->id !== $this->user_id && ($auteur->isAgent() || $auteur->isAdmin());
        $reponse->save();

        if ($reponse->de_agent) {
            $this->prevenirDeLaReponse($reponse);
        }

        return $reponse;
    }

    private function prevenirDeLaReponse(ReponseDemarche $reponse): void
    {
        // Rechargé en entier : un user chargé partiellement n'aurait pas d'e-mail.
        $proprietaire = User::query()->find($this->user_id);

        if ($proprietaire === null) {
            return;
        }

        $avis = new ReponseDemarcheRecue($this, $reponse);

        DB::afterCommit(function () use ($proprietaire, $avis): void {
            // La cloche d'abord : elle reste enregistrée même si l'e-mail échoue.
            $proprietaire->notifyNow($avis, ['database']);

            try {
                $proprietaire->notifyNow($avis, ['mail']);
            } catch (\Throwable $e) {
                // Un échec d'envoi (sendmail synchrone en production) ne doit jamais bloquer l'agent.
                report($e);
            }
        });
    }

    /**
     * Demandes qui attendent encore une prise en charge par un agent.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingHandling(Builder $query): void
    {
        $query->whereIn('statut', self::STATUTS_EN_ATTENTE_PRISE_EN_CHARGE);
    }

    public static function libelleStatut(string $statut): string
    {
        return __(self::STATUT_LABELS[$statut] ?? ucfirst(str_replace('_', ' ', $statut)));
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
