<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * role_id n'est pas "fillable" : on l'assigne explicitement (citoyen si absent ou inconnu).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $role = Role::query()->whereKey((int) ($data['role_id'] ?? 0))->first()
            ?? Role::where('code', Role::CITOYEN)->firstOrFail();
        unset($data['role_id']);

        $user = new User($data);
        $user->role()->associate($role);
        $user->save();

        return $user;
    }
}
