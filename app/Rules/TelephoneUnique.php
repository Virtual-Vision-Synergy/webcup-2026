<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * F69 : le téléphone est chiffré en base, l'unicité se vérifie donc sur son empreinte (telephone_hash).
 * Un numéro ne peut appartenir qu'à un seul compte (sinon la connexion par téléphone F71 devient impossible).
 */
class TelephoneUnique implements ValidationRule
{
    public function __construct(private ?int $ignorerUserId = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        $existe = User::query()
            ->where('telephone_hash', User::hashTelephone($value))
            ->when($this->ignorerUserId !== null, fn ($query) => $query->whereKeyNot($this->ignorerUserId))
            ->exists();

        if ($existe) {
            $fail(__('Ce numéro de téléphone est déjà utilisé.'));
        }
    }
}
