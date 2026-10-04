<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F27 : traduction d'une fiche de service dans une langue (config app.langues, hors français).
 *
 * service_id et locale ne sont volontairement PAS remplissables : le service vient de la route et la langue
 * de la liste des langues proposées, tous deux assignés dans le code (Service::enregistrerTraduction).
 *
 * @property int $id
 * @property int $service_id
 * @property string $locale
 * @property string|null $nom
 * @property string|null $description
 * @property string|null $horaires
 * @property string|null $adresse
 * @property string|null $lieu_rendez_vous
 * @property string|null $pieces_a_fournir
 */
#[Fillable(['nom', 'description', 'horaires', 'adresse', 'lieu_rendez_vous', 'pieces_a_fournir'])]
class ServiceTranslation extends Model
{
    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
