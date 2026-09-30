<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function limiteur(): object
{
    return new class
    {
        use ThrottlesPerUser;

        public function appeler(string $action = 'test', int $max = 2): void
        {
            $this->throttlePerUser($action, $max, 60);
        }
    };
}

test('la limite bloque après le nombre d\'essais autorisé', function () {
    $this->actingAs(User::factory()->create());
    $composant = limiteur();

    $composant->appeler();
    $composant->appeler();

    expect(fn () => $composant->appeler())->toThrow(ValidationException::class);
});

test('la limite est propre à chaque utilisateur', function () {
    $composant = limiteur();

    $this->actingAs(User::factory()->create());
    $composant->appeler();
    $composant->appeler();

    $this->actingAs(User::factory()->create());
    $composant->appeler();

    expect(true)->toBeTrue();
});

test('la limite est propre à chaque action', function () {
    $this->actingAs(User::factory()->create());
    $composant = limiteur();

    $composant->appeler('ia');
    $composant->appeler('ia');
    $composant->appeler('export');

    expect(fn () => $composant->appeler('ia'))->toThrow(ValidationException::class);
});

test('le message d\'erreur est sur la clé throttle', function () {
    $this->actingAs(User::factory()->create());
    $composant = limiteur();

    $composant->appeler('x', 1);

    try {
        $composant->appeler('x', 1);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('throttle');

        return;
    }

    $this->fail('ValidationException attendue');
});
