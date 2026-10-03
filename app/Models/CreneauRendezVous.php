<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CreneauRendezVousFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Créneau de rendez-vous d'un service (F39) : un créneau = une place. Heures stockées en UTC.
 *
 * service_id et rendez_vous_id ne sont volontairement PAS remplissables : le créneau est créé par
 * appointments:generate-slots et n'est réservé ou libéré que par PriseDeRendezVous.
 *
 * @property int $id
 * @property int $service_id
 * @property CarbonImmutable $debut
 * @property CarbonImmutable $fin
 * @property int|null $rendez_vous_id
 * @property-read Service $service
 * @property-read RendezVous|null $rendezVous
 */
#[Fillable(['debut', 'fin'])]
class CreneauRendezVous extends Model
{
    /** @use HasFactory<CreneauRendezVousFactory> */
    use HasFactory;

    protected $table = 'creneaux_rendez_vous';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'rendez_vous_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Rendez-vous confirmé qui occupe actuellement le créneau.
     *
     * @return BelongsTo<RendezVous, $this>
     */
    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }

    /**
     * @param  Builder<CreneauRendezVous>  $query
     */
    public function scopeLibres(Builder $query): void
    {
        $query->whereNull('rendez_vous_id');
    }

    /**
     * Créneaux qui commencent après le délai minimum de réservation.
     *
     * @param  Builder<CreneauRendezVous>  $query
     */
    public function scopeReservables(Builder $query): void
    {
        $query->where('debut', '>=', self::premierDebutReservable());
    }

    public static function premierDebutReservable(): CarbonInterface
    {
        return now()->addMinutes((int) config('rendez_vous.delai_reservation_minutes', 0));
    }

    public static function fuseau(): string
    {
        return (string) config('rendez_vous.fuseau', 'UTC');
    }

    public function estLibre(): bool
    {
        return $this->rendez_vous_id === null;
    }

    public function estReservable(): bool
    {
        return $this->debut->greaterThanOrEqualTo(self::premierDebutReservable());
    }

    /**
     * Jour local (Y-m-d) du créneau, pour regrouper l'affichage par jour.
     */
    public function jourLocal(): string
    {
        return $this->debut->setTimezone(self::fuseau())->format('Y-m-d');
    }

    /** « Mardi 6 octobre 2026 » */
    public function libelleDate(): string
    {
        return Str::ucfirst($this->debut->setTimezone(self::fuseau())->settings(['locale' => 'fr'])->translatedFormat('l j F Y'));
    }

    /** « 09 h 30 » */
    public function libelleHeureDebut(): string
    {
        return self::heure($this->debut);
    }

    /** « 10 h 00 » */
    public function libelleHeureFin(): string
    {
        return self::heure($this->fin);
    }

    /** « 09 h 30 à 10 h 00 (heure de Nova Terra) » */
    public function libelleHoraire(): string
    {
        return $this->libelleHeureDebut().' à '.$this->libelleHeureFin().' ('.config('rendez_vous.libelle_fuseau').')';
    }

    /** « Mardi 6 octobre 2026 — 09 h 30 à 10 h 00 (heure de Nova Terra) » */
    public function libelleComplet(): string
    {
        return $this->libelleDate().' — '.$this->libelleHoraire();
    }

    private static function heure(CarbonInterface $moment): string
    {
        return $moment->setTimezone(self::fuseau())->format('H \h i');
    }
}
