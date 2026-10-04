<?php

namespace App\Filament\Widgets;

use App\Models\AnomalieDonnee;
use App\Models\SecurityEvent;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * F85 : tableau « Sécurité » en un coup d'œil : signaux des dernières 24 h, comptes verrouillés, anomalies ouvertes.
 */
class SecuriteOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Sécurité (24 h)';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $parNiveau = SecurityEvent::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('niveau, count(*) as total')
            ->groupBy('niveau')
            ->pluck('total', 'niveau')
            ->map(fn ($total): int => (int) $total);

        $verrouilles = User::query()->where('verrouille_jusqu_au', '>', now())->count();
        $anomalies = AnomalieDonnee::query()->ouvertes()->count();

        return [
            Stat::make('Signaux élevés', $parNiveau->get(SecurityEvent::NIVEAU_ELEVE, 0))
                ->description('Modifications massives…')
                ->icon('heroicon-o-fire')
                ->color('danger'),
            Stat::make('Signaux moyens', $parNiveau->get(SecurityEvent::NIVEAU_MOYEN, 0))
                ->description('Rafales, accès refusés, connexions bloquées')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('Comptes verrouillés', $verrouilles)
                ->description('Verrouillage temporaire en cours')
                ->icon('heroicon-o-lock-closed'),
            Stat::make('Anomalies de données', $anomalies)
                ->description('À corriger ou ignorer')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color($anomalies > 0 ? 'warning' : 'success'),
        ];
    }
}
