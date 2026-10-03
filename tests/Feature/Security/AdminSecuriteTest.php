<?php

use App\Filament\Pages\AlertesSecurite;
use App\Models\User;
use App\Services\SecurityMonitor;
use Livewire\Livewire;

/*
| F69 : /admin/securite et /admin/alertes-securite décrivent la défense de la plateforme : administrateurs uniquement.
*/

test('un habitant et un agent reçoivent 403 sur les pages de sécurité de l’admin', function (string $role, string $url) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get($url)
        ->assertForbidden();
})->with(['citoyen', 'agent'])->with(['/admin/securite', '/admin/alertes-securite']);

test('un invité est redirigé vers la connexion', function (string $url) {
    $this->get($url)->assertRedirect();
    $this->assertGuest();
})->with(['/admin/securite', '/admin/alertes-securite']);

test('l’admin voit le récapitulatif et le résultat de security:check', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/securite')
        ->assertOk()
        ->assertSee('APP_DEBUG désactivé')
        ->assertSee('En-tête Content-Security-Policy')
        ->assertSee('Ce qui a été audité et corrigé');
});

test('l’admin voit les alertes de sécurité et leur nombre dans le menu', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs(User::factory()->citoyen()->create())->get(route('agent.tableau-de-bord'));

    expect(AlertesSecurite::getNavigationBadge())->toBe('1');

    Livewire::actingAs($admin)
        ->test(AlertesSecurite::class)
        ->assertOk()
        ->assertSee('Accès refusé')
        ->assertSee('agent.tableau-de-bord');

    expect(SecurityMonitor::types())->toHaveKey(SecurityMonitor::BLOCAGE);
});
