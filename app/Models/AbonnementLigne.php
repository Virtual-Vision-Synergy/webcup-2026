<?php

namespace App\Models;

use Database\Factories\AbonnementLigneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F97 : trajet habituel d'un habitant (ligne + arrêt). Il est prévenu (cloche, e-mail) quand la ligne est interrompue.
 *
 * @property int $id
 * @property int $user_id
 * @property int $ligne_transport_id
 * @property string|null $arret
 * @property-read LigneTransport $ligne
 *
 * user_id et ligne_transport_id ne sont volontairement PAS remplissables : utilisateur connecté et ligne de la route.
 */
#[Fillable(['arret'])]
class AbonnementLigne extends Model
{
    /** @use HasFactory<AbonnementLigneFactory> */
    use HasFactory;

    protected $table = 'abonnements_ligne';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LigneTransport, $this>
     */
    public function ligne(): BelongsTo
    {
        return $this->belongsTo(LigneTransport::class, 'ligne_transport_id');
    }
}
