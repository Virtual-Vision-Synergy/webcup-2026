<?php

namespace App\Filament\Resources\InterruptionsTransport;

use App\Filament\Resources\InterruptionsTransport\Pages\ManageInterruptionsTransport;
use App\Models\Annonce;
use App\Models\InterruptionTransport;
use App\Models\LigneTransport;
use App\Services\NotifierInterruptionTransport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * F97 : l'admin déclare l'interruption d'une ou plusieurs lignes (arrêts touchés, période, cause) et les solutions
 * de remplacement (navette, autre ligne, à pied, à vélo, avec point sur la carte). InterruptionTransportPolicy : admins.
 * À l'enregistrement d'une interruption en cours, les habitants abonnés aux lignes touchées sont notifiés (une seule fois) ;
 * une interruption programmée l'est à son début (commande transports:notify).
 */
class InterruptionTransportResource extends Resource
{
    protected static ?string $model = InterruptionTransport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $modelLabel = 'interruption de ligne';

    protected static ?string $pluralModelLabel = 'interruptions de lignes';

    protected static ?string $navigationLabel = 'Interruptions transports';

    protected static ?string $slug = 'interruptions-transport';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CheckboxList::make('lignes')
                    ->label('Lignes interrompues')
                    ->relationship('lignes', 'numero', fn ($query) => $query->orderBy('numero'))
                    ->getOptionLabelFromRecordUsing(fn (LigneTransport $record): string => 'Ligne '.$record->numero.' — '.$record->nom)
                    ->columns(2)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('cause')
                    ->label('Cause (en langage clair)')
                    ->placeholder('Ex. : Pont de l’Ikopa fermé pour travaux : les bus ne peuvent plus passer.')
                    ->required()
                    ->maxLength(1000)
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('arrets_touches')
                    ->label('Arrêts non desservis (un par ligne)')
                    ->helperText('Laissez vide si toute la ligne est interrompue.')
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull(),
                DateTimePicker::make('debut')
                    ->label('Début (heure de Madagascar)')
                    ->timezone(Annonce::FUSEAU)
                    ->seconds(false)
                    ->default(now())
                    ->required(),
                DateTimePicker::make('fin')
                    ->label('Fin prévue (heure de Madagascar)')
                    ->helperText('L’interruption disparaît du site à cette heure.')
                    ->timezone(Annonce::FUSEAU)
                    ->seconds(false)
                    ->default(now()->addHours(8))
                    ->after('debut')
                    ->required(),
                Repeater::make('solutions')
                    ->label('Solutions de remplacement')
                    ->addActionLabel('Ajouter une solution')
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(InterruptionTransport::SOLUTION_LABELS)
                            ->in(InterruptionTransport::SOLUTION_OPTIONS)
                            ->default('navette')
                            ->required(),
                        TextInput::make('titre')
                            ->label('Solution')
                            ->placeholder('Ex. : Navette gratuite Gare centrale → Aéroport')
                            ->required()
                            ->maxLength(150),
                        Textarea::make('description')
                            ->label('Comment faire')
                            ->placeholder('Ex. : Montez devant la Gare centrale, quai B. Billet de la ligne 12 accepté.')
                            ->maxLength(1000)
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('horaires')
                            ->label('Horaires')
                            ->placeholder('Ex. : Toutes les 20 min, de 6 h à 20 h')
                            ->maxLength(150),
                        Select::make('ligne_id')
                            ->label('Ligne à prendre à la place')
                            ->options(fn (): array => LigneTransport::query()->orderBy('numero')->get(['id', 'numero', 'nom'])
                                ->mapWithKeys(fn (LigneTransport $ligne): array => [$ligne->id => 'Ligne '.$ligne->numero.' — '.$ligne->nom])
                                ->all())
                            ->rules(['nullable', 'integer', 'exists:ligne_transports,id'])
                            ->placeholder('Aucune'),
                        TextInput::make('latitude')
                            ->label('Point de départ : latitude')
                            ->helperText('Facultatif, pour afficher le point sur la carte.')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->requiredWith('longitude'),
                        TextInput::make('longitude')
                            ->label('Point de départ : longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->requiredWith('latitude'),
                    ])
                    ->columns(2)
                    ->maxItems(6)
                    ->defaultItems(1)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('lignes:id,numero'))
            ->columns([
                TextColumn::make('lignes.numero')
                    ->label('Lignes')
                    ->formatStateUsing(fn (string $state): string => 'Ligne '.$state)
                    ->badge(),
                TextColumn::make('cause')
                    ->label('Cause')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->state(fn (InterruptionTransport $record): string => $record->statut())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'En cours' => 'danger',
                        'Programmée' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('debut')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->sortable(),
                TextColumn::make('fin')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->sortable(),
                TextColumn::make('notified_at')
                    ->label('Abonnés prévenus')
                    ->dateTime('d/m/Y H:i', Annonce::FUSEAU)
                    ->placeholder('Pas encore'),
            ])
            ->defaultSort('debut', 'desc')
            ->recordActions([
                Action::make('retablir')
                    ->label('Ligne rétablie')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (InterruptionTransport $record): bool => $record->fin->isFuture() && Gate::allows('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('L’interruption prend fin maintenant : elle disparaît du site et les lignes repassent en trafic normal.')
                    ->action(function (InterruptionTransport $record): void {
                        Gate::authorize('update', $record);

                        // fin n'est jamais avant debut : une interruption programmée est close à son début.
                        $record->fin = $record->debut->isFuture() ? $record->debut : now();
                        $record->save();

                        Notification::make()->title('Interruption terminée')->success()->send();
                    }),
                EditAction::make()
                    // Lignes modifiées (table pivot) : le bandeau est recalculé, puis notification si l'interruption vient de commencer.
                    ->after(function (InterruptionTransport $record): void {
                        InterruptionTransport::oublierCache();
                        app(NotifierInterruptionTransport::class)->notifierSiVisible($record);
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Aucune interruption de ligne')
            ->emptyStateDescription('Déclarez une interruption pour prévenir les habitants et leur proposer des solutions de remplacement.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInterruptionsTransport::route('/'),
        ];
    }
}
