<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Notifications\Avis;
use Carbon\CarbonInterface;
use Database\Factories\ServiceReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Avis d'un habitant sur un service municipal (F76) : note de 1 à 5 et commentaire.
 * Un seul avis par habitant et par service (contrainte unique en base), modifiable tant qu'il n'est pas masqué.
 * À ne pas confondre avec la notification « Avis » de la cloche (F30).
 *
 * Seuls rating et comment sont remplissables. user_id (utilisateur connecté), service_id (pris de la route),
 * verified_usage (calculé côté serveur), le masquage et la réponse sont assignés dans le code :
 * enregistrer(), masquer(), reafficher(), repondre().
 *
 * @property int $id
 * @property int $user_id
 * @property int $service_id
 * @property int $rating
 * @property string $comment
 * @property bool $verified_usage
 * @property CarbonInterface|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $hidden_reason
 * @property string|null $response
 * @property CarbonInterface|null $responded_at
 * @property int|null $responded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $user
 * @property-read Service $service
 */
#[Fillable(['rating', 'comment'])]
class ServiceReview extends Model
{
    /** @use HasFactory<ServiceReviewFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    /** Libellés textuels de chaque note (sélection accessible, pas seulement des étoiles). */
    public const RATING_LABELS = [
        1 => 'Très insatisfait',
        2 => 'Insatisfait',
        3 => 'Correct',
        4 => 'Satisfait',
        5 => 'Très satisfait',
    ];

    public const HIDDEN_REASON_OPTIONS = ['injurieux', 'donnees_personnelles', 'hors_sujet', 'autre'];

    public const HIDDEN_REASON_LABELS = [
        'injurieux' => 'Propos injurieux',
        'donnees_personnelles' => 'Données personnelles',
        'hors_sujet' => 'Hors sujet',
        'autre' => 'Autre',
    ];

    public const COMMENT_MIN = 10;

    public const COMMENT_MAX = 1000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_usage' => 'boolean',
            'hidden_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Avis publiés (non masqués par la modération) : les seuls comptés dans la moyenne et affichés sur la fiche.
     *
     * @param  Builder<ServiceReview>  $query
     */
    public function scopeVisibles(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * F70 : avis des services que l'utilisateur peut traiter (admin : tous ; agent : ses services).
     *
     * @param  Builder<ServiceReview>  $query
     */
    public function scopeTraitablesPar(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereIn('service_id', $user->serviceIds());
        }
    }

    /**
     * Crée ou met à jour l'avis de l'habitant sur ce service (jamais de doublon). Appeler après l'autorisation.
     *
     * @param  array{rating: int|string, comment: string}  $donnees
     */
    public static function enregistrer(User $auteur, Service $service, array $donnees): self
    {
        $avis = self::query()->where('user_id', $auteur->id)->where('service_id', $service->id)->first() ?? new self;
        $avis->user_id = $auteur->id;
        $avis->service_id = $service->id;
        $avis->fill(['rating' => (int) $donnees['rating'], 'comment' => trim($donnees['comment'])]);
        $avis->verified_usage = self::usageVerifie($auteur, $service);
        $avis->save();

        return $avis;
    }

    /**
     * L'habitant a-t-il réellement utilisé ce service ? Une démarche traitée, ou un rendez-vous honoré
     * (ou confirmé et déjà passé).
     */
    public static function usageVerifie(User $user, Service $service): bool
    {
        $demarche = Demarche::query()
            ->where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->where('statut', 'traitee')
            ->exists();

        return $demarche || RendezVous::query()
            ->where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->where(fn (Builder $query) => $query
                ->where('statut', 'honore')
                ->orWhere(fn (Builder $query) => $query
                    ->where('statut', 'confirme')
                    ->whereHas('creneau', fn (Builder $query) => $query->where('debut', '<', now()))))
            ->exists();
    }

    public function masquer(User $agent, string $motif): void
    {
        $this->hidden_at = now();
        $this->hidden_by = $agent->id;
        $this->hidden_reason = $motif;
        $this->saveSansModifierLaDate();

        ActionLog::record('avis_service_masque', $this);
        $this->prevenirAuteur(new Avis(
            'Votre avis sur « '.$this->service->nom.' » a été masqué',
            [
                'Votre avis a été masqué par la modération : '.$this->libelleMotifMasquage().'.',
                'Il n’apparaît plus sur la fiche du service. Vous le voyez toujours dans « Mes avis sur les services ».',
            ],
            'Voir mes avis',
            route('services.reviews.mine'),
        ));
    }

    public function reafficher(): void
    {
        $this->hidden_at = null;
        $this->hidden_by = null;
        $this->hidden_reason = null;
        $this->saveSansModifierLaDate();

        ActionLog::record('avis_service_reaffiche', $this);
    }

    public function repondre(User $agent, string $reponse): void
    {
        $this->response = trim($reponse);
        $this->responded_at = now();
        $this->responded_by = $agent->id;
        $this->saveSansModifierLaDate();

        ActionLog::record('avis_service_repondu', $this);
        $this->prevenirAuteur(new Avis(
            'Le service « '.$this->service->nom.' » a répondu à votre avis',
            ['Le service a publié une réponse sous votre avis.'],
            'Lire la réponse',
            route('services.reviews.mine'),
        ));
    }

    /**
     * La modération et la réponse ne changent pas updated_at : c'est la date de dernière modification par l'auteur.
     */
    private function saveSansModifierLaDate(): void
    {
        $this->timestamps = false;

        try {
            $this->save();
        } finally {
            $this->timestamps = true;
        }
    }

    public function estMasque(): bool
    {
        return $this->hidden_at !== null;
    }

    public function libelleNote(): string
    {
        return $this->rating.' / 5 — '.(self::RATING_LABELS[$this->rating] ?? '');
    }

    public function libelleMotifMasquage(): string
    {
        return self::HIDDEN_REASON_LABELS[$this->hidden_reason] ?? 'motif non précisé';
    }

    /**
     * Nom affiché publiquement : prénom et initiale du nom (« Hery R. »), jamais l'e-mail ni le nom complet.
     */
    public function auteurPublic(): string
    {
        $parties = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $prenom = $parties[0] ?? '';

        if ($prenom === '') {
            return 'Un habitant';
        }

        return count($parties) > 1 ? $prenom.' '.Str::upper(Str::substr((string) end($parties), 0, 1)).'.' : $prenom;
    }

    /**
     * « samedi 3 octobre 2026 à 14 h 32 », dans le fuseau de Nova Terra.
     */
    public static function dateLongue(?CarbonInterface $date): string
    {
        return $date === null ? '—' : $date->copy()->timezone(Annonce::FUSEAU)->locale('fr')->translatedFormat('l j F Y à G \h i');
    }

    /**
     * Notification à l'auteur : la cloche d'abord, l'e-mail ensuite (un échec d'envoi ne bloque jamais l'agent).
     */
    private function prevenirAuteur(Avis $avis): void
    {
        $auteur = User::query()->find($this->user_id);

        if ($auteur === null) {
            return;
        }

        DB::afterCommit(function () use ($auteur, $avis): void {
            $auteur->notifyNow($avis, ['database']);

            try {
                $auteur->notifyNow($avis, ['mail']);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function auditLabel(): string
    {
        return 'Avis #'.$this->id.' sur '.($this->service->nom ?? 'un service');
    }
}
