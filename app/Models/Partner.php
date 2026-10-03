<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCoordinates;
use Carbon\CarbonInterface;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Partenaire de la ville (F74) : horaires, adresse, contact et emplacement, consultables sans compte une fois publié.
 *
 * created_by, slug et is_published ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 * opening_hours : [jour ISO (1 = lundi … 7 = dimanche) => [['08:30', '12:00'], ['14:00', '17:30']]], 2 plages au plus.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property string $address
 * @property string $phone
 * @property string|null $email
 * @property string|null $website
 * @property string $latitude
 * @property string $longitude
 * @property array<int, mixed>|null $opening_hours
 * @property bool $is_published
 */
#[Fillable(['name', 'type', 'description', 'address', 'phone', 'email', 'website', 'latitude', 'longitude', 'opening_hours'])]
class Partner extends Model
{
    /** Journal d'audit (F47) ; scopes geolocalises() et proches(), pointCarte() pour <x-carte>. */
    use Auditable, HasCoordinates;

    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    public const TYPE_OPTIONS = ['sante', 'social', 'transport', 'culture', 'emploi', 'autre'];

    public const TYPE_LABELS = [
        'sante' => 'Santé',
        'social' => 'Social et solidarité',
        'transport' => 'Transport',
        'culture' => 'Culture',
        'emploi' => 'Emploi et formation',
        'autre' => 'Autre',
    ];

    /** Jours ISO (1 = lundi). */
    public const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    protected static function booted(): void
    {
        // Slug unique calculé une fois à la création : l'adresse de la fiche ne change pas si le nom est corrigé.
        static::creating(function (Partner $partner): void {
            if (blank($partner->slug)) {
                $base = Str::slug($partner->name) ?: 'partenaire';
                $slug = $base;
                $suffixe = 2;

                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$suffixe++;
                }

                $partner->slug = $slug;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Autre';
    }

    /**
     * Plages d'ouverture d'un jour ISO (1 = lundi), triées.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function hoursFor(int $isoDay): array
    {
        $plages = [];

        // JSON venu de la base : on ne garde que les plages bien formées.
        foreach ((array) ($this->opening_hours[$isoDay] ?? []) as $plage) {
            if (is_array($plage) && is_string($plage[0] ?? null) && is_string($plage[1] ?? null)) {
                $plages[] = [$plage[0], $plage[1]];
            }
        }

        usort($plages, fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $plages;
    }

    /**
     * Horaires d'un jour en texte : « 8 h 30 – 12 h 00 · 14 h 00 – 17 h 30 » ou « Fermé ».
     */
    public function hoursLabel(int $isoDay): string
    {
        $plages = $this->hoursFor($isoDay);

        if ($plages === []) {
            return 'Fermé';
        }

        return implode(' · ', array_map(fn (array $plage): string => self::heure($plage[0]).' – '.self::heure($plage[1]), $plages));
    }

    /**
     * Ouvert ou fermé à l'instant donné (fuseau de l'application), avec un libellé clair.
     *
     * @return array{open: bool, label: string}
     */
    public function openingStatus(?CarbonInterface $now = null): array
    {
        $now = ($now ?? now())->copy()->setTimezone(config('app.timezone'));
        $heure = $now->format('H:i');
        $aujourdhui = $this->hoursFor($now->dayOfWeekIso);

        foreach ($aujourdhui as [$debut, $fin]) {
            if ($heure >= $debut && $heure < $fin) {
                return ['open' => true, 'label' => 'Ouvert maintenant · ferme à '.self::heure($fin)];
            }
        }

        foreach ($aujourdhui as [$debut]) {
            if ($heure < $debut) {
                return ['open' => false, 'label' => 'Fermé · ouvre à '.self::heure($debut)];
            }
        }

        $prefixe = $aujourdhui === [] ? 'Fermé aujourd\'hui' : 'Fermé';

        for ($decalage = 1; $decalage <= 7; $decalage++) {
            $jour = $now->copy()->addDays($decalage)->dayOfWeekIso;
            $plages = $this->hoursFor($jour);

            if ($plages !== []) {
                return ['open' => false, 'label' => "{$prefixe} · ouvre ".self::JOURS[$jour].' à '.self::heure($plages[0][0])];
            }
        }

        return ['open' => false, 'label' => 'Fermé · horaires non communiqués'];
    }

    /**
     * Itinéraire OpenStreetMap jusqu'au partenaire (même service que les fiches de services, F45).
     */
    public function directionsUrl(): string
    {
        return 'https://www.openstreetmap.org/directions?to='.$this->latitude.'%2C'.$this->longitude;
    }

    public function phoneLink(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone);
    }

    /**
     * « 08:30 » → « 8 h 30 ».
     */
    public static function heure(string $hhmm): string
    {
        [$h, $m] = array_pad(explode(':', $hhmm), 2, '00');

        return ((int) $h).' h '.$m;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'opening_hours' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
