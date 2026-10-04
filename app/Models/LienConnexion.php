<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Lien de connexion sans mot de passe (D02) : valable 15 minutes, à usage unique.
 * Seule l'empreinte du jeton est en base ; le jeton en clair n'existe que dans l'URL signée envoyée par e-mail.
 *
 * Aucun champ n'est remplissable : tout est assigné dans le code (emettrePour()), jamais depuis une requête.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $used_at
 * @property-read User $user
 */
class LienConnexion extends Model
{
    public const DUREE_MINUTES = 15;

    protected $table = 'liens_connexion';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    /**
     * Crée un lien pour l'utilisateur (les liens précédents non utilisés sont supprimés)
     * et retourne le jeton en clair, à placer dans l'URL signée.
     *
     * @return array{0: self, 1: string}
     */
    public static function emettrePour(User $user): array
    {
        static::query()->where('user_id', $user->id)->whereNull('used_at')->delete();

        $jeton = Str::random(64);

        $lien = new self;
        $lien->forceFill([
            'user_id' => $user->id,
            'token_hash' => self::empreinte($jeton),
            'expires_at' => now()->addMinutes(self::DUREE_MINUTES),
        ])->save();

        return [$lien, $jeton];
    }

    public function correspondA(string $jeton): bool
    {
        return hash_equals($this->token_hash, self::empreinte($jeton));
    }

    public function estUtilisable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Marque le lien comme utilisé de façon atomique : un seul appel concurrent peut réussir.
     */
    public function consommer(): bool
    {
        return static::query()
            ->whereKey($this->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]) === 1;
    }
}
