<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\RegenereInfosEssentielles;
use Carbon\CarbonInterface;
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
 * F104 : les alertes graves en cours sont reprises dans la page « Infos essentielles » (régénérée à chaque modification).
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
 * @property Carbon|null $impact_prevu_le Début estimé de la perturbation annoncée (F104), pour le compte à rebours.
 * @property bool $officiel Message officiel du Haut Conseil (F73) ; assigné dans le code, par un administrateur uniquement.
 * @property string|null $langage_clair Version en langage clair (F89), rédigée par l'agent.
 * @property Carbon|null $langage_clair_valide_le Validation de la version en langage clair (F89) ; assignée dans le code uniquement.
 * @property Carbon|null $notified_at Envoi de la notification aux habitants (F30) ; assigné par NotifierAnnonce uniquement.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Quartier|null $quartier
 *
 * user_id (l'auteur), officiel, langage_clair_valide_le et notified_at ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 */
#[Fillable(['titre', 'contenu', 'niveau', 'debut', 'fin', 'quartier_id', 'consignes', 'impact_prevu_le', 'langage_clair'])]
class Annonce extends Model
{
    /** @use HasFactory<AnnonceFactory> */
    use Auditable, HasAuditHistory, HasFactory, RegenereInfosEssentielles;

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

    /** Durée d'affichage d'un bandeau avant sa disparition automatique (pour la visite seulement). */
    public const DUREE_AFFICHAGE_SECONDES = 10;

    /** Fuseau de saisie et d'affichage pour les agents (l'application stocke en UTC). */
    public const FUSEAU = 'Indian/Antananarivo';

    public const CACHE_KEY = 'annonces.en-diffusion';

    /**
     * F101 : modèles prêts à l'emploi pour publier une alerte en quelques secondes depuis l'espace agent.
     * « :debut », « :impact » et « :fin » sont remplacés par les heures saisies (heure de Madagascar).
     * quartier null = toute la ville ; delai_minutes = délai estimé avant le début de la perturbation (F104), null si sans objet.
     *
     * @var array<string, array{libelle: string, icone: string, titre: string, niveau: string, quartier: string|null, duree_heures: int, delai_minutes: int|null, contenu: string, consignes: list<string>}>
     */
    public const MODELES = [
        'tempete-solaire' => [
            'libelle' => 'Tempête solaire',
            'icone' => 'sun',
            'titre' => 'Tempête solaire — communications perturbées',
            'niveau' => 'danger',
            'quartier' => null,
            'duree_heures' => 6,
            'delai_minutes' => 20,
            'contenu' => 'Ce qu’il faut savoir : une tempête solaire peut perturber les communications dans toute la ville à partir de :impact. Internet, le réseau mobile, le téléphone et le GPS peuvent être coupés par moments. Fin estimée de l’alerte : :fin.',
            'consignes' => [
                'Maintenant : terminez et enregistrez vos démarches en cours. Ce qui est déjà déposé reste enregistré.',
                'Notez sur papier les numéros d’urgence : 117 (police secours), 118 (sapeurs-pompiers), 124 (SAMU).',
                'Ce qui peut être coupé : Internet, le réseau mobile, le téléphone et le GPS.',
                'Ouvrez la page « Infos essentielles » tant que le réseau fonctionne : elle reste lisible hors ligne sur votre appareil.',
                'Convenez d’un point de rendez-vous avec vos proches et ne comptez pas sur le GPS pour vous déplacer.',
                'En cas de coupure : restez calme, gardez votre téléphone chargé et réessayez plus tard. Pour une urgence, allez au poste de police, à la caserne ou au centre de santé le plus proche.',
            ],
        ],
        'panne-electrique' => [
            'libelle' => 'Panne électrique',
            'icone' => 'bolt',
            'titre' => 'Panne électrique — secteur nord',
            'niveau' => 'alerte',
            'quartier' => 'nord',
            'duree_heures' => 6,
            'delai_minutes' => null,
            'contenu' => 'Ce qu’il faut savoir : une panne électrique touche le secteur nord depuis :debut. Fin estimée : :fin. Les équipes techniques sont sur place ; de nouvelles informations seront publiées ici.',
            'consignes' => [
                'Débranchez vos appareils sensibles (ordinateurs, télévision) pour éviter les dégâts au retour du courant.',
                'Gardez le réfrigérateur et le congélateur fermés : les aliments restent au froid environ 4 heures.',
                'Utilisez des lampes à piles plutôt que des bougies.',
                'Personne sous appareil médical électrique (oxygène, dialyse) : appelez le 124 (SAMU) ou consultez la page Urgences et santé.',
                'Ne touchez jamais un câble électrique tombé au sol : appelez le 118 (sapeurs-pompiers).',
            ],
        ],
    ];

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
                'annonces.officiel', 'annonces.quartier_id', 'annonces.debut', 'annonces.fin', 'annonces.impact_prevu_le', 'annonces.updated_at', 'quartiers.nom as quartier_nom',
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

    /**
     * F89 : la version en langage clair n'est montrée aux habitants qu'une fois rédigée ET validée.
     */
    public function langageClairPublie(): bool
    {
        return filled($this->langage_clair) && $this->langage_clair_valide_le !== null;
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
     * F101 : champs d'une alerte préparés à partir d'un modèle (null si le modèle n'existe pas).
     * Les heures sont écrites en heure de Madagascar dans le texte.
     * F104 : « impact_prevu_le » (début estimé de la perturbation) n'est présent que si le modèle prévoit un délai.
     *
     * @return array{titre: string, contenu: string, consignes: string, niveau: string, quartier_id: int|null, debut: CarbonInterface, fin: CarbonInterface, impact_prevu_le?: CarbonInterface}|null
     */
    public static function depuisModele(string $cle, ?CarbonInterface $debut = null, ?int $dureeHeures = null): ?array
    {
        $modele = self::MODELES[$cle] ?? null;

        if ($modele === null) {
            return null;
        }

        $debut = ($debut ?? now())->copy()->timezone(self::FUSEAU);
        $fin = $debut->copy()->addHours($dureeHeures ?? $modele['duree_heures']);

        $impact = $modele['delai_minutes'] === null ? null : $debut->copy()->addMinutes($modele['delai_minutes']);

        $champs = [
            'titre' => $modele['titre'],
            'contenu' => strtr($modele['contenu'], [
                ':debut' => self::heureLisible($debut),
                ':impact' => self::heureLisible($impact ?? $debut),
                ':fin' => self::heureLisible($fin),
            ]),
            'consignes' => implode("\n", $modele['consignes']),
            'niveau' => $modele['niveau'],
            'quartier_id' => $modele['quartier'] === null ? null : Quartier::idPour($modele['quartier']),
            'debut' => $debut,
            'fin' => $fin,
        ];

        if ($impact !== null) {
            $champs['impact_prevu_le'] = $impact;
        }

        return $champs;
    }

    /**
     * « samedi 4 octobre à 14 h 30 » (heure de Madagascar).
     */
    public static function heureLisible(CarbonInterface $date): string
    {
        return $date->copy()->timezone(self::FUSEAU)->translatedFormat('l j F \à G \h i');
    }

    /**
     * F101 : situation rétablie (ex. « courant rétabli ») : ligne horodatée ajoutée, puis fin de diffusion
     * 30 minutes plus tard pour que les habitants voient le retour à la normale avant que le bandeau disparaisse.
     */
    public function retablir(string $texte): void
    {
        $finRapprochee = now()->addMinutes(30);

        if ($this->fin->gt($finRapprochee)) {
            $this->setAttribute('fin', $finRapprochee);
        }

        $this->ajouterMiseAJour($texte);
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
            'impact_prevu_le' => 'datetime',
            'notified_at' => 'datetime',
            'langage_clair_valide_le' => 'datetime',
            'officiel' => 'boolean',
            'quartier_id' => 'integer',
        ];
    }
}
