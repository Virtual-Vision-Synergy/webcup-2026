<?php

namespace App\Filament\Resources\SecurityEvents;

use App\Filament\Resources\SecurityEvents\Pages\ListSecurityEvents;
use App\Filament\Resources\SecurityEvents\Tables\SecurityEventsTable;
use App\Models\SecurityEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * F85 : événements de sécurité récents (activité inhabituelle). Lecture seule, admins (SecurityEventPolicy).
 */
class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'événement de sécurité';

    protected static ?string $pluralModelLabel = 'événements de sécurité';

    protected static ?string $navigationLabel = 'Événements de sécurité';

    protected static ?string $slug = 'securite/evenements';

    public static function table(Table $table): Table
    {
        return SecurityEventsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SecurityEvent::where('created_at', '>=', now()->subDay())
            ->where('niveau', '!=', SecurityEvent::NIVEAU_INFO)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Signaux moyens ou élevés ces dernières 24 h';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityEvents::route('/'),
        ];
    }
}
