<?php

namespace App\Filament\Resources\InterruptionsTransport\Pages;

use App\Filament\Resources\InterruptionsTransport\InterruptionTransportResource;
use App\Models\InterruptionTransport;
use App\Services\NotifierInterruptionTransport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInterruptionsTransport extends ManageRecords
{
    protected static string $resource = InterruptionTransportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Déclarer une interruption')
                // L'auteur est assigné dans le code (user_id n'est jamais remplissable).
                ->using(function (array $data): InterruptionTransport {
                    $interruption = new InterruptionTransport($data);
                    $interruption->user()->associate(auth()->user());
                    $interruption->save();

                    return $interruption;
                })
                // Après l'enregistrement des lignes : notification immédiate si l'interruption est déjà en cours.
                ->after(function (InterruptionTransport $record): void {
                    InterruptionTransport::oublierCache();
                    app(NotifierInterruptionTransport::class)->notifierSiVisible($record);
                }),
        ];
    }
}
