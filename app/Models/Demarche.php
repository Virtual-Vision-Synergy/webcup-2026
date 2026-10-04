<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\HasConfidentialFields;
use App\Models\Concerns\PrevientDuChangementDeStatut;
use App\Notifications\Avis;
use App\Notifications\ReponseDemarcheRecue;
use App\Services\ParOuCommencer;
use Carbon\CarbonInterface;
use Database\Factories\DemarcheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Démarche administrative déposée par un habitant auprès d'un service municipal.
 * Visible uniquement par son auteur et par le personnel (agents, admins) : voir DemarchePolicy.
 *
 * user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 *
 * F70 : un agent ne voit que les démarches de ses services (scope visibleTo + DemarchePolicy) ;
 * les coordonnées et la situation du demandeur sont confidentielles (confidentialFields).
 *
 * F86 : urgence_medicale, pris_en_charge_par et pris_en_charge_le ne sont PAS remplissables :
 * l'urgence est déterminée dans le code (case cochée ou mots-clés), la prise en charge par prendreEnCharge().
 *
 * @property bool $urgence_medicale
 * @property int|null $pris_en_charge_par
 * @property CarbonInterface|null $pris_en_charge_le
 *
 * F80 : priorite et priorite_manuelle ne sont PAS remplissables : la priorité est suggérée par le code
 * (prioriteSuggeree) ou fixée par un agent (changerPriorite, policy changerPriorite).
 * @property string $priorite
 * @property bool $priorite_manuelle
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
    protected $attributes = ['statut' => self::STATUT_OPTIONS[0], 'urgence_medicale' => false, 'priorite' => 'normale', 'priorite_manuelle' => false];

    /** F80 : priorités, de la plus basse à la plus haute. */
    public const PRIORITE_OPTIONS = ['basse', 'normale', 'haute', 'urgente'];

    /** @var array<string, string> */
    public const PRIORITE_LABELS = ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'];

    /** @var array<string, string> État du badge Terra Nova (couleur + icône). */
    public const PRIORITE_ETATS = ['basse' => 'normal', 'normale' => 'info', 'haute' => 'perturbe', 'urgente' => 'alerte'];

    /** @var array<string, string> */
    public const PRIORITE_ICONES = ['basse' => 'arrow-down', 'normale' => 'minus', 'haute' => 'arrow-up', 'urgente' => 'fire'];

    /** F80 : catégories de service sensibles (priorité haute suggérée). */
    public const CATEGORIES_SENSIBLES = ['sante', 'social', 'securite'];

    /** F80 : au-delà de ce délai sans clôture, la priorité haute est suggérée. */
    public const JOURS_AVANT_PRIORITE_HAUTE = 7;

    /**
     * F86 : mots-clés qui signalent une urgence médicale (comparés sans accents ni majuscules, mots entiers).
     *
     * @var array<int, string>
     */
    public const MOTS_CLES_URGENCE = [
        'urgence medicale', 'urgence vitale', 'malaise', 'inconscient', 'inconsciente', 'evanoui', 'evanouie',
        'ne respire plus', 'respire mal', 'etouffe', 'arret cardiaque', 'crise cardiaque', 'infarctus', 'avc',
        'hemorragie', 'saigne beaucoup', 'convulsion', 'convulsions', 'overdose', 'intoxication', 'douleur thoracique',
        'douleur a la poitrine', 'blesse grave', 'blessee grave', 'accouchement', 'ambulance', 'samu',
    ];

    /** F86 : états où une urgence médicale reste « à traiter en priorité ». */
    public const STATUTS_URGENCE_OUVERTE = ['deposee', 'en_cours'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgence_medicale' => 'boolean',
            'pris_en_charge_le' => 'datetime',
            'priorite_manuelle' => 'boolean',
        ];
    }

    /**
     * F80 : tant qu'aucun agent ne l'a fixée, la priorité suit la suggestion (dépôt, changement d'état, d'urgence ou de service).
     */
    protected static function booted(): void
    {
        static::saving(function (Demarche $demarche): void {
            if (! $demarche->priorite_manuelle && (! $demarche->exists || $demarche->isDirty(['statut', 'urgence_medicale', 'service_id']))) {
                $demarche->priorite = $demarche->prioriteSuggeree()['priorite'];
            }
        });
    }

    public static function libellePriorite(string $priorite): string
    {
        return __(self::PRIORITE_LABELS[$priorite] ?? ucfirst($priorite));
    }

    /**
     * F80 : priorité suggérée et sa raison. L'urgence médicale (F86) l'emporte toujours ;
     * une relance de l'habitant restée sans réponse dans le fil (F84) remonte le dossier.
     *
     * @return array{priorite: string, motif: string}
     */
    public function prioriteSuggeree(): array
    {
        $ouverte = in_array($this->statut, self::STATUTS_URGENCE_OUVERTE, true);
        $derniere = $this->exists ? $this->derniereReponse : null;
        $categorie = $this->service_id !== null ? $this->service?->categorie : null;

        return match (true) {
            $this->urgence_medicale && $ouverte => ['priorite' => 'urgente', 'motif' => __('Urgence médicale signalée')],
            ! $ouverte => ['priorite' => 'basse', 'motif' => __('Dossier clôturé')],
            $derniere !== null && ! $derniere->de_agent => ['priorite' => 'haute', 'motif' => __('Relance de l’habitant sans réponse')],
            $this->created_at !== null && $this->created_at->lte(now()->subDays(self::JOURS_AVANT_PRIORITE_HAUTE)) => ['priorite' => 'haute', 'motif' => __('En attente depuis plus de :n jours', ['n' => self::JOURS_AVANT_PRIORITE_HAUTE])],
            in_array($categorie, self::CATEGORIES_SENSIBLES, true) => ['priorite' => 'haute', 'motif' => __('Service sensible (santé, social, sécurité)')],
            default => ['priorite' => 'normale', 'motif' => __('Aucun critère particulier')],
        };
    }

    /**
     * F80 : priorité fixée par un agent (droits vérifiés avant : policy changerPriorite), ou retour à la suggestion (« auto »).
     */
    public function changerPriorite(string $priorite): void
    {
        if ($priorite === 'auto') {
            $this->priorite_manuelle = false;
            $this->priorite = $this->prioriteSuggeree()['priorite'];
        } elseif (in_array($priorite, self::PRIORITE_OPTIONS, true)) {
            $this->priorite_manuelle = true;
            $this->priorite = $priorite;
        } else {
            throw new \InvalidArgumentException("Priorité inconnue : {$priorite}");
        }

        $this->save();
    }

    /**
     * F80 : recalcule la priorité suggérée sans toucher à la date de mise à jour ni au journal (fil F84, ancienneté).
     */
    public function recalculerPriorite(): void
    {
        if ($this->priorite_manuelle) {
            return;
        }

        $suggestion = $this->prioriteSuggeree()['priorite'];

        if ($suggestion !== $this->priorite) {
            static::query()->whereKey($this->getKey())->toBase()->update(['priorite' => $suggestion]);
            $this->priorite = $suggestion;
            $this->syncOriginalAttribute('priorite');
        }
    }

    /**
     * F80 : ordre de traitement. Les urgences médicales ouvertes (F86) restent toujours tout en haut,
     * puis les dossiers ouverts par priorité, puis par ancienneté.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdreDeTraitement(Builder $query): void
    {
        $query
            ->orderByRaw("case when urgence_medicale = 1 and statut in ('deposee', 'en_cours') then 0 else 1 end")
            ->orderByRaw("case when statut in ('deposee', 'en_cours') then 0 else 1 end")
            ->orderByRaw("case priorite when 'urgente' then 0 when 'haute' then 1 when 'normale' then 2 else 3 end")
            ->oldest()
            ->oldest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * F86 : agent ou admin qui a pris en charge l'urgence médicale.
     *
     * @return BelongsTo<User, $this>
     */
    public function prisEnChargePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pris_en_charge_par');
    }

    /**
     * F86 : vrai si le texte évoque une urgence médicale (mots-clés, sans accents ni majuscules).
     */
    public static function detecterUrgenceMedicale(string ...$textes): bool
    {
        $texte = ' '.trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii(implode(' ', $textes))))).' ';

        foreach (self::MOTS_CLES_URGENCE as $motCle) {
            if (str_contains($texte, ' '.$motCle.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * F86 : urgences médicales encore ouvertes (déposées ou en cours) : la vue « À traiter en priorité ».
     *
     * @param  Builder<self>  $query
     */
    public function scopeUrgencesATraiter(Builder $query): void
    {
        $query->where('urgence_medicale', true)->whereIn('statut', self::STATUTS_URGENCE_OUVERTE);
    }

    public function estUrgenceOuverte(): bool
    {
        return $this->urgence_medicale && in_array($this->statut, self::STATUTS_URGENCE_OUVERTE, true);
    }

    /**
     * F86 : prise en charge d'une urgence (droits vérifiés avant : policy prendreEnCharge).
     * Trace qui et quand, passe la demande « en cours » (l'habitant est prévenu). Sans effet si déjà prise en charge.
     */
    public function prendreEnCharge(User $agent): bool
    {
        if ($this->pris_en_charge_le !== null) {
            return false;
        }

        $this->prisEnChargePar()->associate($agent);
        $this->pris_en_charge_le = now();

        $this->changerStatut('en_cours');

        return true;
    }

    /**
     * F86 : prévient tout de suite les agents du service concerné et les admins (cloche immédiate, e-mail par la file).
     */
    public function alerterUrgenceMedicale(): void
    {
        $destinataires = User::query()
            ->whereNull('deactivated_at')
            ->where(fn (Builder $query) => $query
                ->where('role_id', Role::idFor(Role::ADMIN))
                ->when($this->service_id !== null, fn (Builder $q) => $q->orWhere(fn (Builder $agent) => $agent
                    ->where('role_id', Role::idFor(Role::AGENT))
                    ->whereHas('services', fn (Builder $service) => $service->whereKey($this->service_id)))))
            ->get();

        $avis = new Avis(
            __('Urgence médicale signalée : « :demande »', ['demande' => Str::limit((string) $this->titre, 80)]),
            [
                __('Un habitant vient de déposer une demande signalée comme urgence médicale.'),
                __('Elle est placée en tête de la liste « À traiter en priorité » : prenez-la en charge sans attendre.'),
            ],
            __('Ouvrir la demande'),
            route('demarches.show', $this),
        );

        DB::afterCommit(function () use ($destinataires, $avis): void {
            // Avis : cloche enregistrée tout de suite, e-mail par la file. Un destinataire à la fois :
            // un échec d'envoi ne prive pas les suivants de l'alerte et ne bloque jamais le dépôt de l'habitant.
            foreach ($destinataires as $destinataire) {
                try {
                    Notification::send($destinataire, $avis);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
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

        // F80 : une relance de l'habitant remonte la priorité suggérée ; la réponse d'un agent la fait redescendre.
        $this->unsetRelation('derniereReponse')->recalculerPriorite();

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
