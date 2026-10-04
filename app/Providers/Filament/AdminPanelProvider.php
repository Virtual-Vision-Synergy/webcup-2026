<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAccountIsActive;
use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // F37 : pas de page de connexion Filament, les admins passent par /login (limiteur, journal, F34).
            ->brandName(config('app.name'))
            // Même logo que l'espace citoyen, avec le repère « Administration ».
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->colors([
                // Même teinte que --color-cyan (--color-accent) dans resources/css/app.css ; gris bleutés comme le site.
                'primary' => Color::hex('#00687B'),
                'gray' => Color::Slate,
            ])
            // Même police que le site (IBM Plex Sans, auto-hébergée par Vite) : aucun appel à un CDN de polices.
            ->font('IBM Plex Sans', provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => app(Vite::class)->fonts())
            // Thème sombre par défaut, comme le site.
            ->defaultThemeMode(ThemeMode::Dark)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Retour vers l'espace citoyen (le site), en tête de la navigation.
            ->navigationItems([
                NavigationItem::make('Espace citoyen')
                    ->url(fn (): string => route('dashboard'))
                    ->icon(Heroicon::OutlinedHome)
                    ->sort(-100),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAccountIsActive::class,
            ]);
    }
}
