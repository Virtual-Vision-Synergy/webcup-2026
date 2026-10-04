<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\ModeDegrade as ServiceModeDegrade;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * F77 : activation du « Mode dégradé » (surcharge des serveurs) par un admin, sans redéploiement.
 * La variable d'environnement MODE_DEGRADE=true l'impose aussi (elle ne peut pas être désactivée d'ici).
 */
class ModeDegrade extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Mode dégradé';

    protected static ?string $title = 'Mode dégradé (surcharge des serveurs)';

    protected static ?string $slug = 'mode-degrade';

    protected string $view = 'filament.pages.mode-degrade';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public function estActif(): bool
    {
        return ServiceModeDegrade::actif();
    }

    public function estImposeParEnvironnement(): bool
    {
        return ServiceModeDegrade::activeParEnvironnement();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activer')
                ->label('Activer le mode dégradé')
                ->icon(Heroicon::OutlinedBolt)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Toutes les pages passent en version allégée et le bandeau « Service en mode allégé » s’affiche pour tous les habitants.')
                ->visible(fn (): bool => ! ServiceModeDegrade::activeParAdmin())
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    ServiceModeDegrade::activer();

                    Notification::make()->title('Mode dégradé activé')->success()->send();
                }),
            Action::make('desactiver')
                ->label('Revenir au mode normal')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => ServiceModeDegrade::activeParAdmin())
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    ServiceModeDegrade::desactiver();

                    Notification::make()
                        ->title(ServiceModeDegrade::activeParEnvironnement() ? 'Réglage admin retiré, mais MODE_DEGRADE reste actif' : 'Mode normal rétabli')
                        ->success()
                        ->send();
                }),
        ];
    }
}
