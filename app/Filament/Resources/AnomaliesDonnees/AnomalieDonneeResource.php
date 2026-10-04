<?php

namespace App\Filament\Resources\AnomaliesDonnees;

use App\Filament\Resources\AnomaliesDonnees\Pages\ListAnomaliesDonnees;
use App\Filament\Resources\AnomaliesDonnees\Tables\AnomaliesDonneesTable;
use App\Models\AnomalieDonnee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * F85 : anomalies repérées par le contrôle d'intégrité, avec correction en un clic. Admins (AnomalieDonneePolicy).
 */
class AnomalieDonneeResource extends Resource
{
    protected static ?string $model = AnomalieDonnee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'anomalie de données';

    protected static ?string $pluralModelLabel = 'anomalies de données';

    protected static ?string $navigationLabel = 'Anomalies de données';

    protected static ?string $slug = 'securite/anomalies';

    public static function table(Table $table): Table
    {
        return AnomaliesDonneesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AnomalieDonnee::query()->ouvertes()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Anomalies à traiter';
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
            'index' => ListAnomaliesDonnees::route('/'),
        ];
    }
}
