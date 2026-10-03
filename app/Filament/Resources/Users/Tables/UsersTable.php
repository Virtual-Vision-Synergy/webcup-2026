<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('role.label')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (User $record): string => match ($record->role->code) {
                        Role::ADMIN => 'warning',
                        Role::AGENT => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Activée' : 'Non')
                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                    ->placeholder('Non'),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('role'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Rôle')
                    ->relationship('role', 'label'),
            ])
            ->recordActions([
                EditAction::make(),
                // Un admin ne peut pas supprimer son propre compte.
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ]);
    }
}
