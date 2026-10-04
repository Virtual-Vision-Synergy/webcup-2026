<?php

namespace App\Filament\Resources\GenTestFiches;

use App\Filament\Resources\GenTestFiches\Pages\CreateGenTestFiche;
use App\Filament\Resources\GenTestFiches\Pages\EditGenTestFiche;
use App\Filament\Resources\GenTestFiches\Pages\ListGenTestFiches;
use App\Filament\Resources\GenTestFiches\Pages\ViewGenTestFiche;
use App\Filament\Resources\GenTestFiches\Schemas\GenTestFicheForm;
use App\Filament\Resources\GenTestFiches\Tables\GenTestFichesTable;
use App\Models\GenTestFiche;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Accès réservé aux admins (User::canAccessPanel) ; les droits fins viennent de GenTestFichePolicy.
 */
class GenTestFicheResource extends Resource
{
    protected static ?string $model = GenTestFiche::class;

    protected static ?string $slug = 'gen-test-fiches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $modelLabel = 'gen test fiche';

    protected static ?string $pluralModelLabel = 'gen test fiches';

    protected static ?string $recordTitleAttribute = 'titre';

    public static function form(Schema $schema): Schema
    {
        return GenTestFicheForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GenTestFichesTable::configure($table);
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
            'index' => ListGenTestFiches::route('/'),
            'create' => CreateGenTestFiche::route('/create'),
            'view' => ViewGenTestFiche::route('/{record}'),
            'edit' => EditGenTestFiche::route('/{record}/edit'),
        ];
    }
}
