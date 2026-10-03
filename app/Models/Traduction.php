<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Version traduite d'une fiche (service, démarche) dans une langue autre que le français.
 * Le français reste la langue de référence, stockée sur la fiche elle-même.
 *
 * traduisible_type, traduisible_id et locale sont assignés dans le code.
 */
#[Fillable(['titre', 'description', 'horaires'])]
class Traduction extends Model
{
    public const LANGUE_PAR_DEFAUT = 'fr';

    /** Langues proposées (code => libellé dans sa propre langue). */
    public const LANGUES = ['fr' => 'Français', 'en' => 'English', 'mg' => 'Malagasy'];

    /** Langues pour lesquelles on saisit une traduction (toutes sauf le français). */
    public const LANGUES_TRADUITES = ['en' => 'English', 'mg' => 'Malagasy'];

    /**
     * Langue choisie par le visiteur (session), sinon le français.
     */
    public static function langueCourante(): string
    {
        $langue = session('langue');

        return is_string($langue) && array_key_exists($langue, self::LANGUES) ? $langue : self::LANGUE_PAR_DEFAUT;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function traduisible(): MorphTo
    {
        return $this->morphTo();
    }
}
