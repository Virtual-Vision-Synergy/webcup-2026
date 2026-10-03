<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Services\Tables\ServicesTable;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Disponibilité des services municipaux (F63) : l'admin désactive un service défectueux en un clic.
 * La création et la modification du contenu restent dans l'application (/services).
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $modelLabel = 'service';

    protected static ?string $pluralModelLabel = 'services';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
        ];
    }
}
