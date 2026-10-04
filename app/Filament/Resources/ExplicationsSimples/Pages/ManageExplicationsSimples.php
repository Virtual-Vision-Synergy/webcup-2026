<?php

namespace App\Filament\Resources\ExplicationsSimples\Pages;

use App\Filament\Resources\ExplicationsSimples\ExplicationSimpleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExplicationsSimples extends ManageRecords
{
    protected static string $resource = ExplicationSimpleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajouter une explication'),
        ];
    }
}
