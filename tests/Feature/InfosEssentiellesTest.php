<?php

use App\Filament\Pages\InfosEssentielles as PageInfosEssentielles;
use App\Models\Service;
use App\Models\User;
use App\Support\InfosEssentielles;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    // Un composant Livewire rendu par un test précédent ne doit pas faire injecter ses scripts dans cette page.
    Livewire::flushState();
});

/**
 * Base de données coupée : toute nouvelle requête SQL échoue (fichier SQLite introuvable).
 * Le résultat de $requete est renvoyé après avoir rétabli la base (nécessaire au nettoyage du test).
 */
function avecBaseCoupee(Closure $requete): mixed
{
    $connexionParDefaut = config('database.default');
    config([
        'database.connections.panne' => ['driver' => 'sqlite', 'database' => '/chemin/introuvable/base.sqlite', 'prefix' => ''],
        'database.default' => 'panne',
    ]);

    try {
        return $requete();
    } finally {
        config(['database.default' => $connexionParDefaut]);
    }
}

test('la page « Infos essentielles » est publique, légère et donne urgences, mairie et état des services', function () {
    $response = $this->get(route('infos-essentielles'))
        ->assertOk()
        ->assertSee('Infos essentielles')
        ->assertSee('tel:117', false)
        ->assertSee(InfosEssentielles::MAIRIE['telephone'])
        ->assertSee('Lundi au vendredi')
        ->assertSee('Tous les services municipaux fonctionnent normalement.');

    expect($response->getContent())->not->toContain('<script')->not->toContain('<img');
    Storage::disk('local')->assertExists(InfosEssentielles::FICHIER);
});

test('base coupée : la version statique est toujours servie avec la consigne en cours (200)', function () {
    InfosEssentielles::publier('Coupure de la plateforme', 'Les démarches en ligne reprendront ce soir.', 'alerte');

    $response = avecBaseCoupee(fn () => $this->get(route('infos-essentielles')));

    $response->assertOk()
        ->assertSee('Coupure de la plateforme')
        ->assertSee('Les démarches en ligne reprendront ce soir.')
        ->assertSee('tel:118', false);
});

test('base coupée sans version statique : une version de secours est servie (200)', function () {
    $response = avecBaseCoupee(fn () => $this->get(route('infos-essentielles')));

    $response->assertOk()
        ->assertSee('tel:124', false)
        ->assertSee(InfosEssentielles::MAIRIE['adresse'])
        ->assertSee('data-test="etat-inconnu"', false);
});

test('la version statique est régénérée quand un service devient indisponible', function () {
    $this->withoutDefer();
    $service = Service::factory()->create(['nom' => 'État civil']);
    InfosEssentielles::regenerer();

    $service->rendreIndisponible('Panne du logiciel d’état civil');

    $html = Storage::disk('local')->get(InfosEssentielles::FICHIER);
    expect($html)->toContain('État civil')->toContain('Panne du logiciel d’état civil');
});

test('un admin publie une consigne d’incident affichée en bandeau sur toutes les pages', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PageInfosEssentielles::class)
        ->callAction('publier', data: [
            'titre' => 'Incident en cours',
            'message' => 'Le paiement en ligne est suspendu.',
            'niveau' => 'alerte',
        ])
        ->assertHasNoErrors();

    expect(InfosEssentielles::consigne())->toMatchArray(['titre' => 'Incident en cours', 'niveau' => 'alerte']);
    expect(Storage::disk('local')->get(InfosEssentielles::FICHIER))->toContain('Le paiement en ligne est suspendu.');

    $this->get(route('home'))
        ->assertSee('data-test="bandeau-consigne-incident"', false)
        ->assertSee('Le paiement en ligne est suspendu.')
        ->assertSee(route('infos-essentielles'), false);
});

test('un admin retire la consigne : le bandeau disparaît', function () {
    InfosEssentielles::publier('Incident en cours', 'Message', 'information');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(PageInfosEssentielles::class)->callAction('retirer');

    expect(InfosEssentielles::consigne())->toBeNull();
    $this->get(route('home'))->assertDontSee('data-test="bandeau-consigne-incident"', false);
});

test('un citoyen ne peut pas publier de consigne (403)', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(PageInfosEssentielles::getUrl())
        ->assertForbidden();
});

test('les pages d’erreur et hors ligne renvoient vers les infos essentielles', function () {
    $this->get('/partenaires/inconnu')
        ->assertNotFound()
        ->assertSee('data-test="lien-infos-essentielles"', false);

    $this->get(route('hors-ligne'))
        ->assertOk()
        ->assertSee(route('infos-essentielles'), false);

    expect(view('errors.500')->render())->toContain('/infos-essentielles');
});
