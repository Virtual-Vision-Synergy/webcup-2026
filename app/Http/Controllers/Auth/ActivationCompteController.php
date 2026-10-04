<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ComptesHabitants;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * F71 : première connexion d'un habitant dont le compte a été créé par un agent (sans e-mail).
 *
 * L'habitant saisit son identifiant (ou téléphone), le code d'activation imprimé sur sa fiche et choisit son code personnel.
 * Le code d'activation est à usage unique ; tentatives limitées par identifiant et par IP ; message d'erreur générique.
 */
class ActivationCompteController extends Controller
{
    use PasswordValidationRules;

    public const MESSAGE_REFUS = 'Identifiant ou code d\'activation incorrect, ou code déjà utilisé. Demandez un nouveau code à un agent.';

    private const MAX_TENTATIVES = 5;

    private const FENETRE_SECONDES = 900;

    public function create(): View
    {
        return view('pages::auth.activation');
    }

    public function store(Request $request, ComptesHabitants $comptes): RedirectResponse
    {
        $request->validate([
            'identifiant' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'password' => $this->passwordRules(),
        ]);

        $saisie = $request->string('identifiant')->trim()->lower()->toString();
        $cle = 'activation:'.sha1($saisie.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($cle) / 60));

            return back()->withInput($request->only('identifiant'))->withErrors([
                'code' => __('Trop de tentatives. Réessayez dans :n minute(s).', ['n' => $minutes]),
            ]);
        }

        $user = User::trouverPourConnexion($saisie);

        // Compte inconnu, désactivé ou code faux : même réponse générique (aucune information sur le compte).
        if ($user === null || ! $user->isActive() || ! $comptes->activer($user, $request->string('code')->toString(), $request->string('password')->toString())) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            return back()->withInput($request->only('identifiant'))->withErrors(['code' => __(self::MESSAGE_REFUS)]);
        }

        RateLimiter::clear($cle);

        Auth::login($user);
        $request->session()->regenerate();

        // Arrivée directe sur « Mon espace » (bouton « Nouvelle démarche ») : une démarche commence en 2 étapes.
        return redirect()->route('dashboard');
    }
}
