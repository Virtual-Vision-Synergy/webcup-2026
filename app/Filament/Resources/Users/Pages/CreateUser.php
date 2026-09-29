<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * role n'est pas "fillable" : on l'assigne explicitement.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $role = $data['role'] ?? 'user';
        unset($data['role']);

        $user = new User($data);
        $user->role = in_array($role, ['user', 'admin'], true) ? $role : 'user';
        $user->save();

        return $user;
    }
}
