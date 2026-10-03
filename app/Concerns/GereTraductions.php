<?php

namespace App\Concerns;

use App\Models\Concerns\HasTraductions;
use App\Models\Traduction;
use Illuminate\Database\Eloquent\Model;

/**
 * Saisie des traductions dans les formulaires Livewire : propriété `$traductions[locale][champ]`.
 * Le composant doit déjà avoir autorisé la modification de la fiche avant d'appeler enregistrerTraductions().
 */
trait GereTraductions
{
    /** @var array<string, array<string, string>> */
    public array $traductions = [];

    /**
     * @param  Model&HasTraductions  $fiche
     */
    protected function chargerTraductions(?Model $fiche = null): void
    {
        $existantes = $fiche?->traductions->keyBy('locale');

        foreach (array_keys(Traduction::LANGUES_TRADUITES) as $locale) {
            $this->traductions[$locale] = [
                'titre' => (string) ($existantes?->get($locale)?->titre ?? ''),
                'description' => (string) ($existantes?->get($locale)?->description ?? ''),
                'horaires' => (string) ($existantes?->get($locale)?->horaires ?? ''),
            ];
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function reglesTraductions(): array
    {
        $regles = [];

        foreach (array_keys(Traduction::LANGUES_TRADUITES) as $locale) {
            $regles["traductions.{$locale}.titre"] = ['nullable', 'string', 'max:255'];
            $regles["traductions.{$locale}.description"] = ['nullable', 'string', 'max:5000'];
            $regles["traductions.{$locale}.horaires"] = ['nullable', 'string', 'max:2000'];
        }

        return $regles;
    }

    /**
     * Crée, met à jour ou supprime (si tout est vide) la traduction de chaque langue.
     *
     * @param  Model&HasTraductions  $fiche
     */
    protected function enregistrerTraductions(Model $fiche): void
    {
        foreach (array_keys(Traduction::LANGUES_TRADUITES) as $locale) {
            $champs = array_map(fn ($v): ?string => filled($v) ? trim((string) $v) : null, $this->traductions[$locale] ?? []);
            $existante = $fiche->traductions()->where('locale', $locale)->first();

            if (blank(array_filter($champs))) {
                $existante?->delete();

                continue;
            }

            $traduction = $existante ?? new Traduction;
            $traduction->fill($champs);
            $traduction->locale = $locale;
            $traduction->traduisible()->associate($fiche);
            $traduction->save();
        }
    }
}
