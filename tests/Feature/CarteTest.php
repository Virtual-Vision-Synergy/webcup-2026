<?php

use App\Models\Signalement;
use App\View\Components\Carte;
use Illuminate\Support\Facades\Blade;

/**
 * Lit la configuration JSON transmise à resources/js/carte.js.
 *
 * @return array<string, mixed>
 */
function configCarte(string $html): array
{
    preg_match('/data-carte="([^"]*)"/', $html, $matches);

    return json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);
}

test('le composant carte en lecture affiche ses points, centrés par défaut sur Antananarivo', function () {
    $html = Blade::render('<x-carte :points="$points" label="Carte des points" hauteur="15rem" />', [
        'points' => [['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Analakely', 'url' => '/signalements/1']],
    ]);

    expect($html)
        ->toContain('wire:ignore')
        ->toContain('aria-label="Carte des points"')
        ->toContain('height: 15rem')
        ->not->toContain('Me localiser');

    expect(configCarte($html))->toMatchArray([
        'mode' => 'lecture',
        'centre' => [-18.91, 47.52],
        'points' => [['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Analakely', 'url' => '/signalements/1']],
    ]);
});

test('le composant carte en mode choix propose « Me localiser » et cible latitude / longitude', function () {
    $html = Blade::render('<x-carte mode="choix" />');

    expect($html)->toContain('Me localiser')->toContain('aria-live="polite"');
    expect(configCarte($html))->toMatchArray(['mode' => 'choix', 'champLat' => 'latitude', 'champLng' => 'longitude']);
});

test('le composant carte échappe un titre contenant du HTML', function () {
    $html = Blade::render('<x-carte :points="$points" />', [
        'points' => [['lat' => -18.9, 'lng' => 47.5, 'titre' => '<script>alert("x")</script>']],
    ]);

    expect($html)->not->toContain('<script>alert');
    expect(configCarte($html)['points'][0]['titre'])->toBe('<script>alert("x")</script>');
});

test('le composant carte ignore les points invalides et les liens dangereux', function () {
    $carte = new Carte(points: [
        ['lat' => -18.9, 'lng' => 47.5, 'titre' => 'A', 'url' => 'javascript:alert(1)'],
        ['lat' => -18.9, 'lng' => 47.5, 'titre' => 'B', 'url' => '//evil.example'],
        ['lat' => 'abc', 'lng' => 47.5],
        ['lat' => 95, 'lng' => 47.5],
        null,
    ], hauteur: '10px; background: red');

    expect($carte->points)->toHaveCount(2)
        ->and($carte->points[0]['url'])->toBeNull()
        ->and($carte->points[1]['url'])->toBeNull()
        ->and($carte->hauteur)->toBe('20rem');
});

test('le composant carte refuse un mode inconnu', function () {
    new Carte(mode: 'edition');
})->throws(InvalidArgumentException::class);

test('le scope proches trie du plus proche au plus lointain et respecte la limite', function () {
    $loin = Signalement::factory()->create(['latitude' => -18.80, 'longitude' => 47.60]);
    $proche = Signalement::factory()->create(['latitude' => -18.911, 'longitude' => 47.521]);
    $moyen = Signalement::factory()->create(['latitude' => -18.95, 'longitude' => 47.50]);
    Signalement::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(Signalement::proches(-18.91, 47.52, 10)->pluck('id')->all())->toBe([$proche->id, $moyen->id, $loin->id])
        ->and(Signalement::proches(-18.91, 47.52, 2)->pluck('id')->all())->toBe([$proche->id, $moyen->id]);
});

test('le scope geolocalises exclut les enregistrements sans position', function () {
    Signalement::factory()->create();
    Signalement::factory()->create(['latitude' => null]);
    Signalement::factory()->create(['longitude' => null]);

    expect(Signalement::geolocalises()->count())->toBe(1);
});

test('la page détail d\'un signalement affiche sa carte', function () {
    $signalement = Signalement::factory()->create(['titre' => 'Inondation <b>Isoraka</b>']);

    $response = $this->actingAs($signalement->user)->get(route('signalements.show', $signalement));

    $response->assertOk()->assertSee('data-carte', false)->assertDontSee('<b>Isoraka</b>', false);
});
