<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\VueServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F98 : compteur anonymisé des consultations d'une fiche service (par jour et par quartier du visiteur).
 * Aucune donnée personnelle : ni user_id, ni IP. Rien n'est remplissable : tout est assigné dans le code.
 *
 * @property int $id
 * @property int $service_id
 * @property int|null $quartier_id
 * @property CarbonInterface $jour
 * @property int $nombre
 */
class VueService extends Model
{
    /** @use HasFactory<VueServiceFactory> */
    use HasFactory;

    protected $table = 'vues_services';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jour' => 'date',
            'nombre' => 'integer',
            'service_id' => 'integer',
            'quartier_id' => 'integer',
        ];
    }

    /**
     * Compte une consultation de la fiche (une seule par session, par service et par jour, pour ne pas gonfler le compteur).
     */
    public static function enregistrer(Service $service, ?User $visiteur): void
    {
        $cle = 'vues_services.'.$service->id.'.'.now()->toDateString();

        if (session()->has($cle)) {
            return;
        }

        session()->put($cle, true);

        $quartierId = $visiteur?->quartier_id;

        $vue = static::query()
            ->where('service_id', $service->id)
            ->where('quartier_id', $quartierId)
            ->whereDate('jour', today())
            ->first();

        if ($vue === null) {
            $vue = new static;
            $vue->service_id = $service->id;
            $vue->quartier_id = $quartierId;
            $vue->jour = today();
            $vue->nombre = 0;
            $vue->save();
        }

        $vue->increment('nombre');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }
}
