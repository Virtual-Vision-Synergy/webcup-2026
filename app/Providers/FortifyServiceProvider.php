<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Auth\LoginThrottle;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\IgnorerEmailVide;
use App\Http\Middleware\ProtegerFormulaireContreRobots;
use App\Http\Responses\LockoutResponse;
use App\Http\Responses\ParcoursApresConnexionResponse;
use App\Models\LoginAttempt;
use App\Models\TentativeBloquee;
use App\Models\User;
use App\Services\LoginAttemptRecorder;
use App\Services\ProtectionFormulaires;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LockoutResponse as LockoutResponseContract;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Parcours de prise en main (D12) : redirige les nouveaux habitants vers /bienvenue.
        $this->app->singleton(LoginResponse::class, ParcoursApresConnexionResponse::class);
        $this->app->singleton(RegisterResponse::class, ParcoursApresConnexionResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, ParcoursApresConnexionResponse::class);

        // F37 : limiteur e-mail + IP et IP seule, échecs uniquement, seuils dans config/security.php.
        $this->app->singleton(LoginThrottle::class);
        $this->app->alias(LoginThrottle::class, LoginRateLimiter::class);
        $this->app->singleton(LockoutResponseContract::class, LockoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // F34 : identifiants vérifiés d'abord (pas d'énumération), puis refus explicite si le compte est désactivé.
        // F37 : le blocage est vérifié AVANT ce rappel (EnsureLoginIsNotThrottled) ; ici on compte les échecs.
        // Ce rappel sert aussi au parcours 2FA (RedirectIfTwoFactorAuthenticatable).
        Fortify::authenticateUsing(function (Request $request): User {
            // F71 : e-mail, identifiant d'habitant ou numéro de téléphone dans le même champ.
            $email = (string) $request->input(Fortify::username());
            $user = User::trouverPourConnexion($email);

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                $this->failLogin($request, $email, $user);
            }

            if (! $user->isActive()) {
                app(LoginAttemptRecorder::class)->record($request, $email, $user, successful: false, reason: LoginAttempt::REASON_DEACTIVATED);

                throw ValidationException::withMessages([Fortify::username() => EnsureAccountIsActive::MESSAGE]);
            }

            return $user;
        });
    }

    /**
     * Échec de connexion : journal (sans mot de passe), compteur, puis message générique ou de blocage.
     * Même message que le compte existe ou non.
     *
     * @throws ValidationException
     */
    private function failLogin(Request $request, string $email, ?User $user): never
    {
        event(new Failed(config('fortify.guard'), $user, [Fortify::username() => $email]));

        $throttle = app(LoginThrottle::class);
        $throttle->increment($request);

        if ($throttle->tooManyAttempts($request)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                Fortify::username() => LoginThrottle::lockoutMessage($throttle->availableIn($request)),
            ]);
        }

        $message = trans('auth.failed');
        $remaining = $throttle->remaining($request);

        if ($remaining <= (int) config('security.login.warn_remaining')) {
            $message .= ' Il vous reste '.$remaining.' '.($remaining > 1 ? 'tentatives' : 'tentative').' avant un blocage temporaire.';
        }

        throw ValidationException::withMessages([Fortify::username() => $message]);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     *
     * La connexion n'utilise plus de limiteur de route (`fortify.limiters.login = null`) : c'est LoginThrottle,
     * appelé dans le pipeline Fortify, qui ne compte que les échecs.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // F37 : inscription et « mot de passe oublié » limités par IP (routes enregistrées par Fortify).
        // F81 : au-delà, réponse 429 en français avec le délai, et blocage journalisé (visible dans /admin).
        RateLimiter::for('auth-sensible', function (Request $request) {
            return Limit::perMinute((int) config('security.sensitive_routes_per_minute'))
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    $formulaire = $request->routeIs('register.store')
                        ? TentativeBloquee::FORMULAIRE_INSCRIPTION
                        : TentativeBloquee::FORMULAIRE_MOT_DE_PASSE;
                    app(ProtectionFormulaires::class)->journaliser($formulaire, TentativeBloquee::MOTIF_DEBIT);

                    $message = ProtectionFormulaires::messageDebit((int) ($headers['Retry-After'] ?? 60));

                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 429, $headers)
                        : response()->view('errors.429', ['message' => $message], 429, $headers);
                });
        });

        // F81 : champ piège + délai minimal sur les formulaires publics de connexion et d'inscription.
        $formulairesProteges = ['login.store' => 'connexion', 'register.store' => 'inscription'];

        $this->app->booted(function () use ($formulairesProteges): void {
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if (in_array($route->getName(), ['register.store', 'password.email'], true)
                    && ! in_array('throttle:auth-sensible', $route->middleware(), true)) {
                    $route->middleware('throttle:auth-sensible');
                }

                if ($route->getName() === 'register.store' && ! in_array(IgnorerEmailVide::class, $route->middleware(), true)) {
                    $route->middleware(IgnorerEmailVide::class);
                }

                $formulaire = $formulairesProteges[$route->getName() ?? ''] ?? null;
                $antiRobot = ProtegerFormulaireContreRobots::class.':'.$formulaire;

                if ($formulaire !== null && ! in_array($antiRobot, $route->middleware(), true)) {
                    $route->middleware($antiRobot);
                }
            }
        });
    }
}
