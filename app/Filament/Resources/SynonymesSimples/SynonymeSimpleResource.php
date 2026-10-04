<?php

namespace App\Filament\Resources\SynonymesSimples;

use App\Filament\Resources\SynonymesSimples\Pages\ManageSynonymesSimples;
use App\Models\SynonymeSimple;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * F90 : règles « mot administratif → mot simple », appliquées au texte officiel des passages expliqués.
 * Réservé aux admins (SynonymeSimplePolicy).
 */
class SynonymeSimpleResource extends Resource
{
    protected static ?string $model = SynonymeSimple::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'synonyme simple';

    protected static ?string $pluralModelLabel = 'synonymes simples';

    protected static ?string $navigationLabel = 'Synonymes simples';

    protected static ?string $recordTitleAttribute = 'mot';

    protected static ?string $slug = 'synonymes-simples';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mot')
                    ->label('Mot ou expression administrative')
                    ->helperText('Tel qu’il apparaît dans le texte officiel : « pièces justificatives », « usager ». Accents et majuscules indifférents.')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('equivalent')
                    ->label('En mots simples')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mot')
                    ->label('Mot administratif')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equivalent')
                    ->label('En mots simples')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('mot')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Aucun synonyme')
            ->emptyStateDescription('Ajoutez les mots administratifs difficiles et leur équivalent simple.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSynonymesSimples::route('/'),
        ];
    }
}
