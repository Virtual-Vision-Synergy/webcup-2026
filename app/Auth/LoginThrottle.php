<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * F37 : limiteur de connexion utilisé par Fortify (lié à la place de LoginRateLimiter dans FortifyServiceProvider).
 *
 * Deux compteurs, incrémentés uniquement sur ÉCHEC :
 *  - couple e-mail + IP (5 échecs → 15 min) : le vrai titulaire peut toujours se connecter depuis un autre réseau ;
 *  - IP seule, plus large (20 échecs → 10 min) : une même source qui essaie plusieurs comptes.
 * Une connexion réussie remet à zéro le compteur e-mail + IP (PrepareAuthenticatedSession appelle clear()).
 */
class LoginThrottle extends LoginRateLimiter
{
    public static function pairKey(string $email, string $ip): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email))).'|'.$ip;
    }

    public static function ipKey(string $ip): string
    {
        return 'login-ip:'.$ip;
    }

    /**
     * Délai lisible en français : « 45 secondes », « 1 minute », « 12 minutes ».
     */
    public static function humanDelay(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return $seconds.' '.($seconds > 1 ? 'secondes' : 'seconde');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.($minutes > 1 ? 'minutes' : 'minute');
    }

    public static function lockoutMessage(int $seconds): string
    {
        return 'Trop de tentatives de connexion. Par sécurité, réessayez dans '.self::humanDelay($seconds).'.';
    }

    public function attempts(Request $request): int
    {
        return (int) $this->limiter->attempts($this->pairKeyFor($request));
    }

    public function tooManyAttempts(Request $request): bool
    {
        return $this->limiter->tooManyAttempts($this->pairKeyFor($request), $this->maxAttempts())
            || $this->limiter->tooManyAttempts(self::ipKey((string) $request->ip()), $this->ipMaxAttempts());
    }

    public function increment(Request $request): void
    {
        $this->limiter->hit($this->pairKeyFor($request), (int) config('security.login.decay_minutes') * 60);
        $this->limiter->hit(self::ipKey((string) $request->ip()), (int) config('security.login.ip_decay_minutes') * 60);
    }

    /**
     * Secondes avant de pouvoir réessayer : le plus long des blocages en cours.
     */
    public function availableIn(Request $request): int
    {
        $seconds = 0;

        if ($this->limiter->tooManyAttempts($this->pairKeyFor($request), $this->maxAttempts())) {
            $seconds = $this->limiter->availableIn($this->pairKeyFor($request));
        }

        $ipKey = self::ipKey((string) $request->ip());

        if ($this->limiter->tooManyAttempts($ipKey, $this->ipMaxAttempts())) {
            $seconds = max($seconds, $this->limiter->availableIn($ipKey));
        }

        return $seconds;
    }

    /**
     * Connexion réussie : seul le compteur e-mail + IP repart à zéro (le compteur IP protège les autres comptes).
     */
    public function clear(Request $request): void
    {
        $this->limiter->clear($this->pairKeyFor($request));
    }

    /**
     * Essais restants avant blocage du couple e-mail + IP.
     */
    public function remaining(Request $request): int
    {
        return max(0, $this->maxAttempts() - $this->attempts($request));
    }

    /**
     * Déblocage manuel (bouton admin de l'écran sécurité, ou démo).
     */
    public function unlock(string $email, string $ip): void
    {
        $this->limiter->clear(self::pairKey($email, $ip));
        $this->limiter->clear(self::ipKey($ip));
    }

    protected function throttleKey(Request $request): string
    {
        return $this->pairKeyFor($request);
    }

    private function pairKeyFor(Request $request): string
    {
        return self::pairKey((string) $request->input(Fortify::username()), (string) $request->ip());
    }

    private function maxAttempts(): int
    {
        return (int) config('security.login.max_attempts');
    }

    private function ipMaxAttempts(): int
    {
        return (int) config('security.login.ip_max_attempts');
    }
}
