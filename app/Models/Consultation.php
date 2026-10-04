<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Consultation des habitants sur une décision de la ville (F65) : créée par un agent, ouverte à tous ou à un quartier.
 * Chaque habitant concerné répond une seule fois ; à la clôture, la répartition et la décision sont publiées aux participants.
 *
 * user_id, decision et decision_le ne sont volontairement PAS remplissables : ils sont assignés dans le code.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $quartier_id
 * @property string $question
 * @property string $explication
 * @property string $options Une option de réponse par ligne, dans l'ordre.
 * @property Carbon $ouverture_le
 * @property Carbon $cloture_le
 * @property string|null $decision
 * @property Carbon|null $decision_le
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Quartier|null $quartier
 */
#[Fillable(['question', 'explication', 'options', 'quartier_id', 'ouverture_le', 'cloture_le'])]
class Consultation extends Model
{
    public const STATUT_LABELS = [
        'a_venir' => 'À venir',
        'ouverte' => 'Ouverte',
        'cloturee' => 'Clôturée',
    ];

    /**
     * Valeur attendue par <x-tn.status-badge>.
     */
    public const STATUT_BADGES = [
        'a_venir' => 'info',
        'ouverte' => 'normal',
        'cloturee' => 'perturbe',
    ];

    public const OPTIONS_MIN = 2;

    public const OPTIONS_MAX = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quartier_id' => 'integer',
            'ouverture_le' => 'datetime',
            'cloture_le' => 'datetime',
            'decision_le' => 'datetime',
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
     * @return BelongsTo<Quartier, $this>
     */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }

    /**
     * @return HasMany<ParticipationConsultation, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ParticipationConsultation::class);
    }

    /**
     * Consultations qui concernent cet habitant : celles de toute la ville et celles de son quartier.
     *
     * @param  Builder<Consultation>  $query
     */
    public function scopePourHabitant(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('quartier_id');

            if ($user->quartier_id !== null) {
                $query->orWhere('quartier_id', $user->quartier_id);
            }
        });
    }

    /**
     * @param  Builder<Consultation>  $query
     */
    public function scopeOuvertes(Builder $query): void
    {
        $query->where('ouverture_le', '<=', now())->where('cloture_le', '>', now());
    }

    public function concerne(User $user): bool
    {
        return $this->quartier_id === null || $this->quartier_id === $user->quartier_id;
    }

    /**
     * Options de réponse dans l'ordre (l'index sert de valeur de réponse).
     *
     * @return array<int, string>
     */
    public function listeOptions(): array
    {
        return self::decouperOptions($this->options);
    }

    /**
     * @return array<int, string>
     */
    public static function decouperOptions(?string $options): array
    {
        return collect(preg_split('/\R/', (string) $options) ?: [])
            ->map(fn (string $option) => trim($option))
            ->filter()
            ->values()
            ->all();
    }

    public function statut(): string
    {
        return match (true) {
            $this->ouverture_le->isFuture() => 'a_venir',
            $this->cloture_le->isFuture() => 'ouverte',
            default => 'cloturee',
        };
    }

    public function estOuverte(): bool
    {
        return $this->statut() === 'ouverte';
    }

    public function estCloturee(): bool
    {
        return $this->statut() === 'cloturee';
    }

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut()];
    }

    public function statutBadge(): string
    {
        return self::STATUT_BADGES[$this->statut()];
    }

    public function nomPublic(): string
    {
        return $this->quartier ? 'Quartier '.$this->quartier->nom : 'Tous les habitants';
    }

    /**
     * Réponse de l'habitant (jamais celle d'un autre : filtrée par user_id).
     */
    public function participationDe(User $user): ?ParticipationConsultation
    {
        return $this->participations()->where('user_id', $user->id)->first();
    }

    /**
     * Répartition des réponses : une ligne par option, dans l'ordre.
     *
     * @return array<int, array{option: string, total: int, pourcentage: int}>
     */
    public function repartition(): array
    {
        $parChoix = $this->participations()->selectRaw('choix, count(*) as total')->groupBy('choix')->pluck('total', 'choix');
        $total = (int) $parChoix->sum();

        return collect($this->listeOptions())
            ->map(function (string $option, int $index) use ($parChoix, $total): array {
                $nombre = (int) ($parChoix[$index] ?? 0);

                return [
                    'option' => $option,
                    'total' => $nombre,
                    'pourcentage' => $total > 0 ? (int) round($nombre * 100 / $total) : 0,
                ];
            })
            ->all();
    }

    public function ouvertureLocale(): string
    {
        return Remontee::dateLocale($this->ouverture_le, 'd/m/Y à H:i');
    }

    public function clotureLocale(): string
    {
        return Remontee::dateLocale($this->cloture_le, 'd/m/Y à H:i');
    }
}
