<?php

use App\Models\Actualite;
use App\Models\Demarche;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\Avis;
use App\Services\NotifierAnnonce;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * F95 : sobriété — nombre de requêtes SQL des pages clés sous un seuil, aucune requête en double, page publique « Sobriété ».
 */

/**
 * Habitant avec des données réalistes partout où une page pourrait lancer une requête par ligne.
 */
function habitantAvecActivite(): User
{
    $user = User::factory()->create();

    Service::factory()->count(6)->create(['mis_en_avant' => true]);
    Service::factory()->count(4)->create();
    Actualite::factory()->count(6)->create();
    Demarche::factory()->count(6)->for($user)->create();
    Signalement::factory()->count(6)->for($user)->create();
    $user->notify(new Avis('Premier avis', ['Bonjour']));
    $user->notify(new Avis('Second avis'));

    return $user;
}

/**
 * Requêtes SQL (texte + paramètres) lancées pour afficher une page, cache vide (cas le plus défavorable).
 *
 * @return list<string>
 */
function requetesSqlDeLaPage(string $url, ?User $user = null): array
{
    Cache::flush();
    // Le filet des annonces programmées (une fois par minute) n'est pas une requête de la page.
    Cache::put(NotifierAnnonce::VERROU, true, 60);
    // F94 : la régénération des infos essentielles, différée par la création des services de test, n'est pas une requête de la page.
    app(DeferredCallbackCollection::class)->forget('infos-essentielles');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $reponse = $user === null ? test()->get($url) : test()->actingAs($user)->get($url);

    DB::disableQueryLog();
    $reponse->assertOk();

    return array_map(
        fn (array $requete): string => $requete['query'].' | '.json_encode($requete['bindings']),
        DB::getQueryLog(),
    );
}

dataset('pages clés', [
    'accueil' => ['home', false, 10],
    'services' => ['services.index', true, 12],
    'actualités' => ['actualites.index', true, 9],
    'mes demandes' => ['mes-demandes.index', true, 9],
    'mon espace' => ['dashboard', true, 16],
]);

test('une page clé reste sous son seuil de requêtes SQL', function (string $route, bool $connecte, int $seuil) {
    $user = habitantAvecActivite();

    $requetes = requetesSqlDeLaPage(route($route), $connecte ? $user : null);

    expect(count($requetes))->toBeLessThanOrEqual($seuil, implode("\n", $requetes));
})->with('pages clés');

test('une page clé ne lance aucune requête SQL en double', function (string $route, bool $connecte) {
    $user = habitantAvecActivite();

    $requetes = requetesSqlDeLaPage(route($route), $connecte ? $user : null);

    expect(array_keys(array_filter(array_count_values($requetes), fn (int $fois): bool => $fois > 1)))->toBe([]);
})->with('pages clés');

test('mon espace ne lance pas une requête de plus par service prioritaire', function () {
    $user = User::factory()->create();
    Service::factory()->count(1)->create(['mis_en_avant' => true]);
    $avecUnService = count(requetesSqlDeLaPage(route('dashboard'), $user->fresh()));

    Service::factory()->count(3)->create(['mis_en_avant' => true]);
    $avecQuatreServices = count(requetesSqlDeLaPage(route('dashboard'), $user->fresh()));

    expect($avecQuatreServices)->toBe($avecUnService);
});

test('la page sobriété est publique et affiche les mesures des 5 pages', function () {
    $this->get(route('sobriete.show'))
        ->assertOk()
        ->assertSeeHtml('data-test="tableau-sobriete"')
        ->assertSeeInOrder(['Accueil', 'Services', 'Actualités', 'Mes demandes', 'Mon espace']);
});

test('un habitant connecté peut aussi consulter la page sobriété', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('sobriete.show'))
        ->assertOk()
        ->assertSee('Ce qui a été réduit');
});

test('hors production, l’en-tête Server-Timing compte les requêtes SQL en double', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('Server-Timing')
        ->assertHeaderContains('Server-Timing', 'doublons;desc="0 requetes SQL en double"');
});
