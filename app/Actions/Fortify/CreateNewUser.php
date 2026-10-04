<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use App\Services\ComptesHabitants;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * F71 : l'e-mail est facultatif si un numéro de téléphone est donné ; le compte reçoit alors
     * un identifiant d'habitant et l'habitant se connecte avec son téléphone (ou cet identifiant) et son code personnel.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['telephone'] = User::normaliserTelephone($input['telephone'] ?? null) ?? (filled($input['telephone'] ?? null) ? $input['telephone'] : null);

        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => ['nullable', 'required_without:telephone', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'telephone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9]{6,30}$/', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ], [
            'email.required_without' => __('Indiquez une adresse e-mail ou un numéro de téléphone.'),
            'telephone.required_without' => __('Indiquez une adresse e-mail ou un numéro de téléphone.'),
            'telephone.unique' => __('Ce numéro de téléphone est déjà utilisé.'),
        ])->validate();

        $user = new User([
            'name' => $input['name'],
            'password' => $input['password'],
            'telephone' => $input['telephone'],
        ]);

        if (filled($input['email'] ?? null)) {
            $user->email = $input['email'];
        } else {
            $identifiant = app(ComptesHabitants::class)->genererIdentifiant();
            $user->forceFill(['identifiant' => $identifiant, 'email' => ComptesHabitants::emailTechnique($identifiant)]);
        }

        $user->role_id = Role::idFor(Role::CITOYEN);
        $user->save();

        return $user;
    }
}
