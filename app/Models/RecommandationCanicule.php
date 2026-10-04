<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * F31 : conseils écrits de l'Agence sanitaire pour un profil et un niveau d'alerte (un conseil par ligne).
 * Rédigés et modifiés par les admins (Filament, RecommandationCaniculePolicy) ; aucune IA.
 *
 * @property int $id
 * @property string $profil
 * @property string $niveau
 * @property string $conseils
 */
#[Fillable(['profil', 'niveau', 'conseils'])]
class RecommandationCanicule extends Model
{
    protected $table = 'recommandations_canicule';

    /**
     * Conseils du profil pour ce niveau ; à défaut (texte supprimé par l'admin), ceux du « tout public ».
     *
     * @return list<string>
     */
    public static function pour(string $profil, string $niveau): array
    {
        $recommandations = self::query()
            ->where('niveau', $niveau)
            ->whereIn('profil', [$profil, 'tout_public'])
            ->pluck('conseils', 'profil');

        return self::lignes((string) ($recommandations[$profil] ?? $recommandations['tout_public'] ?? ''));
    }

    /**
     * @return list<string>
     */
    public static function lignes(string $conseils): array
    {
        return array_values(array_filter(
            array_map(fn (string $ligne): string => trim($ligne, " \t-•"), preg_split('/\R/', $conseils) ?: []),
            fn (string $ligne): bool => $ligne !== '',
        ));
    }
}
