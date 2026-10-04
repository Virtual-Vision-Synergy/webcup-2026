<?php

namespace App\Filament\Widgets;

use App\Models\LoginAttempt;
use App\Models\TentativeBloquee;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * F81 : la protection anti-robots rendue « perceptible » : envois bloqués sur 24 h, par motif,
 * plus les connexions refusées pour blocage par F37 (journal LoginAttempt, non dupliqué).
 */
class ProtectionRobotsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Protection des formulaires (24 h)';

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
        $parMotif = TentativeBloquee::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('motif, count(*) as total')
            ->groupBy('motif')
            ->pluck('total', 'motif')
            ->map(fn ($total): int => (int) $total);

        $robots = $parMotif->get(TentativeBloquee::MOTIF_HONEYPOT, 0) + $parMotif->get(TentativeBloquee::MOTIF_JETON_INVALIDE, 0);

        $connexionsBloquees = LoginAttempt::query()
            ->failed()
            ->where('reason', LoginAttempt::REASON_LOCKED_OUT)
            ->since(now()->subDay())
            ->count();

        return [
            Stat::make('Envois bloqués', $parMotif->sum())
                ->description('Tous formulaires confondus')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger'),
            Stat::make('Robots détectés', $robots)
                ->description('Champ piège rempli ou formulaire contourné')
                ->icon('heroicon-o-bug-ant'),
            Stat::make('Trop rapides / trop d’envois', $parMotif->get(TentativeBloquee::MOTIF_TROP_RAPIDE, 0) + $parMotif->get(TentativeBloquee::MOTIF_DEBIT, 0))
                ->description('Délai minimal ou limite de débit')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Connexions bloquées', $connexionsBloquees)
                ->description('Trop d’échecs de mot de passe (F37)')
                ->icon('heroicon-o-lock-closed')
                ->color('warning'),
        ];
    }
}
