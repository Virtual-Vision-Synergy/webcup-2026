<?php

namespace App\Filament\Resources\AnomaliesDonnees\Pages;

use App\Filament\Resources\AnomaliesDonnees\AnomalieDonneeResource;
use App\Models\AnomalieDonnee;
use App\Services\ControleIntegrite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class ListAnomaliesDonnees extends ListRecords
{
    protected static string $resource = AnomalieDonneeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('controler')
                ->label('Lancer le contrôle maintenant')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->visible(fn (): bool => Gate::allows('controler', AnomalieDonnee::class))
                ->action(function (): void {
                    Gate::authorize('controler', AnomalieDonnee::class);

                    // Requêtes de contrôle un peu lourdes : au plus 6 lancements manuels par 10 minutes et par admin.
                    $cle = 'f85:controle:'.Auth::id();
                    if (RateLimiter::tooManyAttempts($cle, 6)) {
                        Notification::make()
                            ->title('Trop de demandes')
                            ->body('Réessayez dans '.RateLimiter::availableIn($cle).' secondes.')
                            ->warning()
                            ->send();

                        return;
                    }
                    RateLimiter::hit($cle, 600);

                    $resultat = app(ControleIntegrite::class)->executer();

                    $notification = Notification::make()
                        ->title($resultat['detectees'] === 0 ? 'Aucune donnée incohérente' : $resultat['detectees'].' anomalie(s) à traiter')
                        ->body($resultat['nouvelles'].' nouvelle(s), '.$resultat['disparues'].' disparue(s) depuis le dernier contrôle.');
                    $resultat['detectees'] === 0 ? $notification->success() : $notification->warning();
                    $notification->send();
                }),
        ];
    }
}
