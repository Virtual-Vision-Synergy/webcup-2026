<?php

namespace App\Filament\Resources\GenTestFiches\Pages;

use App\Filament\Resources\GenTestFiches\GenTestFicheResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGenTestFiche extends EditRecord
{
    protected static string $resource = GenTestFicheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
