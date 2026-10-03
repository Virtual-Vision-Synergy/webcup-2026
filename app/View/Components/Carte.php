<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * Carte Leaflet / OpenStreetMap réutilisable.
 *
 * Lecture : <x-carte :points="$points" />
 * Choix   : <x-carte mode="choix" /> dans un composant Livewire qui a des propriétés latitude et longitude.
 *
 * Chaque point : ['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Texte', 'url' => route(...)] (titre et url facultatifs).
 */
class Carte extends Component
{
    public const MODES = ['lecture', 'choix'];

    /** Antananarivo. */
    public const CENTRE_DEFAUT = [-18.91, 47.52];

    /** États reconnus pour colorer un marqueur. */
    public const ETATS = ['normal', 'perturbe', 'alerte', 'info'];

    /** @var array<int, array{lat: float, lng: float, titre: string, url: string|null, etat: string|null}> */
    public array $points;

    /**
     * @param  iterable<int, array<string, mixed>|null>  $points
     * @param  array{0: float, 1: float}|null  $centre
     */
    public function __construct(
        iterable $points = [],
        public ?array $centre = null,
        public int $zoom = 13,
        public string $hauteur = '20rem',
        public string $mode = 'lecture',
        public string $label = 'Carte',
        public string $champLat = 'latitude',
        public string $champLng = 'longitude',
    ) {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Mode de carte inconnu : {$mode} (lecture ou choix).");
        }

        $this->points = $this->normaliser($points);
        $this->centre = $centre ?? self::CENTRE_DEFAUT;
        $this->zoom = max(1, min(19, $zoom));

        // La hauteur finit dans un attribut style : on n'accepte qu'une valeur CSS simple.
        if (! preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', $hauteur)) {
            $this->hauteur = '20rem';
        }
    }

    /**
     * Configuration transmise à resources/js/carte.js (encodée en JSON dans un attribut data-).
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'points' => $this->points,
            'centre' => array_map('floatval', $this->centre ?? self::CENTRE_DEFAUT),
            'zoom' => $this->zoom,
            'mode' => $this->mode,
            'champLat' => $this->champLat,
            'champLng' => $this->champLng,
        ];
    }

    public function render(): View
    {
        return view('components.carte');
    }

    /**
     * Garde les points aux coordonnées valides et les liens sûrs (relatifs ou http/https).
     *
     * @param  iterable<int, array<string, mixed>|null>  $points
     * @return array<int, array{lat: float, lng: float, titre: string, url: string|null, etat: string|null}>
     */
    private function normaliser(iterable $points): array
    {
        $resultat = [];

        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }

            $lat = $point['lat'] ?? null;
            $lng = $point['lng'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                continue;
            }

            $url = isset($point['url']) ? (string) $point['url'] : null;

            if ($url !== null && ! preg_match('#^(https?://|/(?!/))#i', $url)) {
                $url = null;
            }

            $resultat[] = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'titre' => (string) ($point['titre'] ?? ''),
                'url' => $url,
                // État affiché par la couleur du marqueur (liste fermée, sinon marqueur standard).
                'etat' => in_array($point['etat'] ?? null, self::ETATS, true) ? $point['etat'] : null,
            ];
        }

        return $resultat;
    }
}
