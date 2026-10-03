<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * role_id n'est volontairement PAS remplissable : il est assigné dans le code (inscription, admin).
 * L'ancienne colonne texte « role » existe encore en base mais n'est plus utilisée.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
