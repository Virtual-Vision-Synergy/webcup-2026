<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réponse d'un habitant à une consultation (F65). Une seule par habitant et par consultation (contrainte unique en base),
 * non modifiable : c'est la trace de sa participation.
 *
 * Aucun champ n'est remplissable : consultation_id, user_id et choix sont assignés dans le code.
 *
 * @property int $id
 * @property int $consultation_id
 * @property int $user_id
 * @property int $choix Index de l'option choisie dans Consultation::listeOptions().
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Consultation $consultation
 * @property-read User $user
 */
class ParticipationConsultation extends Model
{
    protected $table = 'participations_consultation';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'choix' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Consultation, $this>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * « 04/10/2026 à 10:42 » (heure locale) : date de la participation.
     */
    public function participeLe(): string
    {
        return Remontee::dateLocale($this->created_at, 'd/m/Y à H:i');
    }
}
