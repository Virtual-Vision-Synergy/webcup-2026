<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\MailChannelTolerant;
use App\Policies\DatabaseNotificationPolicy;
use App\Services\RecommandationProvider;
use App\Services\RecommandationsEcrites;
use App\Services\ReformulateurRequete;
use App\Services\SansReformulation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // D10 : orientation sans IA par défaut ; une reformulation par IA pourra être liée ici plus tard.
        $this->app->bind(ReformulateurRequete::class, SansReformulation::class);

        // F31 : conseils canicule écrits par l'Agence sanitaire (aucune IA) ; une implémentation IA pourra être liée ici.
        $this->app->bind(RecommandationProvider::class, RecommandationsEcrites::class);

        // F93 : une panne du serveur d'e-mails n'empêche plus d'afficher la page (erreur journalisée + bandeau).
        $this->app->bind(MailChannel::class, MailChannelTolerant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('viewAgentSpace', fn (User $user): bool => $user->isAgent() || $user->isAdmin());

        // F30 : une notification n'est accessible qu'à son destinataire.
        Gate::policy(DatabaseNotification::class, DatabaseNotificationPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // F78 : toute requête N+1 (relation chargée paresseusement) est signalée hors production.
        // Journalisée plutôt que levée en exception, pour ne jamais casser une page pendant une démonstration.
        Model::preventLazyLoading(! app()->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Requête N+1 détectée : relation chargée paresseusement.', [
                'modele' => $model::class,
                'relation' => $relation,
                'url' => request()->fullUrl(),
            ]);
        });

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
