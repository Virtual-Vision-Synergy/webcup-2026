<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KnownDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F54 : appareil depuis lequel un utilisateur s'est déjà connecté.
 *
 * Aucun champ n'est remplissable : tout est écrit uniquement par App\Services\DeviceRecognizer
 * (affectation explicite côté serveur), jamais à partir de la requête.
 * Seule l'empreinte SHA-256 du jeton du cookie est en base ; ni IP complète ni user-agent brut.
 *
 * @property int $id
 * @property int $user_id
 * @property-read User $user
 * @property string $device_token_hash
 * @property string $browser
 * @property string $os
 * @property string $device_type
 * @property string|null $ip_approx
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class KnownDevice extends Model
{
    /** @use HasFactory<KnownDeviceFactory> */
    use HasFactory, MassPrunable;

    /** Les dates sont stockées en UTC et affichées à l'heure de Nova Terra. */
    public const FUSEAU = Annonce::FUSEAU;

    public const TYPE_ORDINATEUR = 'ordinateur';

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_TABLETTE = 'tablette';

    /** @var array<int, string> */
    public const TYPE_OPTIONS = [self::TYPE_ORDINATEUR, self::TYPE_MOBILE, self::TYPE_TABLETTE];

    /** Appareils inactifs depuis plus de N mois purgés par `php artisan model:prune`. */
    public const RETENTION_MOIS = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * « Firefox sur Android (mobile) ».
     */
    public function libelleAppareil(): string
    {
        return $this->browser.' sur '.$this->os.' ('.$this->device_type.')';
    }

    /**
     * Adresse approximative pour l'affichage : « 41.188.x.x » (IPv4) ou le préfixe /48 (IPv6).
     */
    public function ipAffichee(): string
    {
        return $this->ip_approx === null ? 'inconnue' : self::ipPourAffichage($this->ip_approx);
    }

    public static function ipPourAffichage(string $ipApprox): string
    {
        if (preg_match('/^(\d{1,3})\.(\d{1,3})\./', $ipApprox, $m) === 1) {
            return $m[1].'.'.$m[2].'.x.x';
        }

        return $ipApprox;
    }

    /**
     * « 3 oct. 2026 à 14 h 32 » à l'heure de Nova Terra.
     */
    public static function dateLisible(?CarbonInterface $date): string
    {
        return $date?->timezone(self::FUSEAU)->locale('fr')->translatedFormat('j M Y \à G \h i') ?? '—';
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('last_seen_at', '<', now()->subMonths(self::RETENTION_MOIS));
    }
}
