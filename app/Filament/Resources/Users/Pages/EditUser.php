<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (User $record): bool => $record->is(auth()->user())),
        ];
    }

    /**
     * role n'est pas "fillable" : on l'assigne explicitement,
     * et jamais sur son propre compte.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $role = $data['role'] ?? null;
        unset($data['role']);

        $record->fill($data);

        if (in_array($role, ['user', 'admin'], true) && ! $record->is(auth()->user())) {
            $record->role = $role;
        }

        $record->save();

        return $record;
    }
}
