<?php

namespace App\Models;

use Database\Factories\RegleAssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * F91 : règle de l'assistant d'orientation : des expressions d'habitant (« mot de passe oublié ») → une réponse et un lien.
 * Gérée uniquement par les admins (Filament, RegleAssistantPolicy) ; utilisée par App\Services\AssistantOrientation.
 *
 * @property int $id
 * @property string $declencheurs Expressions séparées par des virgules.
 * @property string $reponse
 * @property string|null $lien_libelle
 * @property string|null $lien_url Chemin interne (« /rendez-vous/prendre »).
 * @property bool $actif
 */
#[Fillable(['declencheurs', 'reponse', 'lien_libelle', 'lien_url', 'actif'])]
class RegleAssistant extends Model
{
    /** @use HasFactory<RegleAssistantFactory> */
    use HasFactory;

    protected $table = 'regles_assistant';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    /**
     * Chemin interne au site (« /rendez-vous/prendre ») : pas d'URL externe, ni « //domaine », ni « javascript: ».
     */
    public static function lienInterneValide(?string $lien): bool
    {
        return $lien !== null && preg_match('#^/(?![/\\\\])[A-Za-z0-9/_\-?=&.%]*$#', $lien) === 1;
    }

    /**
     * @return list<string>
     */
    public function expressions(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->declencheurs)), fn (string $e): bool => $e !== ''));
    }
}
