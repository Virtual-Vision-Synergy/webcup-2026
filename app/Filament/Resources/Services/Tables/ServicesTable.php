<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('categorie')
                    ->label('Catégorie')
                    ->formatStateUsing(fn (?string $state): string => Service::labelCategorie($state) ?? '—'),
                TextColumn::make('indisponible_depuis')
                    ->label('État')
                    ->badge()
                    ->state(fn (Service $record): string => $record->estIndisponible() ? 'Indisponible' : 'Disponible')
                    ->color(fn (Service $record): string => $record->estIndisponible() ? 'danger' : 'success'),
                TextColumn::make('motif_indisponibilite')
                    ->label('Motif')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('retour_prevu_le')
                    ->label('Retour prévu')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('nom')
            ->filters([
                TernaryFilter::make('indisponible_depuis')
                    ->label('État')
                    ->nullable()
                    ->trueLabel('Indisponibles')
                    ->falseLabel('Disponibles'),
            ])
            ->recordActions([
                Action::make('rendreIndisponible')
                    ->label('Rendre indisponible')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Service $record): bool => ! $record->estIndisponible() && (auth()->user()?->can('toggleAvailability', $record) ?? false))
                    ->modalHeading(fn (Service $record): string => 'Rendre « '.$record->nom.' » indisponible')
                    ->modalDescription('Le service reste visible au catalogue avec ce motif, mais les démarches et rendez-vous sont bloqués.')
                    ->modalSubmitActionLabel('Rendre indisponible')
                    ->schema([
                        TextInput::make('motif')
                            ->label('Motif affiché aux habitants')
                            ->placeholder('Ex. Panne du système informatique')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('retour_prevu_le')
                            ->label('Retour prévu le (facultatif)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(today()),
                    ])
                    ->action(function (Service $record, array $data): void {
                        Gate::authorize('toggleAvailability', $record);

                        $retour = filled($data['retour_prevu_le'] ?? null) ? Carbon::parse($data['retour_prevu_le']) : null;
                        $record->rendreIndisponible((string) $data['motif'], $retour);
                        Cache::forget('landing.etat');

                        Notification::make()->title('Service rendu indisponible.')->success()->send();
                    }),
                Action::make('retablir')
                    ->label('Rétablir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Service $record): bool => $record->estIndisponible() && (auth()->user()?->can('toggleAvailability', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Service $record): string => 'Rétablir « '.$record->nom.' » ?')
                    ->action(function (Service $record): void {
                        Gate::authorize('toggleAvailability', $record);

                        $record->retablir();
                        Cache::forget('landing.etat');

                        Notification::make()->title('Service rétabli.')->success()->send();
                    }),
            ]);
    }
}
