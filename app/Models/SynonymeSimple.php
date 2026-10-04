<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * F90 : règle « mot administratif → mot simple » (« justificatif » → « document qui prouve »).
 * Gérée par les admins (Filament) ; appliquée au texte officiel d'un passage par App\Services\SimplificateurRegles.
 *
 * @property int $id
 * @property string $mot
 * @property string $equivalent
 */
#[Fillable(['mot', 'equivalent'])]
class SynonymeSimple extends Model
{
    protected $table = 'synonymes_simples';
}
