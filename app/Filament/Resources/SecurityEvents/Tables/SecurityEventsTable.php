<?php

namespace App\Filament\Resources\SecurityEvents\Tables;

use App\Models\SecurityEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SecurityEventsTable
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name,email'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('niveau')
                    ->label('Niveau')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SecurityEvent::NIVEAU_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        SecurityEvent::NIVEAU_ELEVE => 'danger',
                        SecurityEvent::NIVEAU_MOYEN => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('type')
                    ->label('Signal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SecurityEvent::TYPE_OPTIONS[$state] ?? $state)
                    ->color('info'),
                TextColumn::make('user.name')
                    ->label('Compte')
                    ->placeholder('Visiteur / inconnu')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Détail')
                    ->wrap(),
                TextColumn::make('ip')
                    ->label('Adresse IP')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user_agent')
                    ->label('Navigateur / outil')
                    ->limit(40)
                    ->tooltip(fn (SecurityEvent $record): ?string => $record->user_agent)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->filters([
                SelectFilter::make('niveau')
                    ->label('Niveau')
                    ->options(SecurityEvent::NIVEAU_OPTIONS),
                SelectFilter::make('type')
                    ->label('Signal')
                    ->options(SecurityEvent::TYPE_OPTIONS),
                SelectFilter::make('periode')
                    ->label('Période')
                    ->options(self::PERIODES)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('created_at', '>=', now()->subDays((int) $data['value']))
                        : $query),
            ])
            ->emptyStateHeading('Aucun événement de sécurité')
            ->emptyStateDescription('Les nouvelles connexions, rafales d’actions, accès refusés répétés et modifications massives apparaîtront ici.');
    }
}
