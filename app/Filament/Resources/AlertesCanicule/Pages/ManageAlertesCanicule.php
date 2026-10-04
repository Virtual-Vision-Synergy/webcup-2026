<?php

namespace App\Filament\Resources\AlertesCanicule\Pages;

use App\Filament\Resources\AlertesCanicule\AlerteCaniculeResource;
use App\Models\AlerteCanicule;
use App\Services\NotifierCanicule;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAlertesCanicule extends ManageRecords
{
    protected static string $resource = AlerteCaniculeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Déclencher une alerte')
                // L'auteur est assigné dans le code (user_id n'est jamais remplissable).
                ->using(function (array $data): AlerteCanicule {
                    $alerte = new AlerteCanicule($data);
                    $alerte->user()->associate(auth()->user());
                    $alerte->save();

                    return $alerte;
                })
                // Après l'enregistrement des quartiers : notification immédiate si l'alerte est déjà en cours.
                ->after(function (AlerteCanicule $record): void {
                    AlerteCanicule::oublierCache();
                    app(NotifierCanicule::class)->notifierSiVisible($record);
                }),
        ];
    }
}
