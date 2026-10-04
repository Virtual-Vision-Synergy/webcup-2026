<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use App\Models\Concerns\HasConfidentialFields;
use App\Services\AuditLogger;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int $role_id
 * @property-read Role $role
 * @property string $name
 * @property string $email
 * @property string|null $telephone
 * @property string|null $quartier Ancienne saisie libre (D12), tenue à jour avec le nom du quartier choisi.
 * @property int|null $quartier_id
 * @property string|null $profil_canicule F31 : profil choisi pour les conseils canicule ; assigné dans le code (jamais en masse).
 * @property-read Quartier|null $quartierResidence
 * @property-read Onboarding|null $onboarding
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $verrouille_jusqu_au Verrouillage temporaire posé par un admin (F85).
 * @property bool $notifier_par_email Préférence de l'habitant (F30) : annonces urgentes par e-mail.
 * @property string|null $identifiant Identifiant d'habitant (F71) pour se connecter sans e-mail.
 * @property string|null $code_activation Empreinte du code d'activation à usage unique (F71).
 * @property string|null $langue Langue mémorisée (F71).
 * @property bool $mode_allege Mode allégé pour les connexions lentes (F59).
 * @property bool $version_simple Version simple des pages clés (F62).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * role_id, deactivated_at et verrouille_jusqu_au ne sont volontairement PAS remplissables : ils sont assignés dans le code
 * (inscription, admin, deactivate()/reactivate()).
 * L'ancienne colonne texte « role » existe encore en base mais n'est plus utilisée.
 */
#[Fillable(['name', 'email', 'password', 'telephone', 'quartier', 'quartier_id', 'notifier_par_email'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'code_activation'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasAuditHistory, HasConfidentialFields, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** F71 : domaine réservé (RFC 2606) des adresses techniques des comptes sans e-mail ; aucun message n'y part. */
    public const DOMAINE_SANS_EMAIL = 'sans-email.invalid';

    /** @var list<string> Champs non journalisés (F47) : l'empreinte du code d'activation reste hors du journal. */
    protected array $auditIgnore = ['code_activation'];

    /** F70 : champs jamais affichés en clair dans le journal (F47/F48), remplacés par « [masqué] ». */
    public const CHAMPS_CONFIDENTIELS = ['telephone', 'email'];

    /**
     * F70 : coordonnées d'un habitant, masquées par défaut sur sa fiche dans l'espace agent (F34).
     *
     * @return array<string, array{label: string, valeur: \Closure(): (string|null)}>
     */
    public function confidentialFields(): array
    {
        return [
            'telephone' => ['label' => 'Téléphone', 'valeur' => fn (): ?string => $this->telephone],
            'email' => ['label' => 'E-mail', 'valeur' => fn (): ?string => $this->emailAffichable()],
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quartier_id' => 'integer',
            'deactivated_at' => 'datetime',
            'verrouille_jusqu_au' => 'datetime',
            'notifier_par_email' => 'boolean',
            'mode_allege' => 'boolean',
            'version_simple' => 'boolean',
        ];
    }

    /**
     * F95 : nombre de notifications non lues, compté une seule fois par requête HTTP
     * (les deux cloches et la page des notifications affichent le même chiffre).
     */
    public function nombreNotificationsNonLues(): int
    {
        $requete = request();
        $cle = 'tn.notifications-non-lues.'.$this->getKey();

        if (! $requete->attributes->has($cle)) {
            $requete->attributes->set($cle, $this->unreadNotifications()->count());
        }

        return (int) $requete->attributes->get($cle);
    }

    /**
     * À appeler après avoir marqué des notifications comme lues dans la même requête.
     */
    public function oublierNotificationsNonLues(): void
    {
        request()->attributes->remove('tn.notifications-non-lues.'.$this->getKey());
    }

    /**
     * F95 : l'habitant a-t-il déjà déposé une démarche ? Un « oui » est retenu pour la requête HTTP en cours
     * (le tableau de bord posait la question deux fois) ; un « non » est toujours revérifié.
     */
    public function aCommenceUneDemarche(): bool
    {
        $requete = request();
        $cle = 'tn.demarche-commencee.'.$this->getKey();

        if ($requete->attributes->get($cle) === true) {
            return true;
        }

        $commencee = $this->demarches()->exists();

        if ($commencee) {
            $requete->attributes->set($cle, true);
        }

        return $commencee;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * F71 : vrai si l'habitant a une vraie adresse e-mail (et non l'adresse technique d'un compte sans e-mail).
     */
    public function aUnEmail(): bool
    {
        return ! str_ends_with($this->email, '@'.self::DOMAINE_SANS_EMAIL);
    }

    /**
     * Adresse affichable : null pour un compte sans e-mail.
     */
    public function emailAffichable(): ?string
    {
        return $this->aUnEmail() ? $this->email : null;
    }

    /**
     * Aucun e-mail n'est envoyé à un compte sans adresse (le canal mail est alors ignoré par Laravel).
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->emailAffichable();
    }

    public function aActiverCompte(): bool
    {
        return $this->code_activation !== null;
    }

    /**
     * F71 : retrouve un compte à partir de ce que l'habitant saisit à la connexion :
     * adresse e-mail, identifiant d'habitant (HAB-XXXXXX) ou numéro de téléphone (s'il n'appartient qu'à un seul compte).
     */
    public static function trouverPourConnexion(string $saisie): ?self
    {
        $saisie = trim($saisie);

        if ($saisie === '') {
            return null;
        }

        if (str_contains($saisie, '@')) {
            return self::where('email', mb_strtolower($saisie))->first();
        }

        if (preg_match('/^hab-?[a-z0-9]{4,12}$/i', $saisie) === 1) {
            return self::where('identifiant', self::normaliserIdentifiant($saisie))->first();
        }

        $telephone = self::normaliserTelephone($saisie);

        if ($telephone === null) {
            return null;
        }

        $comptes = self::where('telephone', $telephone)->limit(2)->get();

        return $comptes->count() === 1 ? $comptes->first() : null;
    }

    public static function normaliserIdentifiant(string $identifiant): string
    {
        $brut = strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $identifiant));

        return 'HAB-'.substr($brut, 3);
    }

    /**
     * Téléphone réduit aux chiffres (et au « + » initial) : « 034 12 345 67 » → « 0341234567 ».
     */
    public static function normaliserTelephone(?string $telephone): ?string
    {
        $telephone = trim((string) $telephone);
        $chiffres = (str_starts_with($telephone, '+') ? '+' : '').preg_replace('/\D/', '', $telephone);

        return strlen(ltrim($chiffres, '+')) >= 6 ? $chiffres : null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /**
     * L'ancienne colonne texte « role » existe encore en base : sans ceci, $user->role
     * renverrait cette chaîne au lieu de la relation vers Role.
     */
    public function getAttribute($key): mixed
    {
        return $key === 'role' ? $this->getRelationValue('role') : parent::getAttribute($key);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Démarches déposées par l'habitant (espace personnel).
     *
     * @return HasMany<Demarche, $this>
     */
    public function demarches(): HasMany
    {
        return $this->hasMany(Demarche::class);
    }

    /**
     * Quartier choisi par l'habitant dans son profil (F29) : sert au ciblage des alertes.
     *
     * @return BelongsTo<Quartier, $this>
     */
    public function quartierResidence(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'quartier_id');
    }

    /**
     * État du parcours de prise en main (D12). Champs réservés : modifiés uniquement par OnboardingProgress.
     *
     * @return HasOne<Onboarding, $this>
     */
    public function onboarding(): HasOne
    {
        return $this->hasOne(Onboarding::class);
    }

    /**
     * Appareils depuis lesquels l'utilisateur s'est connecté (F54). Écrits uniquement par DeviceRecognizer.
     *
     * @return HasMany<KnownDevice, $this>
     */
    public function knownDevices(): HasMany
    {
        return $this->hasMany(KnownDevice::class);
    }

    /**
     * F85 : événements de sécurité concernant ce compte. Écrits uniquement par SurveillanceSecurite.
     *
     * @return HasMany<SecurityEvent, $this>
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    /**
     * F70 : services couverts par un agent. Affectation réservée à l'admin (UserPolicy::assignServices).
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    /** @var list<int>|null Ids des services couverts, mis en cache pour la durée de la requête. */
    private ?array $serviceIdsEnCache = null;

    /**
     * Ids des services couverts par l'agent (vide pour un citoyen).
     *
     * @return list<int>
     */
    public function serviceIds(): array
    {
        if (! $this->isAgent()) {
            return [];
        }

        return $this->serviceIdsEnCache ??= array_values(array_map(intval(...), $this->services()->pluck('services.id')->all()));
    }

    /**
     * Change les services couverts (à appeler après l'autorisation) et le note au journal (F47).
     *
     * @param  list<int>  $serviceIds
     */
    public function affecterServices(array $serviceIds): void
    {
        $avant = $this->services()->orderBy('nom')->pluck('nom')->implode(', ');

        $this->services()->sync(Service::query()->whereKey($serviceIds)->pluck('id')->all());
        $this->serviceIdsEnCache = null;

        $apres = $this->services()->orderBy('nom')->pluck('nom')->implode(', ');

        if ($avant !== $apres) {
            AuditLogger::log('services_changed', $this, ['services' => ['avant' => $avant ?: null, 'apres' => $apres ?: null]]);
        }
    }

    /**
     * F70 : l'utilisateur peut-il traiter les données de ce service ? Admin : tout ; agent : ses services ;
     * une donnée sans service est réservée à l'admin ; citoyen : jamais (côté agent).
     */
    public function canAccessService(Service|int|null $service): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $serviceId = $service instanceof Service ? $service->id : $service;

        return $serviceId !== null && in_array((int) $serviceId, $this->serviceIds(), true);
    }

    public function hasRole(string $code): bool
    {
        return $this->role_id === Role::idFor($code);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isAgent(): bool
    {
        return $this->hasRole(Role::AGENT);
    }

    public function isCitoyen(): bool
    {
        return $this->hasRole(Role::CITOYEN);
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Désactive le compte : il ne peut plus se connecter (voir EnsureAccountIsActive et FortifyServiceProvider).
     * Droits vérifiés par UserPolicy::deactivate.
     */
    public function deactivate(): void
    {
        $this->forceFill(['deactivated_at' => now()])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill(['deactivated_at' => null])->save();
    }

    /**
     * F85 : compte verrouillé temporairement par un admin (le verrou se lève tout seul à l'échéance).
     */
    public function estVerrouille(): bool
    {
        return $this->verrouille_jusqu_au !== null && $this->verrouille_jusqu_au->isFuture();
    }

    /**
     * F85 : message affiché à la connexion ou à la déconnexion forcée d'un compte verrouillé.
     */
    public function messageVerrouillage(): string
    {
        $fin = $this->verrouille_jusqu_au?->copy()->timezone(Annonce::FUSEAU)->format('d/m/Y à H:i') ?? '';

        return 'Par sécurité, votre compte est temporairement verrouillé jusqu’au '.$fin.' (heure de Nova Terra). '
            .'Contactez la mairie de Nova Terra si vous pensez qu’il s’agit d’une erreur.';
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeCitizens(Builder $query): void
    {
        $query->where('role_id', Role::idFor(Role::CITOYEN));
    }

    /**
     * Comptes qu'un agent ou un admin peut administrer : citoyens pour un agent, citoyens et agents pour un admin.
     *
     * @param  Builder<User>  $query
     */
    public function scopeManageableBy(Builder $query, User $actor): void
    {
        $roleIds = match (true) {
            $actor->isAdmin() => [Role::idFor(Role::CITOYEN), Role::idFor(Role::AGENT)],
            $actor->isAgent() => [Role::idFor(Role::CITOYEN)],
            default => [],
        };

        $query->whereIn('role_id', $roleIds);
    }

    /**
     * Recherche par nom ou e-mail (les jokers % et _ saisis sont traités comme du texte).
     *
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('identifiant', 'like', $like)
            ->orWhere('telephone', 'like', $like));
    }

    /**
     * Seul point de passage pour changer un rôle (droits vérifiés par UserPolicy::updateRole).
     *
     * @throws \DomainException si on retire le rôle admin au dernier administrateur
     */
    public function changerRole(Role $role): void
    {
        $adminId = Role::idFor(Role::ADMIN);

        if ($this->role_id === $adminId && $role->id !== $adminId
            && self::where('role_id', $adminId)->count() <= 1) {
            throw new \DomainException('Impossible de retirer le rôle du dernier administrateur.');
        }

        $this->role()->associate($role);
        $this->save();
    }
}
