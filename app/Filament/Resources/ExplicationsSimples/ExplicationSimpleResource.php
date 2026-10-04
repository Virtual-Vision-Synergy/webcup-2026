<?php

namespace App\Filament\Resources\ExplicationsSimples;

use App\Filament\Resources\ExplicationsSimples\Pages\ManageExplicationsSimples;
use App\Models\ExplicationSimple;
use App\Support\Lexique;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * F90 : explications simples des passages administratifs (bouton « Expliquer plus simplement »).
 * Réservé aux admins (ExplicationSimplePolicy) ; prises en compte immédiatement sur les pages.
 */
class ExplicationSimpleResource extends Resource
{
    protected static ?string $model = ExplicationSimple::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $modelLabel = 'explication simple';

    protected static ?string $pluralModelLabel = 'explications simples';

    protected static ?string $navigationLabel = 'Explications simples';

    protected static ?string $recordTitleAttribute = 'titre';

    protected static ?string $slug = 'explications-simples';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('cle')
                    ->label('Clé du passage')
                    ->helperText('Identifiant utilisé dans la page (minuscules et tirets). Ne pas modifier une clé déjà utilisée.')
                    ->required()
                    ->maxLength(80)
                    ->regex('/^[a-z0-9-]+$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('titre')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('texte_officiel')
                    ->label('Texte officiel du passage')
                    ->helperText('Copie du passage administratif : ses mots difficiles sont traduits grâce aux synonymes simples.')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Textarea::make('explication')
                    ->label('Explication en mots simples')
                    ->helperText('Phrases courtes, mots de tous les jours, en s’adressant à l’habitant (« vous »).')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Select::make('termes')
                    ->label('Mots du lexique à rappeler')
                    ->multiple()
                    ->options(fn (): array => array_map(fn (array $terme): string => $terme['terme'], Lexique::termes()))
                    ->in(array_keys(Lexique::TERMES)),
                Toggle::make('actif')
                    ->label('Affichée sur le site')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cle')
                    ->label('Clé')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('explication')
                    ->label('Explication')
                    ->limit(80)
                    ->toggleable(),
                IconColumn::make('actif')
                    ->label('Affichée')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Modifiée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('titre')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Aucune explication')
            ->emptyStateDescription('Rédigez une explication simple pour chaque passage administratif difficile.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExplicationsSimples::route('/'),
        ];
    }
}
