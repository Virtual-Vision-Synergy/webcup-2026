<?php

namespace App\Models;

use Database\Factories\AlerteCaniculeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte canicule d'un secteur de la ville, publiée par l'Agence sanitaire.
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 */
#[Fillable(['secteur', 'niveau', 'temperature_max', 'debut', 'fin', 'message'])]
class AlerteCanicule extends Model
{
    /** @use HasFactory<AlerteCaniculeFactory> */
    use HasFactory;

    protected $table = 'alertes_canicule';

    public const NIVEAU_OPTIONS = ['vigilance', 'alerte', 'urgence'];

    public const NIVEAU_LABELS = [
        'vigilance' => 'Vigilance',
        'alerte' => 'Alerte',
        'urgence' => 'Urgence',
    ];

    public const PROFIL_OPTIONS = ['personnes_agees', 'enfants', 'femmes_enceintes', 'malades_chroniques', 'travailleurs_exterieur'];

    public const PROFIL_LABELS = [
        'personnes_agees' => 'Personnes âgées',
        'enfants' => 'Enfants',
        'femmes_enceintes' => 'Femmes enceintes',
        'malades_chroniques' => 'Malades chroniques',
        'travailleurs_exterieur' => 'Travailleurs en extérieur',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alertes en cours : commencées et non terminées.
     *
     * @param  Builder<AlerteCanicule>  $query
     * @return Builder<AlerteCanicule>
     */
    public function scopeActives(Builder $query): Builder
    {
        return $query->whereDate('debut', '<=', today())
            ->where(fn (Builder $q) => $q->whereNull('fin')->orWhereDate('fin', '>=', today()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'date',
            'fin' => 'date',
            'temperature_max' => 'integer',
        ];
    }
}
