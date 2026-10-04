<?php

namespace App\Filament\Resources\MotsClesService;

use App\Filament\Resources\MotsClesService\Pages\ManageMotsClesService;
use App\Models\MotCleService;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * D10 : synonymes et mots-clés qui orientent les habitants vers le bon service (« poubelle » → propreté).
 * Réservé aux admins (MotCleServicePolicy) ; pris en compte immédiatement par la recherche des services.
 */
class MotCleServiceResource extends Resource
{
    protected static ?string $model = MotCleService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $modelLabel = 'mot-clé d’orientation';

    protected static ?string $pluralModelLabel = 'mots-clés d’orientation';

    protected static ?string $navigationLabel = 'Mots-clés d’orientation';

    protected static ?string $recordTitleAttribute = 'mot';

    protected static ?string $slug = 'mots-cles';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mot')
                    ->label('Mot ou expression de l’habitant')
                    ->helperText('Tel qu’un habitant l’écrirait : « poubelle », « papiers », « acte de naissance ». Accents et majuscules indifférents.')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('service_id', $get('service_id'))),
                Select::make('service_id')
                    ->label('Service vers lequel orienter')
                    ->options(fn (): array => Service::query()->orderBy('nom')->pluck('nom', 'id')->all())
                    ->searchable()
                    ->required()
                    ->exists(Service::class, 'id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('service:id,nom'))
            ->columns([
                TextColumn::make('mot')
                    ->label('Mot-clé')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service.nom')
                    ->label('Service')
                    ->badge()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('mot')
            ->filters([
                SelectFilter::make('service_id')
                    ->label('Service')
                    ->relationship('service', 'nom')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Aucun mot-clé')
            ->emptyStateDescription('Ajoutez les mots qu’emploient les habitants pour qu’ils trouvent le bon service.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMotsClesService::route('/'),
        ];
    }
}
