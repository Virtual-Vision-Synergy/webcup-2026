<?php

namespace App\Filament\Resources\GenTestFiches\Tables;

use App\Models\GenTestFiche;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GenTestFichesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->label('Titre')->searchable()->limit(40)->sortable(),
                TextColumn::make('description')->label('Description')->limit(60)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('niveau')->label('Niveau')->badge()->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))->sortable(),
                TextColumn::make('genTestZone.nom')->label('Gen Test Zone')->searchable()->sortable(),
                TextColumn::make('statut')->label('Statut')->badge()
                    ->formatStateUsing(fn (string $state): string => GenTestFiche::libelleStatut($state))
                    ->color(fn (string $state): string => match ($state) {
                        'en_attente' => 'warning',
                        'valide' => 'success',
                        'refuse' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('user.name')->label('Auteur')->searchable(),
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('niveau')->label('Niveau')->options(array_combine(GenTestFiche::NIVEAU_OPTIONS, array_map('ucfirst', GenTestFiche::NIVEAU_OPTIONS))),
                SelectFilter::make('gen_test_zone_id')->label('Gen Test Zone')->relationship('genTestZone', 'nom'),
                SelectFilter::make('statut')->label('Statut')->options(array_combine(GenTestFiche::STATUT_OPTIONS, array_map(GenTestFiche::libelleStatut(...), GenTestFiche::STATUT_OPTIONS))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize('changerStatut')
                    ->visible(fn (GenTestFiche $record): bool => $record->statut === GenTestFiche::STATUT_OPTIONS[0])
                    ->action(fn (GenTestFiche $record) => $record->changerStatut(GenTestFiche::STATUT_OPTIONS[1])),
                Action::make('changerStatut')
                    ->label('Changer le statut')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize('changerStatut')
                    ->fillForm(fn (GenTestFiche $record): array => ['statut' => $record->statut])
                    ->schema([
                        Select::make('statut')
                            ->label('Statut')
                            ->options(array_combine(GenTestFiche::STATUT_OPTIONS, array_map(GenTestFiche::libelleStatut(...), GenTestFiche::STATUT_OPTIONS)))
                            ->required(),
                    ])
                    ->action(fn (GenTestFiche $record, array $data) => $record->changerStatut($data['statut'])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
