<?php

namespace App\Filament\Resources\GenTestFiches\Pages;

use App\Filament\Resources\GenTestFiches\GenTestFicheResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGenTestFiche extends ViewRecord
{
    protected static string $resource = GenTestFicheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
