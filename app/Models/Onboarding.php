<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OnboardingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * État du parcours de prise en main d'un habitant (D12). La progression elle-même est calculée
 * à partir des vraies données par App\Services\OnboardingProgress.
 *
 * Aucun champ n'est remplissable : tout est assigné dans le code (OnboardingProgress), jamais depuis une requête.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $service_id
 * @property CarbonInterface|null $service_visited_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $skipped_at
 * @property list<string>|null $situation Situation déclarée pour « Par où commencer ? » (F72), null = pas encore répondu
 */
class Onboarding extends Model
{
    /** @use HasFactory<OnboardingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_visited_at' => 'datetime',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'situation' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Dernier service consulté pendant le parcours.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
