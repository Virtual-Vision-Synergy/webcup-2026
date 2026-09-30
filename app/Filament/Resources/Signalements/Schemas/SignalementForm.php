<?php

namespace App\Filament\Resources\Signalements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SignalementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('titre')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('niveau')
                    ->required(),
                TextInput::make('zone')
                    ->required(),
                TextInput::make('photo'),
                DatePicker::make('date_incident')
                    ->required(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
            ]);
    }
}
