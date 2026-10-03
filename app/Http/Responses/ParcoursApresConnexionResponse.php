<?php

namespace App\Http\Responses;

use App\Services\OnboardingProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirection après connexion, connexion 2FA et inscription : un habitant dont le parcours de prise en main (D12)
 * n'est ni terminé ni passé arrive sur /bienvenue ; tous les autres gardent le comportement Fortify habituel.
 */
class ParcoursApresConnexionResponse implements LoginResponse, RegisterResponse, TwoFactorLoginResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user && OnboardingProgress::pour($user)->doitAfficher()) {
            return redirect()->route('onboarding.show');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
