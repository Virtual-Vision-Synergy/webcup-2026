<?php

use App\Models\Service;
use App\Models\User;
use App\Services\ProtectionFormulaires;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * F70 : agent rattaché à tous les services existants au moment de l'appel (il voit toutes leurs démarches).
 */
function agentDeTousLesServices(): User
{
    return User::factory()->agentDe(...Service::all()->all())->create();
}

/**
 * F81 : champs anti-robots d'un formulaire public affiché il y a 10 secondes (champ piège vide + jeton valide).
 *
 * @return array<string, string>
 */
function jetonAntiRobot(string $formulaire): array
{
    // Respecte un éventuel voyage dans le temps déjà fait par le test.
    $horlogeFigee = Carbon::getTestNow();
    Carbon::setTestNow(now()->subSeconds(10));
    $jeton = app(ProtectionFormulaires::class)->jeton($formulaire);
    Carbon::setTestNow($horlogeFigee);

    return [ProtectionFormulaires::CHAMP_PIEGE => '', ProtectionFormulaires::CHAMP_JETON => $jeton];
}
