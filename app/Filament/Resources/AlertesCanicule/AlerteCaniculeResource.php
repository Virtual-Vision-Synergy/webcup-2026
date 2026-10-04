<?php

namespace App\Filament\Resources\AlertesCanicule;

use App\Filament\Resources\AlertesCanicule\Pages\ManageAlertesCanicule;
use App\Models\AlerteCanicule;
use App\Models\Annonce;
use App\Services\NotifierCanicule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * F31 : l'Agence sanitaire déclenche une alerte canicule pour un ou plusieurs quartiers (AlerteCaniculePolicy : admins).
 * À l'enregistrement d'une alerte en cours, les habitants des quartiers touchés sont notifiés (une seule fois) ;
 * une alerte programmée l'est à son début (commande canicule:notify).
 */
class AlerteCaniculeResource extends Resource
{
    protected static ?string $model = AlerteCanicule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $modelLabel = 'alerte canicule';

    protected static ?string $pluralModelLabel = 'alertes canicule';

    protected static ?string $navigationLabel = 'Alertes canicule';

    protected static ?string $slug = 'alertes-canicule';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CheckboxList::make('quartiers')
                    ->label('Quartiers touchés')
                    ->relationship('quartiers', 'nom')
                    ->columns(3)
                    ->required()
                    ->columnSpanFull(),
                Select::make('niveau')
                    ->label('Niveau')
                    ->options(AlerteCanicule::NIVEAU_LABELS)
                    ->in(AlerteCanicule::NIVEAU_OPTIONS)
                    ->default('vigilance')
                    ->required(),
                TextInput::make('temperature_max')
                    ->label('Température maximale attendue (°C)')
                    ->integer()
                    ->minValue(25)
                    ->maxValue(60),
                DateTimePicker::make('debut')
                    ->label('Début (heure de Madagascar)')
                    ->timezone(Annonce::FUSEAU)
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                DateTimePicker::make('fin')
                    ->label('Fin (heure de Madagascar)')
                    ->timezone(Annonce::FUSEAU)
                    ->seconds(false)
                    ->default(now()->addDays(2))
                    ->after('debut')
                    ->required(),
                Textarea::make('message')
                    ->label('Message aux habitants')
                    ->helperText('Ce qu’il faut savoir tout de suite. Les conseils par profil (personnes âgées, enfants…) sont ajoutés automatiquement.')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('quartiers:id,nom'))
            ->columns([
                TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AlerteCanicule::NIVEAU_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'urgence' => 'danger',
                        'alerte' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('quartiers.nom')
                    ->label('Quartiers')
                    ->badge(),
                TextColumn::make('temperature_max')
                    ->label('Max.')
                    ->suffix(' °C')
                    ->placeholder('—'),
                TextColumn::make('debut')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->sortable(),
                TextColumn::make('fin')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->sortable(),
                TextColumn::make('notified_at')
                    ->label('Habitants prévenus')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->placeholder('Pas encore'),
            ])
            ->defaultSort('debut', 'desc')
            ->recordActions([
                EditAction::make()
                    // Quartiers modifiés (table pivot) : le bandeau est recalculé, puis notification si l'alerte vient de commencer.
                    ->after(function (AlerteCanicule $record): void {
                        AlerteCanicule::oublierCache();
                        app(NotifierCanicule::class)->notifierSiVisible($record);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucune alerte canicule')
            ->emptyStateDescription('Déclenchez une alerte pour prévenir les habitants des quartiers touchés.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAlertesCanicule::route('/'),
        ];
    }
}
