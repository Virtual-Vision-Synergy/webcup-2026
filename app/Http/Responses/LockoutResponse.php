<?php

namespace App\Http\Responses;

use App\Auth\LoginThrottle;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * F37 : connexion refusée pendant un blocage (même avec le bon mot de passe), avec le délai en français.
 * Même message que l'e-mail existe ou non.
 */
class LockoutResponse implements LockoutResponseContract
{
    public function __construct(private LoginThrottle $throttle) {}

    public function toResponse($request): Response
    {
        throw ValidationException::withMessages([
            Fortify::username() => LoginThrottle::lockoutMessage($this->throttle->availableIn($request)),
        ]);
    }
}
