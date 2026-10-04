<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * « Me prévenir quand disponible » (F99) : un abonnement par habitant et par service partenaire.
 *
 * Aucun champ n'est remplissable : user_id = utilisateur connecté, partner_offering_id = service de la page,
 * notified_at = renseigné par PartnerOffering::prevenirAbonnes().
 *
 * @property int $id
 * @property int $user_id
 * @property int $partner_offering_id
 * @property Carbon|null $notified_at
 * @property-read User $user
 * @property-read PartnerOffering $offering
 */
class AvailabilitySubscription extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'partner_offering_id' => 'integer',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Abonnement actif (pas encore prévenu) de l'utilisateur pour ce service, s'il existe.
     */
    public static function actifPour(User $user, PartnerOffering $offering): ?self
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('partner_offering_id', $offering->id)
            ->whereNull('notified_at')
            ->first();
    }

    /**
     * Crée l'abonnement (ou le réarme s'il a déjà servi) : jamais de doublon grâce à l'index unique.
     */
    public static function abonner(User $user, PartnerOffering $offering): self
    {
        $abonnement = self::query()
            ->where('user_id', $user->id)
            ->where('partner_offering_id', $offering->id)
            ->first() ?? new self;

        $abonnement->user()->associate($user);
        $abonnement->offering()->associate($offering);
        $abonnement->notified_at = null;
        $abonnement->save();

        return $abonnement;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PartnerOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(PartnerOffering::class, 'partner_offering_id');
    }
}
