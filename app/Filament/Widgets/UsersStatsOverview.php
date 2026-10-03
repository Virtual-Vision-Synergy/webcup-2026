<?php

namespace App\Filament\Widgets;

use App\Models\Role;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class UsersStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

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
        return [
            Stat::make('Inscrits', User::count())
                ->description('Comptes utilisateurs')
                ->icon('heroicon-o-users'),
            Stat::make('Administrateurs', User::where('role_id', Role::idFor(Role::ADMIN))->count())
                ->description('Accès à cet espace')
                ->icon('heroicon-o-shield-check')
                ->color('warning'),
            Stat::make('Nouveaux (7 jours)', User::where('created_at', '>=', now()->subDays(7))->count())
                ->description('Inscrits cette semaine')
                ->icon('heroicon-o-user-plus')
                ->color('success'),
        ];
    }
}
