<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Adresse e-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // Le mot de passe n'est jamais affiché. Laissé vide en modification = inchangé.
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Laisser vide pour ne pas le changer.' : null),
                // role_id n'est pas "fillable" : il est enregistré explicitement dans CreateUser / EditUser.
                Select::make('role_id')
                    ->label('Rôle')
                    ->options(fn (): array => Role::query()->orderBy('id')->pluck('label', 'id')->all())
                    ->default(fn (): int => Role::idFor(Role::CITOYEN))
                    ->required()
                    ->exists(Role::class, 'id')
                    ->native(false)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                    ->helperText(fn (?User $record): ?string => $record?->is(auth()->user()) ? 'Vous ne pouvez pas modifier votre propre rôle.' : null),
            ]);
    }
}
