<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use App\Support\LangageClair;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->state(fn (Service $record): string => $record->libelleEtat())
                    ->color(fn (Service $record): string => match ($record->etat()) {
                        Service::ETAT_INDISPONIBLE => 'danger',
                        Service::ETAT_PERTURBE => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('motif_indisponibilite')
                    ->label('Motif')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('retour_prevu_le')
                    ->label('Retour prévu')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('langage_clair_valide_le')
                    ->label('Langage clair')
                    ->badge()
                    ->state(fn (Service $record): string => match (true) {
                        $record->langageClairPublie() => 'Relu par la mairie',
                        filled($record->langage_clair) => 'Brouillon',
                        default => 'À rédiger',
                    })
                    ->color(fn (Service $record): string => match (true) {
                        $record->langageClairPublie() => 'success',
                        filled($record->langage_clair) => 'warning',
                        default => 'gray',
                    }),
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
                // F89 : l'agent ou l'admin rédige la version en langage clair et la valide avant publication.
                Action::make('langageClair')
                    ->label('Langage clair')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->color('gray')
                    ->visible(fn (Service $record): bool => auth()->user()?->can('redigerLangageClair', $record) ?? false)
                    ->modalHeading(fn (Service $record): string => 'Version en langage clair : '.$record->nom)
                    ->modalDescription('Phrases courtes, mots de tous les jours. Gardez les délais, les pièces, les montants et les contacts : ils sont aussi rappelés automatiquement sous le texte.')
                    ->modalSubmitActionLabel('Enregistrer')
                    ->fillForm(fn (Service $record): array => [
                        // Sans version rédigée, on part de la proposition automatique (synonymes en base) à relire.
                        'langage_clair' => $record->langage_clair ?? LangageClair::pourService($record)['texte'],
                        'valider' => $record->langageClairPublie(),
                    ])
                    ->schema([
                        Textarea::make('langage_clair')
                            ->label('Version simple')
                            ->rows(8)
                            ->maxLength(3000)
                            ->required(fn (Get $get): bool => (bool) $get('valider')),
                        Toggle::make('valider')
                            ->label('Validée : publier avec le badge « Relu par la mairie »')
                            ->helperText('Non validée, la version reste un brouillon : les habitants voient la version simplifiée automatiquement.'),
                    ])
                    ->action(function (Service $record, array $data): void {
                        Gate::authorize('redigerLangageClair', $record);

                        $record->enregistrerLangageClair($data['langage_clair'] ?? null, (bool) ($data['valider'] ?? false));

                        Notification::make()
                            ->title($record->langageClairPublie() ? 'Version en langage clair publiée.' : 'Brouillon enregistré (non publié).')
                            ->success()
                            ->send();
                    }),
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
