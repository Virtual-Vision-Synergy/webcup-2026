<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCoordinates;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Partenaire de la ville (F74) : horaires, adresse et emplacement consultables sans compte.
 *
 * created_by, slug et is_published ne sont volontairement PAS remplissables : ils sont assignés dans le code
 * (auteur = agent connecté, slug généré à la création, publication décidée par un agent après authorize).
 *
 * opening_hours : ['lundi' => [['start' => '08:30', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:30']], ...]
 * (0 à 2 plages par jour, heure de Nova Terra).
 */
#[Fillable(['name', 'type', 'description', 'address', 'phone', 'email', 'website', 'latitude', 'longitude', 'opening_hours'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use Auditable, HasCoordinates, HasFactory;

    /** Les horaires sont ceux de Nova Terra (et non app.timezone, en UTC). */
    public const FUSEAU = Annonce::FUSEAU;

    public const TYPE_OPTIONS = ['sante', 'social', 'transport', 'culture', 'emploi', 'autre'];

    public const TYPE_LABELS = [
        'sante' => 'Santé',
        'social' => 'Social et solidarité',
        'transport' => 'Transport',
        'culture' => 'Culture',
        'emploi' => 'Emploi et formation',
        'autre' => 'Autre',
    ];

    public const TYPE_ICONES = [
        'sante' => 'heart',
        'social' => 'user-group',
        'transport' => 'truck',
        'culture' => 'musical-note',
        'emploi' => 'briefcase',
        'autre' => 'building-storefront',
    ];

    /** Jours de la semaine, dans l'ordre ISO (lundi = 1). */
    public const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    /** Nombre maximal de plages par jour. */
    public const PLAGES_PAR_JOUR = 2;

    /** Slugs qui entreraient en conflit avec des routes. */
    private const RESERVED_SLUGS = ['create'];

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

    protected static function booted(): void
    {
        static::creating(function (Partner $partner): void {
            if (blank($partner->slug)) {
                $partner->slug = self::uniqueSlug((string) $partner->name);
            }
        });
    }

    /**
     * Slug unique dérivé du nom : « maison-de-l-emploi », puis « maison-de-l-emploi-2 »…
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'partenaire';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  Builder<Partner>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public static function labelType(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Autre';
    }

    public static function iconeType(?string $type): string
    {
        return self::TYPE_ICONES[$type] ?? 'building-storefront';
    }

    /**
     * Plages d'un jour, triées et nettoyées.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function hoursFor(string $jour): array
    {
        $plages = [];
        $semaine = $this->getAttribute('opening_hours');
        $plagesBrutes = is_array($semaine) && is_array($semaine[$jour] ?? null) ? $semaine[$jour] : [];

        foreach ($plagesBrutes as $plage) {
            if (is_array($plage) && is_string($plage['start'] ?? null) && is_string($plage['end'] ?? null)) {
                $plages[] = ['start' => $plage['start'], 'end' => $plage['end']];
            }
        }

        usort($plages, fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        return $plages;
    }

    /**
     * Horaires d'un jour en texte : « 8 h 30 – 12 h 00, 14 h 00 – 17 h 30 » ou « Fermé ».
     */
    public function hoursLabel(string $jour): string
    {
        $plages = $this->hoursFor($jour);

        if ($plages === []) {
            return 'Fermé';
        }

        return implode(', ', array_map(
            fn (array $plage): string => self::heure($plage['start']).' – '.self::heure($plage['end']),
            $plages,
        ));
    }

    public static function today(?CarbonInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(self::FUSEAU);

        return self::JOURS[$now->dayOfWeekIso - 1];
    }

    /**
     * « Ouvert / fermé maintenant », calculé en heure de Nova Terra.
     *
     * @return array{open: bool, label: string, color: string, icon: string}
     */
    public function openingStatus(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(self::FUSEAU);
        $heure = $now->format('H:i');
        $aujourdhui = self::JOURS[$now->dayOfWeekIso - 1];
        $plages = $this->hoursFor($aujourdhui);

        foreach ($plages as $plage) {
            if ($heure >= $plage['start'] && $heure < $plage['end']) {
                return self::statut(true, 'Ouvert maintenant · ferme à '.self::heure($plage['end']));
            }

            if ($heure < $plage['start']) {
                return self::statut(false, 'Fermé · ouvre à '.self::heure($plage['start']));
            }
        }

        // Plus d'ouverture aujourd'hui : prochain jour ouvert dans la semaine qui vient.
        for ($decalage = 1; $decalage <= 7; $decalage++) {
            $jour = self::JOURS[$now->addDays($decalage)->dayOfWeekIso - 1];
            $suivantes = $this->hoursFor($jour);

            if ($suivantes !== []) {
                $quand = $decalage === 1 ? 'demain' : $jour;
                $prefixe = $plages === [] ? 'Fermé aujourd\'hui' : 'Fermé';

                return self::statut(false, "{$prefixe} · ouvre {$quand} à ".self::heure($suivantes[0]['start']));
            }
        }

        return self::statut(false, 'Fermé aujourd\'hui');
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
     * Itinéraire OpenStreetMap vers le partenaire (comme les services, F45).
     */
    public function directionsUrl(): string
    {
        return 'https://www.openstreetmap.org/directions?to='.(float) $this->latitude.'%2C'.(float) $this->longitude;
    }

    public function telLink(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', (string) $this->phone);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * F99 : services proposés par ce partenaire dans le catalogue.
     *
     * @return HasMany<PartnerOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(PartnerOffering::class);
    }

    /**
     * F99 : comptes partenaires rattachés (users.partner_id, assigné par un admin).
     *
     * @return HasMany<User, $this>
     */
    public function comptes(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * F99 : plages saisies dans un formulaire (« hours.lundi.0.start »…) → format opening_hours, lignes vides retirées.
     * Chaque plage doit commencer avant de finir et ne pas chevaucher la précédente.
     *
     * @param  array<string, mixed>  $saisie
     * @return array<string, array<int, array{start: string, end: string}>>
     *
     * @throws ValidationException
     */
    public static function horairesDepuisSaisie(array $saisie, string $champ = 'hours'): array
    {
        $semaine = [];
        $erreurs = [];

        foreach (self::JOURS as $jour) {
            $plages = [];

            foreach (array_slice((array) ($saisie[$jour] ?? []), 0, self::PLAGES_PAR_JOUR) as $i => $plage) {
                $debut = (string) (is_array($plage) ? ($plage['start'] ?? '') : '');
                $fin = (string) (is_array($plage) ? ($plage['end'] ?? '') : '');

                if ($debut === '' && $fin === '') {
                    continue;
                }

                if ($debut >= $fin) {
                    $erreurs["{$champ}.{$jour}.{$i}.end"] = "Le {$jour}, l'heure de fermeture doit être après l'heure d'ouverture.";
                } elseif ($plages !== [] && $debut < end($plages)['end']) {
                    $erreurs["{$champ}.{$jour}.{$i}.start"] = "Le {$jour}, la seconde plage doit commencer après la fin de la première.";
                }

                $plages[] = ['start' => $debut, 'end' => $fin];
            }

            $semaine[$jour] = $plages;
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        return $semaine;
    }

    /**
     * @return array{open: bool, label: string, color: string, icon: string}
     */
    private static function statut(bool $open, string $label): array
    {
        return [
            'open' => $open,
            'label' => $label,
            'color' => $open ? 'green' : 'red',
            'icon' => $open ? 'check-circle' : 'x-circle',
        ];
    }
}
