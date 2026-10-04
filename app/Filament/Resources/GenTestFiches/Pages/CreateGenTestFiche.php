<?php

namespace App\Filament\Resources\GenTestFiches\Pages;

use App\Filament\Resources\GenTestFiches\GenTestFicheResource;
use App\Models\GenTestFiche;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGenTestFiche extends CreateRecord
{
    protected static string $resource = GenTestFicheResource::class;

    /**
     * user_id n'est pas "fillable" : l'admin crée la fiche à son nom, assigné ici.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new GenTestFiche($data);
        $record->user_id = max(0, (int) auth()->id());
        $record->save();

        return $record;
    }
}
