<?php

namespace App\Filament\Resources\MotsClesService\Pages;

use App\Filament\Resources\MotsClesService\MotCleServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMotsClesService extends ManageRecords
{
    protected static string $resource = MotCleServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter un mot-clé'),
        ];
    }
}
