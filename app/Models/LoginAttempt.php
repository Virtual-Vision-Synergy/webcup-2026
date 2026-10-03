<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LoginAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * F37 : journal des tentatives de connexion.
 *
 * Aucun champ n'est remplissable : les lignes sont créées uniquement par App\Services\LoginAttemptRecorder
 * (affectation explicite côté serveur), jamais à partir de la requête. Jamais de mot de passe stocké.
 *
 * @property int $id
 * @property string $email
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string|null $ip
 * @property string|null $user_agent
 * @property bool $successful
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
class LoginAttempt extends Model
{
    /** @use HasFactory<LoginAttemptFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    public const REASON_BAD_CREDENTIALS = 'bad_credentials';

    public const REASON_LOCKED_OUT = 'locked_out';

    public const REASON_DEACTIVATED = 'deactivated';

    /** @var array<string, string> */
    public const REASON_OPTIONS = [
        self::REASON_BAD_CREDENTIALS => 'Identifiants incorrects',
        self::REASON_LOCKED_OUT => 'Bloquée (trop d’essais)',
        self::REASON_DEACTIVATED => 'Compte désactivé',
    ];

    /** @var array<string, int> */
    public const PERIOD_OPTIONS = [
        '24h' => 1,
        '7j' => 7,
        '30j' => 30,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASON_OPTIONS[$this->reason ?? ''] ?? ($this->successful ? 'Connexion réussie' : 'Échec');
    }

    /**
     * Rétention limitée : `php artisan model:prune` (planifié chaque jour).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('security.login.retention_days')));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('successful', false);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSince(Builder $query, CarbonInterface $since): void
    {
        $query->where('created_at', '>=', $since);
    }
}
