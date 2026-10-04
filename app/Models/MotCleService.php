<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D10 : mot-clé ou synonyme qui oriente vers un service (« poubelle » → Environnement et propreté).
 * Géré uniquement par les admins (Filament, MotCleServicePolicy) ; utilisé par App\Services\OrientationServices.
 *
 * @property int $id
 * @property int $service_id
 * @property string $mot
 * @property-read Service $service
 */
#[Fillable(['service_id', 'mot'])]
class MotCleService extends Model
{
    protected $table = 'mots_cles_service';

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
