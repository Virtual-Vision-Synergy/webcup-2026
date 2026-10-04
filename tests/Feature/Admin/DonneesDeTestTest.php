<?php

use App\Filament\Pages\DonneesDeTest;
use App\Models\ActionLog;
use App\Models\Signalement;
use App\Models\User;
use App\Services\ReinitialisationDonnees;
use App\Services\Sauvegardes;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;

beforeEach(function () {
    // Pas de vrai fichier de sauvegarde pendant les tests.
    $this->mock(Sauvegardes::class, fn (MockInterface $mock) => $mock->shouldReceive('lancer')->andReturn('sauvegarde.sql.gz'));
});

test('un citoyen ne peut pas ouvrir la page de réinitialisation', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(DonneesDeTest::getUrl())
        ->assertForbidden();
});

test('un admin réinitialise les données sans perdre les comptes ni sa session', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'name' => 'Mon admin']);
    $citoyenDemo = User::factory()->citoyen()->create(['email' => 'user@example.com', 'name' => 'Renommé par le jury']);
    $citoyenDemo->forceFill(['deactivated_at' => now()])->save();
    $juryEnLigne = User::factory()->citoyen()->create(['email' => 'jury@webcup.test']);
    $saisieDuJury = Signalement::factory()->for($juryEnLigne)->create(['description' => 'Signalement saisi par le jury']);
    $hashAdmin = $admin->password;

    $this->actingAs($admin);

    Livewire::test(DonneesDeTest::class)->callAction('reinitialiser');

    expect(Signalement::find($saisieDuJury->id))->toBeNull()
        ->and(Signalement::count())->toBeGreaterThan(0)
        ->and($juryEnLigne->fresh())->not->toBeNull()
        ->and($admin->fresh()->name)->toBe('Mon admin')
        ->and($admin->fresh()->password)->toBe($hashAdmin)
        ->and($citoyenDemo->fresh()->name)->toBe('Citoyen Démo')
        ->and($citoyenDemo->fresh()->deactivated_at)->toBeNull()
        ->and(User::where('email', 'admin@example.com')->count())->toBe(1)
        ->and(ActionLog::where('action', 'donnees_reinitialisees')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('la réinitialisation peut être relancée sur une base déjà remplie', function () {
    $admin = User::factory()->admin()->create();
    $reinitialisation = app(ReinitialisationDonnees::class);

    $reinitialisation->lancer($admin);
    $comptes = User::count();
    $signalements = Signalement::count();
    $reinitialisation->lancer($admin);

    expect(User::count())->toBe($comptes)
        ->and(Signalement::count())->toBe($signalements);
});

test('si la sauvegarde échoue, aucune donnée n\'est effacée', function () {
    $this->mock(Sauvegardes::class, fn (MockInterface $mock) => $mock->shouldReceive('lancer')->andThrow(new RuntimeException('échec')));
    $admin = User::factory()->admin()->create();
    $signalement = Signalement::factory()->create();

    $this->actingAs($admin);

    Livewire::test(DonneesDeTest::class)->callAction('reinitialiser');

    expect(Signalement::find($signalement->id))->not->toBeNull();
});

test('le lien vers l\'espace admin n\'apparaît que pour les admins', function () {
    Http::fake();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('dashboard'))
        ->assertDontSee('data-test="admin-space-link"', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertSee('data-test="admin-space-link"', false);
});

test('le panneau admin propose un lien vers l\'espace citoyen', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(DonneesDeTest::getUrl())
        ->assertOk()
        ->assertSee('Espace citoyen')
        ->assertSee(route('dashboard'), false);
});
