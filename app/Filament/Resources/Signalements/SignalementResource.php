<?php

namespace App\Filament\Resources\Signalements;

use App\Filament\Resources\Signalements\Pages\CreateSignalement;
use App\Filament\Resources\Signalements\Pages\EditSignalement;
use App\Filament\Resources\Signalements\Pages\ListSignalements;
use App\Filament\Resources\Signalements\Schemas\SignalementForm;
use App\Filament\Resources\Signalements\Tables\SignalementsTable;
use App\Models\Signalement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SignalementResource extends Resource
{
    protected static ?string $model = Signalement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'admin';

    public static function form(Schema $schema): Schema
    {
        return SignalementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSignalements::route('/'),
            'create' => CreateSignalement::route('/create'),
            'edit' => EditSignalement::route('/{record}/edit'),
        ];
    }
}
