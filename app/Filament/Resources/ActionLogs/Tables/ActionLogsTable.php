<?php

namespace App\Filament\Resources\ActionLogs\Tables;

use App\Models\ActionLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActionLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->placeholder('Visiteur ou système')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ActionLog::ACTION_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'deleted' => 'danger',
                        'created', 'login' => 'success',
                        'updated' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Objet')
                    ->formatStateUsing(fn (?string $state, ActionLog $record): string => $state ? $state.' #'.$record->subject_id : '—'),
                TextColumn::make('ip')
                    ->label('Adresse IP')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options(ActionLog::ACTION_LABELS),
            ])
            ->emptyStateHeading('Aucune action enregistrée')
            ->emptyStateDescription('Le journal se remplit au fil des actions des utilisateurs.');
    }
}
