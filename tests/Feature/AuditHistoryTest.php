<?php

use App\Models\Annonce;
use App\Models\AuditLog;
use App\Models\LigneTransport;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function historique(string $type, int $id): string
{
    return route('agent.history.show', ['type' => $type, 'id' => $id]);
}

test('modifier un service : la fiche montre auteur, date, champ, ancienne et nouvelle valeur', function () {
    $service = Service::factory()->create(['horaires' => 'Lun-Ven 8h-16h']);
    $agent = User::factory()->agent()->create(['name' => 'Marie Rakoto']);
    $this->actingAs($agent);

    $service->update(['horaires' => 'Lun-Sam 8h-12h']);
    $log = $service->auditLogs()->first();

    expect($log->dateComplete())->toMatch('/^\d{1,2} (janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre) \d{4} à \d{2} h \d{2}$/u')
        ->and($log->dateRelative())->toStartWith('il y a');

    $this->get(route('services.show', $service))
        ->assertOk()
        ->assertSee('Historique des modifications')
        ->assertSee('Dernière modification par')
        ->assertSee('Marie Rakoto')
        ->assertSee('Agent municipal')
        ->assertSee($log->dateComplete())
        ->assertSee('Horaires')
        ->assertSee('Lun-Ven 8h-16h')
        ->assertSee('Lun-Sam 8h-12h')
        ->assertSee(historique('service', $service->id), false);

    $this->get(historique('service', $service->id))
        ->assertOk()
        ->assertSee('Marie Rakoto')
        ->assertSee('Lun-Sam 8h-12h');
});

test('l\'historique d\'une ligne de transport traduit l\'état du trafic avant / après', function () {
    $ligne = LigneTransport::factory()->create(['etat' => 'normal']);
    $agent = User::factory()->agent()->create(['name' => 'Hery Rabe']);
    $this->actingAs($agent);

    $ligne->forceFill(['etat' => 'perturbe'])->save();

    $this->get(route('transports.show', $ligne))
        ->assertOk()
        ->assertSee('Hery Rabe')
        ->assertSee('État du trafic')
        ->assertSeeInOrder(['Trafic normal', 'Perturbé']);
});

test('l\'historique d\'un message général apparaît sur sa fiche de modification', function () {
    $annonce = Annonce::factory()->active()->create(['titre' => 'Coupure d’eau']);
    $agent = User::factory()->agent()->create(['name' => 'Fara Andria']);
    $this->actingAs($agent);

    $annonce->update(['titre' => 'Coupure d’eau prolongée']);

    $this->get(route('agent.annonces.edit', $annonce))
        ->assertOk()
        ->assertSee('Fara Andria')
        ->assertSee('Titre')
        ->assertSee('Coupure d’eau prolongée');
});

test('plusieurs modifications : de la plus récente à la plus ancienne', function () {
    $service = Service::factory()->create(['horaires' => 'Horaires A']);
    $this->actingAs(User::factory()->agent()->create());

    $service->update(['horaires' => 'Horaires B']);
    $service->update(['horaires' => 'Horaires C']);

    $this->get(historique('service', $service->id))
        ->assertOk()
        ->assertSeeInOrder(['Horaires C', 'Horaires A']);
});

test('un champ sensible est affiché « [masqué] », jamais en clair', function () {
    $citoyen = User::factory()->citoyen()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $citoyen->update(['password' => 'MotDePasseSecret!']);
    $hash = $citoyen->getRawOriginal('password');

    $this->get(route('agent.citizens.show', $citoyen))
        ->assertOk()
        ->assertSee('Mot de passe')
        ->assertSee(AuditLog::MASQUE)
        ->assertDontSee('MotDePasseSecret!')
        ->assertDontSee($hash);
});

test('une valeur contenant du HTML est affichée échappée', function () {
    $service = Service::factory()->create(['horaires' => 'Lun-Ven']);
    $this->actingAs(User::factory()->agent()->create());

    $service->update(['horaires' => '<script>alert(1)</script>']);

    $this->get(historique('service', $service->id))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('un citoyen ne voit pas l\'historique sur une fiche', function () {
    $service = Service::factory()->create();
    $this->actingAs(User::factory()->agent()->create());
    $service->update(['horaires' => 'Nouveaux horaires']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.show', $service))
        ->assertOk()
        ->assertDontSee('Historique des modifications')
        ->assertDontSee('Dernière modification par');
});

test('un invité est redirigé vers la connexion', function () {
    $service = Service::factory()->create();

    $this->get(historique('service', $service->id))->assertRedirect(route('login'));
});

test('un citoyen reçoit 403 sur la page historique', function () {
    $service = Service::factory()->create();

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(historique('service', $service->id))
        ->assertForbidden();
});

test('un agent reçoit 403 sur l\'historique d\'un compte qu\'il ne peut pas voir, un admin y accède', function () {
    $autreAgent = User::factory()->agent()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(historique('compte', $autreAgent->id))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(historique('compte', $autreAgent->id))
        ->assertOk();
});

test('un type inconnu ou un nom de classe dans l\'URL renvoie 404', function (string $type) {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/agent/historique/'.$type.'/1')
        ->assertNotFound();
})->with(['inconnu', 'User', 'App%5CModels%5CUser', 'AuditLog']);

test('un élément inexistant renvoie 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(historique('service', 999999))
        ->assertNotFound();
});

test('aucune route ne permet de modifier ou supprimer une entrée d\'historique', function () {
    $methodes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'agent/historique'))
        ->flatMap(fn ($route) => $route->methods())
        ->unique()
        ->values()
        ->all();

    expect($methodes)->not->toBeEmpty()
        ->and(array_diff($methodes, ['GET', 'HEAD']))->toBe([]);
});
