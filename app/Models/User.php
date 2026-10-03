<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property-read Quartier|null $quartierResidence
 * @property-read Onboarding|null $onboarding
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * role_id et deactivated_at ne sont volontairement PAS remplissables : ils sont assignés dans le code
 * (inscription, admin, deactivate()/reactivate()).
 * L'ancienne colonne texte « role » existe encore en base mais n'est plus utilisée.
 */
#[Fillable(['name', 'email', 'password', 'telephone', 'quartier', 'quartier_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasAuditHistory, HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        ];
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
        $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
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
