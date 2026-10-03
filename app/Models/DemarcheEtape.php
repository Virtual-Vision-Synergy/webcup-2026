<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Étape datée du suivi d'une démarche (D11) : enregistrée par Demarche::changerStatut().
 *
 * Aucun champ n'est remplissable : statut, commentaire, user_id et demarche_id sont assignés dans le code.
 *
 * @property int $id
 * @property int $demarche_id
 * @property string $statut
 * @property string|null $commentaire
 * @property int|null $user_id
 */
class DemarcheEtape extends Model
{
    /**
     * @return BelongsTo<Demarche, $this>
     */
    public function demarche(): BelongsTo
    {
        return $this->belongsTo(Demarche::class);
    }

    /**
     * Agent qui a changé l'état (null si son compte a été supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
