<?php

namespace App\Filament\Resources\SynonymesSimples\Pages;

use App\Filament\Resources\SynonymesSimples\SynonymeSimpleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSynonymesSimples extends ManageRecords
{
    protected static string $resource = SynonymeSimpleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter un synonyme'),
        ];
    }
}
