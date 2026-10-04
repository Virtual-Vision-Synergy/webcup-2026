<?php

namespace App\Filament\Resources\GenTestFiches\Schemas;

use App\Models\GenTestFiche;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

/**
 * user_id et statut n'apparaissent pas ici : ils ne sont jamais saisis librement.
 * user_id est assigné à la création (Pages/CreateGenTestFiche.php), le statut passe par l'action « changerStatut ».
 */
class GenTestFicheForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titre')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->maxLength(5000)
                    ->columnSpanFull(),
                Select::make('niveau')
                    ->label('Niveau')
                    ->required()
                    ->options(array_combine(GenTestFiche::NIVEAU_OPTIONS, array_map('ucfirst', GenTestFiche::NIVEAU_OPTIONS))),
                Select::make('gen_test_zone_id')
                    ->label('Gen Test Zone')
                    ->relationship('genTestZone', 'nom')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }
}
