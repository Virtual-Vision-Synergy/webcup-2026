<?php

namespace App\Filament\Resources\Signalements\Pages;

use App\Filament\Resources\Signalements\SignalementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSignalements extends ListRecords
{
    protected static string $resource = SignalementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
