<?php

namespace App\Models\Concerns;

use App\Models\Traduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contenus multilingues : le modèle déclare TRADUCTION_CHAMPS (colonne du modèle => colonne de Traduction).
 * Si la traduction manque (ou le champ est vide), on retombe sur le français.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Traduction> $traductions
 */
trait HasTraductions
{
    /**
     * @return MorphMany<Traduction, $this>
     */
    public function traductions(): MorphMany
    {
        return $this->morphMany(Traduction::class, 'traduisible');
    }

    /**
     * Charge uniquement la traduction de la langue courante (évite le N+1 dans les listes).
     *
     * @param  Builder<static>  $query
     */
    public function scopeAvecTraduction(Builder $query): void
    {
        $langue = Traduction::langueCourante();

        if ($langue !== Traduction::LANGUE_PAR_DEFAUT) {
            $query->with(['traductions' => fn ($q) => $q->where('locale', $langue)]);
        }
    }

    /**
     * Valeur d'un champ dans la langue courante, avec repli sur le français.
     */
    public function traduit(string $champ): ?string
    {
        $original = $this->getAttribute($champ);
        $langue = Traduction::langueCourante();
        $colonne = static::TRADUCTION_CHAMPS[$champ] ?? null;

        if ($langue === Traduction::LANGUE_PAR_DEFAUT || $colonne === null) {
            return $original;
        }

        $traductions = $this->relationLoaded('traductions') ? $this->traductions : $this->traductions()->get();
        $valeur = $traductions->firstWhere('locale', $langue)?->{$colonne};

        return filled($valeur) ? $valeur : $original;
    }

    /**
     * Vrai si la fiche existe dans la langue courante (sert au bandeau « traduction indisponible »).
     */
    public function estTraduit(): bool
    {
        $langue = Traduction::langueCourante();

        if ($langue === Traduction::LANGUE_PAR_DEFAUT) {
            return true;
        }

        $traductions = $this->relationLoaded('traductions') ? $this->traductions : $this->traductions()->get();

        return $traductions->contains(fn (Traduction $t): bool => $t->locale === $langue && filled($t->titre));
    }
}
