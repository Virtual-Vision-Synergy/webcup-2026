<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\LienConnexion;
use App\Models\User;
use App\Notifications\LienDeConnexion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * D02 : connexion sans mot de passe par lien envoyé par e-mail.
 *
 * - Demande : réponse identique que l'adresse existe ou non, limitée par e-mail + IP et par IP.
 * - Lien : URL signée temporaire (15 min) + jeton dont seule l'empreinte est en base, usage unique.
 * - L'ouverture du lien (GET) affiche une confirmation ; seule la validation (POST) consomme le lien,
 *   pour qu'un antivirus de messagerie qui ouvre les liens ne le « brûle » pas.
 * - Si la double authentification est active, le défi Fortify reste demandé ensuite.
 */
class LienConnexionController extends Controller
{
    public const MESSAGE_ENVOI = 'Si un compte correspond à cette adresse, un lien de connexion valable 15 minutes vient de lui être envoyé.';

    public const MESSAGE_REFUS = 'Ce lien de connexion est invalide, a expiré ou a déjà été utilisé. Demandez-en un nouveau.';

    private const MAX_PAR_EMAIL = 3;

    private const MAX_PAR_IP = 10;

    private const FENETRE_SECONDES = 600;

    public function create(): View
    {
        return view('pages::auth.login-link');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = $request->string('email')->lower()->toString();
        $cleEmail = 'lien-connexion:'.sha1($email.'|'.$request->ip());
        $cleIp = 'lien-connexion-ip:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($cleEmail, self::MAX_PAR_EMAIL) || RateLimiter::tooManyAttempts($cleIp, self::MAX_PAR_IP)) {
            $secondes = max(RateLimiter::availableIn($cleEmail), RateLimiter::availableIn($cleIp));

            return back()->withInput()->withErrors([
                'email' => 'Trop de demandes. Réessayez dans '.max(1, (int) ceil($secondes / 60)).' minute(s).',
            ]);
        }

        RateLimiter::hit($cleEmail, self::FENETRE_SECONDES);
        RateLimiter::hit($cleIp, self::FENETRE_SECONDES);

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->isActive()) {
            [$lien, $jeton] = LienConnexion::emettrePour($user);

            $url = URL::temporarySignedRoute(
                'login-link.show',
                $lien->expires_at,
                ['lien' => $lien->id, 'token' => $jeton],
            );

            $user->notify(new LienDeConnexion($url));
        }

        return back()->with('status', self::MESSAGE_ENVOI);
    }

    public function show(Request $request, string $lien): View|RedirectResponse
    {
        if ($this->lienValide($request, $lien) === null) {
            return $this->refus();
        }

        return view('pages::auth.login-link-confirm');
    }

    public function consume(Request $request, string $lien): Response
    {
        $lienConnexion = $this->lienValide($request, $lien);

        if ($lienConnexion === null || ! $lienConnexion->consommer()) {
            return $this->refus();
        }

        $user = $lienConnexion->user;

        if (! $user->isActive()) {
            return redirect()->route('login')->withErrors(['email' => EnsureAccountIsActive::MESSAGE]);
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    private function lienValide(Request $request, string $lien): ?LienConnexion
    {
        if (! $request->hasValidSignature() || ! ctype_digit($lien)) {
            return null;
        }

        $lienConnexion = LienConnexion::query()->with('user')->find((int) $lien);
        $jeton = $request->query('token');

        if ($lienConnexion === null || ! is_string($jeton) || ! $lienConnexion->correspondA($jeton) || ! $lienConnexion->estUtilisable()) {
            return null;
        }

        return $lienConnexion;
    }

    private function refus(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => self::MESSAGE_REFUS]);
    }
}
