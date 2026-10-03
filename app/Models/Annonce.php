<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\AnnonceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Message général diffusé en bandeau à tous les habitants (D18) pendant sa période de validité.
 * F29 : une annonce peut cibler un quartier (alerte ciblée) et porter des consignes à suivre, une par ligne.
 * Les dates sont stockées en UTC ; les agents les saisissent en heure de Madagascar (FUSEAU).
 *
 * @property int $id
 * @property int $user_id
 * @property string $titre
 * @property string $contenu
 * @property string $niveau
 * @property int|null $quartier_id
 * @property string|null $consignes
 * @property Carbon $debut
 * @property Carbon $fin
 * @property bool $officiel Message officiel du Haut Conseil (F73) ; assigné dans le code, par un administrateur uniquement.
 * @property Carbon|null $notified_at Envoi de la notification aux habitants (F30) ; assigné par NotifierAnnonce uniquement.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Quartier|null $quartier
 *
 * user_id (l'auteur), officiel et notified_at ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['titre', 'contenu', 'niveau', 'debut', 'fin', 'quartier_id', 'consignes'])]
class Annonce extends Model
{
    /** @use HasFactory<AnnonceFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    /** Du moins au plus grave. */
    public const NIVEAU_OPTIONS = ['information', 'vigilance', 'alerte', 'danger'];

    /** Libellé texte affiché avec la couleur (l'information ne repose jamais sur la seule couleur). */
    public const NIVEAU_LIBELLES = [
        'information' => 'Information',
        'vigilance' => 'Vigilance',
        'alerte' => 'Alerte',
        'danger' => 'Danger',
    ];

    /** Niveaux annoncés avec role="alert" ; dans le quartier concerné, le bandeau se replie mais ne se ferme pas. */
    public const NIVEAUX_GRAVES = ['alerte', 'danger'];

    /** Fuseau de saisie et d'affichage pour les agents (l'application stocke en UTC). */
    public const FUSEAU = 'Indian/Antananarivo';

    public const CACHE_KEY = 'annonces.en-diffusion';

    /**
     * Cookie (non chiffré, écrit par le navigateur) listant les messages fermés : « id-version,id-version ».
     * Lu côté serveur : un message fermé n'est plus rendu du tout, même après un changement de page.
     */
    public const COOKIE_FERMES = 'tn_annonces_fermees';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quartier ciblé ; null = toute la ville.
     *
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }

    /**
     * Messages en cours de diffusion : debut <= maintenant < fin.
     *
     * @param  Builder<Annonce>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('debut', '<=', now())->where('fin', '>', now());
    }

    /**
     * Messages à afficher dans le bandeau : les messages officiels du Haut Conseil (F73), puis ceux du quartier de l'habitant d'abord (F29),
     * puis du plus grave au moins grave, puis du plus récent au plus ancien.
     *
     * Le cache (court, invalidé à chaque création, modification ou suppression) contient les messages non expirés ;
     * le filtre sur les dates est refait à chaque affichage pour qu'un message programmé apparaisse pile à l'heure.
     * On met en cache des tableaux bruts, pas des objets : config/cache.php interdit de désérialiser des classes
     * (serializable_classes = false), les modèles sont donc reconstruits avec hydrate().
     *
     * @return Collection<int, Annonce>
     */
    public static function enDiffusion(?User $habitant = null): Collection
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = Cache::remember(self::CACHE_KEY, 60, fn (): array => self::query()
            ->leftJoin('quartiers', 'quartiers.id', '=', 'annonces.quartier_id')
            ->where('annonces.fin', '>', now())
            ->get([
                'annonces.id', 'annonces.titre', 'annonces.contenu', 'annonces.consignes', 'annonces.niveau',
                'annonces.officiel', 'annonces.quartier_id', 'annonces.debut', 'annonces.fin', 'annonces.updated_at', 'quartiers.nom as quartier_nom',
            ])
            ->map(fn (Annonce $annonce): array => $annonce->getAttributes())
            ->all());

        $maintenant = now();

        return self::hydrate($lignes)
            ->filter(fn (Annonce $annonce): bool => $annonce->debut->lte($maintenant) && $annonce->fin->gt($maintenant))
            ->sortBy([
                fn (Annonce $a, Annonce $b): int => $b->estOfficiel() <=> $a->estOfficiel(),
                fn (Annonce $a, Annonce $b): int => $b->concerne($habitant) <=> $a->concerne($habitant),
                fn (Annonce $a, Annonce $b): int => $b->gravite() <=> $a->gravite(),
                fn (Annonce $a, Annonce $b): int => $b->debut <=> $a->debut,
            ])
            ->values();
    }

    /**
     * Clés des messages fermés, lues dans le cookie (format strictement contrôlé, 50 au maximum).
     *
     * @return list<string>
     */
    public static function clesFermees(?string $cookie): array
    {
        $cles = array_filter(
            array_map(trim(...), explode(',', (string) $cookie)),
            fn (string $cle): bool => preg_match('/^\d{1,10}-\d{1,12}$/', $cle) === 1,
        );

        return array_slice(array_values($cles), -50);
    }

    public function gravite(): int
    {
        return (int) array_search($this->niveau, self::NIVEAU_OPTIONS, true);
    }

    public function libelleNiveau(): string
    {
        return __(self::NIVEAU_LIBELLES[$this->niveau] ?? ucfirst($this->niveau));
    }

    public function estGrave(): bool
    {
        return in_array($this->niveau, self::NIVEAUX_GRAVES, true);
    }

    /**
     * Message officiel du Haut Conseil (F73) : toujours affiché en premier, signé par l'institution.
     */
    public function estOfficiel(): bool
    {
        return (bool) $this->officiel;
    }

    public function estCiblee(): bool
    {
        return $this->quartier_id !== null;
    }

    /**
     * L'alerte vise le quartier de cet habitant (toujours faux pour un visiteur ou un message à toute la ville).
     */
    public function concerne(?User $habitant): bool
    {
        return $this->quartier_id !== null && $habitant?->quartier_id === $this->quartier_id;
    }

    public function nomQuartier(): ?string
    {
        if (! $this->estCiblee()) {
            return null;
        }

        return $this->getAttribute('quartier_nom') ?? $this->quartier?->nom;
    }

    /**
     * Ajoute au message une ligne horodatée (« Mise à jour 14 h 30 : … ») sans recréer l'alerte.
     * La date de modification change : le bandeau réapparaît chez ceux qui l'avaient fermé.
     */
    public function ajouterMiseAJour(string $texte): void
    {
        $heure = now(self::FUSEAU)->format('G \h i');
        $this->contenu = rtrim($this->contenu)."\nMise à jour {$heure} : ".trim($texte);
        $this->save();
    }

    /**
     * Consignes à suivre, une par ligne (lignes vides ignorées).
     *
     * @return list<string>
     */
    public function listeConsignes(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $this->consignes) ?: []),
            fn (string $ligne): bool => $ligne !== '',
        ));
    }

    /**
     * @return 'programme'|'en_cours'|'expire'
     */
    public function statut(): string
    {
        return match (true) {
            $this->debut->isFuture() => 'programme',
            $this->fin->lte(now()) => 'expire',
            default => 'en_cours',
        };
    }

    /**
     * Clé de fermeture (cookie) et de repli (navigateur) : un message modifié réapparaît.
     */
    public function cleFermeture(): string
    {
        return $this->id.'-'.($this->updated_at->timestamp ?? 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'notified_at' => 'datetime',
            'officiel' => 'boolean',
            'quartier_id' => 'integer',
        ];
    }
}
