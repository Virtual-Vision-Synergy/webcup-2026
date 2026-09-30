<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Limite une action sensible (IA, envoi, export…) par utilisateur connecté (par IP à défaut).
 *
 *   $this->throttlePerUser('ia', maxAttempts: 5, decaySeconds: 60);
 *
 * Au-delà de la limite : ValidationException sur la clé `throttle`
 * (affichable avec `@error('throttle') {{ $message }} @enderror`).
 */
trait ThrottlesPerUser
{
    protected function throttlePerUser(string $action, int $maxAttempts = 5, int $decaySeconds = 60): void
    {
        $key = 'throttle:'.$action.':'.(Auth::id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'throttle' => __('Trop de demandes. Réessayez dans :seconds secondes.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
