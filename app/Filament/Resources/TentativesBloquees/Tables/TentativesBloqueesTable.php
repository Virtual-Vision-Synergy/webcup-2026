<?php

namespace App\Filament\Resources\TentativesBloquees\Tables;

use App\Models\TentativeBloquee;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TentativesBloqueesTable
{
    /** @var array<int, string> Nombre de jours => libellé. */
    private const PERIODES = [
        1 => 'Dernières 24 h',
        7 => '7 derniers jours',
        30 => '30 derniers jours',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('formulaire')
                    ->label('Formulaire')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TentativeBloquee::FORMULAIRE_OPTIONS[$state] ?? $state)
                    ->color('gray'),
                TextColumn::make('motif')
                    ->label('Motif')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TentativeBloquee::MOTIF_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        TentativeBloquee::MOTIF_HONEYPOT, TentativeBloquee::MOTIF_JETON_INVALIDE => 'danger',
                        TentativeBloquee::MOTIF_DEBIT => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('ip')
                    ->label('Adresse IP')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Compte')
                    ->placeholder('Visiteur'),
                TextColumn::make('user_agent')
                    ->label('Navigateur / outil')
                    ->limit(40)
                    ->tooltip(fn (TentativeBloquee $record): ?string => $record->user_agent)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('formulaire')
                    ->label('Formulaire')
                    ->options(TentativeBloquee::FORMULAIRE_OPTIONS),
                SelectFilter::make('motif')
                    ->label('Motif')
                    ->options(TentativeBloquee::MOTIF_OPTIONS),
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(self::PERIODES)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('created_at', '>=', now()->subDays((int) $data['value']))
                        : $query),
            ])
            ->emptyStateHeading('Aucun envoi bloqué')
            ->emptyStateDescription('Les envois de robots (champ piège rempli, formulaire contourné, envoi trop rapide ou trop d’envois) apparaîtront ici.');
    }
}
