<?php

use Illuminate\Support\Facades\Blade;

/*
 * F43 : l'information n'est jamais portée par la seule couleur, et la palette d'état reste lisible (WCAG AA).
 */

/**
 * Lit les variables de couleur hexadécimales d'un bloc de resources/css/app.css.
 *
 * @return array<string, string>
 */
function couleursDuBloc(string $debutDuBloc): array
{
    $css = (string) file_get_contents(resource_path('css/app.css'));
    $debut = strpos($css, $debutDuBloc);
    expect($debut)->not->toBeFalse();

    $bloc = substr($css, (int) $debut, (int) strpos($css, '}', (int) $debut) - (int) $debut);
    preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9A-Fa-f]{6})\s*;/', $bloc, $correspondances);

    return array_combine($correspondances[1], $correspondances[2]);
}

/**
 * @return array{0: float, 1: float, 2: float}
 */
function rgbDepuisHex(string $hex): array
{
    return [hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $rgb
 */
function luminanceRelative(array $rgb): float
{
    $lineaire = array_map(fn (float $v): float => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb);

    return 0.2126 * $lineaire[0] + 0.7152 * $lineaire[1] + 0.0722 * $lineaire[2];
}

/**
 * @param  array{0: float, 1: float, 2: float}  $a
 * @param  array{0: float, 1: float, 2: float}  $b
 */
function rapportDeContraste(array $a, array $b): float
{
    $la = luminanceRelative($a);
    $lb = luminanceRelative($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

dataset('themes', [
    'clair' => ['@theme {'],
    'sombre' => ['.dark {'],
]);

test('les couleurs d’état respectent le contraste WCAG AA sur les fonds et dans les badges teintés', function (string $bloc) {
    $couleurs = couleursDuBloc($bloc);

    foreach (['cyan', 'magenta', 'amber', 'green', 'ink', 'ink-2'] as $etat) {
        foreach (['night', 'surface', 'surface-2'] as $fond) {
            $texte = rgbDepuisHex($couleurs[$etat]);
            $arrierePlan = rgbDepuisHex($couleurs[$fond]);
            // Badge : fond = couleur de l'état à 12 % sur la surface (les badges utilisent 8 %).
            $fondTeinte = array_map(fn (float $c, float $f): float => $c * 0.12 + $f * 0.88, $texte, $arrierePlan);

            expect(rapportDeContraste($texte, $arrierePlan))->toBeGreaterThanOrEqual(4.5, "{$etat} sur {$fond}")
                ->and(rapportDeContraste($texte, $fondTeinte))->toBeGreaterThanOrEqual(4.5, "{$etat} sur {$fond} teinté");
        }
    }
})->with('themes');

test('chaque état de badge a une icône de forme différente en plus du texte', function () {
    $rendus = collect(['normal', 'perturbe', 'alerte', 'info'])
        ->mapWithKeys(fn (string $etat): array => [$etat => Blade::render('<x-tn.status-badge :etat="$etat">Libellé</x-tn.status-badge>', ['etat' => $etat])]);

    $rendus->each(function (string $html, string $etat) {
        expect($html)->toContain('<svg', 'Libellé', "data-etat=\"{$etat}\"");
    });

    $icones = $rendus->map(fn (string $html): string => (string) preg_replace('/\s+/', ' ', (string) strstr((string) strstr($html, '<svg'), '</svg>', true)));
    expect($icones->unique())->toHaveCount(4);
});

test('la chronologie donne l’état de chaque étape en texte pour les lecteurs d’écran', function () {
    $html = Blade::render('<x-tn.timeline :items="$items" />', ['items' => [
        ['label' => 'Démarche déposée', 'etat' => 'info', 'fait' => true],
        ['label' => 'Décision : Refusée', 'etat' => 'alerte', 'fait' => true],
        ['label' => 'Clôture', 'etat' => 'normal', 'fait' => false],
    ]]);

    expect($html)->toContain('étape franchie, état : information', 'étape franchie, état : alerte', 'étape à venir');
});
