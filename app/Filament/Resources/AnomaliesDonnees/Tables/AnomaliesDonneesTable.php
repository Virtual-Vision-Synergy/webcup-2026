<?php

namespace App\Filament\Resources\AnomaliesDonnees\Tables;

use App\Models\AnomalieDonnee;
use App\Models\User;
use App\Services\ControleIntegrite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AnomaliesDonneesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('resolveur:id,name'))
            ->columns([
                TextColumn::make('detectee_le')
                    ->label('Détectée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AnomalieDonnee::TYPE_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        AnomalieDonnee::TYPE_STATUT_IMPOSSIBLE, AnomalieDonnee::TYPE_REFERENCE_ORPHELINE => 'danger',
                        AnomalieDonnee::TYPE_DATE_FUTURE => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('description')
                    ->label('Anomalie')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('correction')
                    ->label('Correction proposée')
                    ->state(fn (AnomalieDonnee $record): string => ControleIntegrite::correctionProposee($record))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('resolution')
                    ->label('État')
                    ->badge()
                    ->state(fn (AnomalieDonnee $record): string => $record->resolution ?? 'ouverte')
                    ->formatStateUsing(fn (string $state): string => AnomalieDonnee::RESOLUTION_OPTIONS[$state] ?? 'À traiter')
                    ->color(fn (string $state): string => match ($state) {
                        'ouverte' => 'warning',
                        AnomalieDonnee::RESOLUTION_IGNOREE => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('resolveur.name')
                    ->label('Traitée par')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('detectee_le', 'desc')
            ->filters([
                TernaryFilter::make('ouvertes')
                    ->label('État')
                    ->placeholder('Toutes')
                    ->trueLabel('À traiter')
                    ->falseLabel('Traitées')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('resolue_le'),
                        false: fn (Builder $query) => $query->whereNotNull('resolue_le'),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AnomalieDonnee::TYPE_OPTIONS),
            ])
            ->recordActions([
                Action::make('corriger')
                    ->label('Corriger')
                    ->icon('heroicon-o-wrench')
                    ->color('success')
                    ->visible(fn (AnomalieDonnee $record): bool => Gate::allows('resoudre', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Corriger cette anomalie ?')
                    ->modalDescription(fn (AnomalieDonnee $record): string => ControleIntegrite::correctionProposee($record).' La modification est inscrite au journal d’audit.')
                    ->action(function (AnomalieDonnee $record): void {
                        Gate::authorize('resoudre', $record);

                        $admin = Auth::user();
                        $corrigee = app(ControleIntegrite::class)->corriger($record, $admin instanceof User ? $admin : null);

                        Notification::make()
                            ->title($corrigee ? 'Anomalie corrigée.' : 'L’élément n’existe plus : anomalie close.')
                            ->success()
                            ->send();
                    }),
                Action::make('ignorer')
                    ->label('Ignorer')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (AnomalieDonnee $record): bool => Gate::allows('resoudre', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Ignorer cette anomalie ?')
                    ->modalDescription('À utiliser pour un faux positif : elle ne sera plus signalée par les prochains contrôles.')
                    ->action(function (AnomalieDonnee $record): void {
                        Gate::authorize('resoudre', $record);

                        $admin = Auth::user();
                        $record->marquerResolue(AnomalieDonnee::RESOLUTION_IGNOREE, $admin instanceof User ? $admin : null);

                        Notification::make()->title('Anomalie ignorée.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Aucune donnée incohérente')
            ->emptyStateDescription('Le contrôle d’intégrité tourne toutes les heures (statuts impossibles, références orphelines, dates futures, doublons). Lancez-le à la demande avec le bouton en haut.');
    }
}
