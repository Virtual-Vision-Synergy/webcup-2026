<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ServiceInterruptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Interruption d'un service municipal (F38) : maintenance ou incident.
 *
 * service_id, debut_at, created_by, retabli_at et retabli_par ne sont volontairement PAS remplissables :
 * ils sont assignés dans le code (service de la route, agent connecté, action « Rétablir »).
 * retour_prevu_at est informatif : seul « Rétablir » rend le service de nouveau disponible.
 *
 * @property CarbonInterface $debut_at
 * @property CarbonInterface|null $retour_prevu_at
 * @property CarbonInterface|null $retabli_at
 */
#[Fillable(['type', 'motif', 'retour_prevu_at', 'alternative', 'alternative_service_id'])]
class ServiceInterruption extends Model
{
    /** @use HasFactory<ServiceInterruptionFactory> */
    use HasFactory;

    public const TYPE_OPTIONS = ['maintenance', 'incident'];

    public const TYPE_LABELS = [
        'maintenance' => 'Maintenance',
        'incident' => 'Incident',
    ];

    /** Message affiché quand une démarche est tentée sur un service interrompu. */
    public const MESSAGE_DEMARCHE_SUSPENDUE = 'Ce service est momentanément indisponible : la démarche est suspendue pendant l’interruption. Consultez la fiche du service pour savoir quand revenir ou quoi faire en attendant.';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debut_at' => 'datetime',
            'retour_prevu_at' => 'datetime',
            'retabli_at' => 'datetime',
            'alternative_service_id' => 'integer',
        ];
    }

    /**
     * Fuseau d'affichage et de saisie (heure de Nova Terra, comme les rendez-vous). Stockage en UTC.
     */
    public static function fuseau(): string
    {
        return (string) config('rendez_vous.fuseau', 'UTC');
    }

    /**
     * Interruptions en cours : commencées et non rétablies.
     *
     * @param  Builder<ServiceInterruption>  $query
     */
    public function scopeEnCours(Builder $query): void
    {
        $query->whereNull('retabli_at')->where('debut_at', '<=', now());
    }

    public function estEnCours(): bool
    {
        return $this->retabli_at === null && $this->debut_at->lessThanOrEqualTo(now());
    }

    public function libelleType(): string
    {
        return self::TYPE_LABELS[$this->type] ?? Str::ucfirst((string) $this->type);
    }

    /**
     * Badge de statut : « perturbe » (ambre) pour une maintenance, « alerte » (magenta) pour un incident.
     */
    public function etatBadge(): string
    {
        return $this->type === 'incident' ? 'alerte' : 'perturbe';
    }

    public function retourPrevuDepasse(): bool
    {
        return $this->retour_prevu_at !== null && $this->retour_prevu_at->isPast();
    }

    /** « samedi 3 octobre à 14 h », « lundi 5 octobre à 9 h 30 » (heure de Nova Terra). */
    public static function libelleDate(CarbonInterface $date): string
    {
        $locale = $date->copy()->setTimezone(self::fuseau())->settings(['locale' => 'fr']);
        $heure = $locale->format('G').' h'.($locale->minute > 0 ? ' '.$locale->format('i') : '');

        return $locale->translatedFormat('l j F').' à '.$heure;
    }

    /**
     * Phrase de retour affichée aux habitants (jamais « disponible » tant que le service n'est pas rétabli).
     */
    public function libelleRetour(): string
    {
        if ($this->retour_prevu_at === null) {
            return 'Date de retour pas encore connue';
        }

        if ($this->retourPrevuDepasse()) {
            return 'Retour annoncé pour le '.self::libelleDate($this->retour_prevu_at).' — mise à jour en cours par la mairie';
        }

        return 'Retour prévu : '.self::libelleDate($this->retour_prevu_at);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function alternativeService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'alternative_service_id');
    }

    /**
     * Agent qui a déclaré l'interruption (jamais affiché côté habitant).
     *
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function retablissement(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retabli_par');
    }
}
