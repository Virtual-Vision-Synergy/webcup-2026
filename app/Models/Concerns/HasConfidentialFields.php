<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * F70 : données confidentielles d'un modèle (liste blanche).
 *
 * Le modèle déclare confidentialFields() : champ => [libellé, lecture de la valeur]. Ces valeurs ne sont jamais
 * affichées directement dans l'espace agent : elles passent par le composant <livewire:donnee-confidentielle>,
 * qui exige un motif, vérifie la policy « viewConfidential » et journalise chaque consultation (sans la valeur).
 *
 * @mixin Model
 */
trait HasConfidentialFields
{
    /**
     * @return array<string, array{label: string, valeur: Closure(): (string|null)}>
     */
    abstract public function confidentialFields(): array;

    public function hasConfidentialField(string $champ): bool
    {
        return array_key_exists($champ, $this->confidentialFields());
    }

    public function confidentialLabel(string $champ): string
    {
        return $this->confidentialFields()[$champ]['label'] ?? abort(404);
    }

    /**
     * Valeur en clair : à n'appeler qu'après l'autorisation et la journalisation de la consultation.
     */
    public function confidentialValue(string $champ): ?string
    {
        $lecture = $this->confidentialFields()[$champ]['valeur'] ?? abort(404);

        $valeur = $lecture();

        return $valeur === null || $valeur === '' ? null : $valeur;
    }
}
