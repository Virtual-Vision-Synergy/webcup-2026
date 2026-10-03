<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
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
     * role_id n'est pas "fillable" : il passe par la policy updateRole (jamais sur son propre compte)
     * puis par User::changerRole (qui protège le dernier administrateur).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $role = Role::query()->whereKey((int) ($data['role_id'] ?? 0))->first();
        unset($data['role_id']);

        $record->fill($data)->save();

        if ($role && $role->id !== $record->role_id) {
            $this->authorize('updateRole', $record);

            try {
                $record->changerRole($role);
            } catch (\DomainException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();

                throw new Halt;
            }
        }

        return $record;
    }
}
