<?php

namespace App\Filament\Resources\ReglesAssistant\Pages;

use App\Filament\Resources\ReglesAssistant\RegleAssistantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReglesAssistant extends ManageRecords
{
    protected static string $resource = RegleAssistantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter une règle'),
        ];
    }
}
