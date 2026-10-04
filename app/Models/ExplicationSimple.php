<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * F90 : explication en mots simples d'un passage administratif, rédigée à l'avance et éditable par l'admin (Filament).
 * Affichée sur demande par <livewire:explication-simple cle="..." /> ; le texte officiel fait foi.
 *
 * @property int $id
 * @property string $cle
 * @property string $titre
 * @property string|null $texte_officiel
 * @property string $explication
 * @property list<string>|null $termes
 * @property bool $actif
 */
#[Fillable(['cle', 'titre', 'texte_officiel', 'explication', 'termes', 'actif'])]
class ExplicationSimple extends Model
{
    protected $table = 'explications_simples';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'termes' => 'array',
            'actif' => 'boolean',
        ];
    }
}
