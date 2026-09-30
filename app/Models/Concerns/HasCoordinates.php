<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pour les modèles qui ont des colonnes latitude et longitude (décimales, facultatives).
 *
 *   Signalement::geolocalises()->get();
 *   Signalement::proches(-18.91, 47.52, 5)->get();   // les 5 plus proches, du plus proche au plus loin
 *   $signalement->pointCarte($signalement->titre, route('signalements.show', $signalement));
 */
trait HasCoordinates
{
    /**
     * Enregistrements qui ont une position.
     *
     * @param  Builder<static>  $query
     */
    public function scopeGeolocalises(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('latitude'))
            ->whereNotNull($this->qualifyColumn('longitude'));
    }

    /**
     * Les $limite enregistrements les plus proches d'un point, du plus proche au plus lointain.
     *
     * Tri par distance au carré (pas de racine ni de trigonométrie en SQL : compatible SQLite et MariaDB).
     * L'écart de longitude est corrigé par cos(latitude), calculé en PHP, pour rester juste loin de l'équateur.
     *
     * @param  Builder<static>  $query
     */
    public function scopeProches(Builder $query, float $lat, float $lng, int $limite = 10): void
    {
        $facteur = cos(deg2rad($lat));

        // SQL littéral, valeurs uniquement en paramètres liés (?) : aucune injection possible.
        $query->geolocalises()
            ->orderByRaw(
                '((latitude - ?) * (latitude - ?)) + ((longitude - ?) * ? * (longitude - ?) * ?) asc',
                [$lat, $lat, $lng, $facteur, $lng, $facteur],
            )
            ->limit(max(1, $limite));
    }

    /**
     * Point prêt pour le composant <x-carte :points="...">, ou null sans position.
     *
     * @return array{lat: float, lng: float, titre: string, url: string|null}|null
     */
    public function pointCarte(string $titre = '', ?string $url = null): ?array
    {
        $lat = $this->getAttribute('latitude');
        $lng = $this->getAttribute('longitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng, 'titre' => $titre, 'url' => $url];
    }
}
