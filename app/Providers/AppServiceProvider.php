<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\Channels\MailChannelTolerant;
use App\Policies\DatabaseNotificationPolicy;
use App\Services\RecommandationProvider;
use App\Services\RecommandationsEcrites;
use App\Services\RedacteurLangageClair;
use App\Services\RedacteurLangageClairRegles;
use App\Services\ReformulateurRequete;
use App\Services\SansReformulation;
use App\Services\Simplificateur;
use App\Services\SimplificateurRegles;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentTimezone;
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

        // F90 : explications simples rédigées et règles en base (aucune IA) ; une implémentation IA pourra être liée ici.
        $this->app->bind(Simplificateur::class, SimplificateurRegles::class);

        // F89 : version en langage clair de secours par les synonymes en base (aucune IA) ; une implémentation IA pourra être liée ici.
        $this->app->bind(RedacteurLangageClair::class, RedacteurLangageClairRegles::class);

        // F93 : une panne du serveur d'e-mails n'empêche plus d'afficher la page (erreur journalisée + bandeau).
        $this->app->bind(MailChannel::class, MailChannelTolerant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // F87 : l'admin Filament affiche les dates en heure de Madagascar (stockées en UTC).
        FilamentTimezone::set(config()->string('app.timezone_affichage'));

        Gate::define('viewAgentSpace', fn (User $user): bool => $user->isAgent() || $user->isAdmin());

        // F103 : le rapport d'activité (page admin, version imprimable, CSV) est réservé aux administrateurs.
        Gate::define('voirRapportActivite', fn (User $user): bool => $user->isAdmin());

        // F99 : espace partenaire (comptes partenaires rattachés à un partenaire, et admins qui gèrent tous les partenaires).
        Gate::define('viewPartnerSpace', fn (User $user): bool => $user->isAdmin() || ($user->isPartenaire() && $user->partner_id !== null));

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

        Password::defaults(fn (): Password => Password::min(8));
    }
}
