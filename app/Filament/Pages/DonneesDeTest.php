<?php

namespace App\Filament\Pages;

use App\Models\ActionLog;
use App\Models\User;
use App\Services\ReinitialisationDonnees;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Remise à zéro des données de test du jury (détail des données : docs/donnees-de-test-jury.md).
 * Réservé aux admins ; les comptes sont conservés, l'admin connecté n'est pas déconnecté.
 */
class DonneesDeTest extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Réinitialiser les données';

    protected static ?string $title = 'Réinitialiser les données de test';

    protected static ?string $slug = 'donnees-de-test';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.donnees-de-test';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reinitialiser')
                ->label('Réinitialiser les données')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Effacer toutes les données et recharger les données de test ?')
                ->modalDescription('Démarches, signalements, rendez-vous, avis, journaux… tout est effacé puis recréé. Les comptes sont conservés et vous restez connecté. Une sauvegarde de la base est faite juste avant.')
                ->modalSubmitActionLabel('Oui, réinitialiser')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    $admin = Auth::user();
                    abort_unless($admin instanceof User, 403);

                    $cle = 'donnees-de-test:'.$admin->id;
                    if (RateLimiter::tooManyAttempts($cle, 3)) {
                        Notification::make()
                            ->title('Trop de demandes')
                            ->body('Réessayez dans '.RateLimiter::availableIn($cle).' secondes.')
                            ->warning()
                            ->send();

                        return;
                    }
                    RateLimiter::hit($cle, 600);

                    try {
                        app(ReinitialisationDonnees::class)->lancer($admin);
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->title('Réinitialisation impossible')
                            ->body('Aucune donnée n’a été modifiée. Le détail est dans le journal du serveur.')
                            ->danger()
                            ->send();

                        return;
                    }

                    ActionLog::record('donnees_reinitialisees');

                    Notification::make()
                        ->title('Données de test rechargées')
                        ->success()
                        ->send();
                }),
        ];
    }
}
