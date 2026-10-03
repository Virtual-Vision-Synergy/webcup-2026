<?php

use App\Models\Concerns\HasCoordinates;
use App\View\Components\Carte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;

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
        'points' => [['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Analakely', 'url' => '/points/1']],
    ]);

    expect($html)
        ->toContain('wire:ignore')
        ->toContain('aria-label="Carte des points"')
        ->toContain('height: 15rem')
        ->not->toContain('Me localiser');

    expect(configCarte($html))->toMatchArray([
        'mode' => 'lecture',
        'centre' => [-18.91, 47.52],
        'points' => [['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Analakely', 'url' => '/points/1', 'etat' => null]],
    ]);
});

test('le composant carte ne garde que les états de marqueur connus', function () {
    $html = Blade::render('<x-carte :points="$points" />', [
        'points' => [
            ['lat' => -18.9, 'lng' => 47.5, 'titre' => 'A', 'etat' => 'alerte'],
            ['lat' => -18.8, 'lng' => 47.4, 'titre' => 'B', 'etat' => '<script>'],
        ],
    ]);

    expect(array_column(configCarte($html)['points'], 'etat'))->toBe(['alerte', null]);
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

/**
 * Modèle jetable (table créée à la volée) pour tester le trait HasCoordinates sans dépendre d'une fonctionnalité.
 */
function pointDeTest(?float $latitude, ?float $longitude): Model
{
    if (! Schema::hasTable('points_de_test')) {
        Schema::create('points_de_test', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
    }

    $modele = new class extends Model
    {
        use HasCoordinates;

        protected $table = 'points_de_test';

        public $timestamps = false;

        protected $guarded = [];
    };

    return $modele->newQuery()->create(['latitude' => $latitude, 'longitude' => $longitude]);
}

test('le scope proches trie du plus proche au plus lointain et respecte la limite', function () {
    $loin = pointDeTest(-18.80, 47.60);
    $proche = pointDeTest(-18.911, 47.521);
    $moyen = pointDeTest(-18.95, 47.50);
    pointDeTest(null, null);

    expect($loin::proches(-18.91, 47.52, 10)->pluck('id')->all())->toBe([$proche->id, $moyen->id, $loin->id])
        ->and($loin::proches(-18.91, 47.52, 2)->pluck('id')->all())->toBe([$proche->id, $moyen->id]);
});

test('le scope geolocalises exclut les enregistrements sans position', function () {
    $complet = pointDeTest(-18.9, 47.5);
    pointDeTest(null, 47.5);
    pointDeTest(-18.9, null);

    expect($complet::geolocalises()->count())->toBe(1);
});
