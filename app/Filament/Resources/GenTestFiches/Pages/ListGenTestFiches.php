<?php

namespace App\Filament\Resources\GenTestFiches\Pages;

use App\Filament\Resources\GenTestFiches\GenTestFicheResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGenTestFiches extends ListRecords
{
    protected static string $resource = GenTestFicheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
