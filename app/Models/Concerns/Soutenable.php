<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Mécanisme de soutien d'habitants (F52), partagé par les signalements et les idées (F68).
 *
 * Le modèle fournit sa relation soutiens() (table avec contrainte unique user + élément)
 * et nouveauSoutien() (soutien non enregistré, rattaché à l'élément). user_id est assigné ici,
 * jamais depuis le navigateur. Les règles d'accès (qui peut soutenir) restent dans chaque Policy.
 *
 * @mixin Model
 */
trait Soutenable
{
    /**
     * @return HasMany<covariant Model, $this>
     */
    abstract public function soutiens(): HasMany;

    abstract protected function nouveauSoutien(): Model;

    public function estSoutenuPar(User $user): bool
    {
        return $this->soutiens()->where('user_id', $user->id)->exists();
    }

    /**
     * Enregistre le soutien de l'habitant (sans effet s'il soutient déjà).
     */
    public function ajouterSoutien(User $user): void
    {
        if ($this->estSoutenuPar($user)) {
            return;
        }

        $soutien = $this->nouveauSoutien();
        $soutien->setAttribute('user_id', $user->id);

        try {
            $soutien->save();
        } catch (UniqueConstraintViolationException) {
            // Double clic simultané : la contrainte unique a déjà enregistré le soutien.
        }
    }

    public function retirerSoutien(User $user): void
    {
        $this->soutiens()->where('user_id', $user->id)->delete();
    }
}
