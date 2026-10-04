<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalise automatiquement crÃ©ation, modification et suppression du modÃ¨le (F47).
 *
 * Sur une modification, les changements Â« mÃ©tier Â» ont leur propre entrÃ©e :
 *   - role_id         â†’ role_changed (libellÃ©s de rÃ´le avant / aprÃ¨s)
 *   - deactivated_at  â†’ deactivated / reactivated
 *   - statut          â†’ status_changed (libellÃ©s de statut si le modÃ¨le a libelleStatut())
 *   - le reste        â†’ updated
 *
 * Champs jamais journalisÃ©s : AUDIT_IGNORE ci-dessous + $auditIgnore du modÃ¨le (facultatif).
 * Les seeders passent par WithoutModelEvents : ils ne remplissent pas le journal.
 *
 * @mixin Model
 */
trait Auditable
{
    /** @var list<string> */
    private static array $auditIgnoreDefaut = ['id', 'created_at', 'updated_at', 'remember_token', 'email_verified_at', 'role'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&self $model */
            $changes = [];
            foreach ($model->auditableValues($model->getAttributes()) as $champ => $valeur) {
                $changes[$champ] = ['avant' => null, 'apres' => $valeur];
            }

            AuditLogger::log('created', $model, $changes);
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $model->auditUpdate();
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            $changes = [];
            foreach ($model->auditableValues($model->getRawOriginal()) as $champ => $valeur) {
                $changes[$champ] = ['avant' => $valeur, 'apres' => null];
            }

            AuditLogger::log('deleted', $model, $changes);
        });
    }

    protected function auditUpdate(): void
    {
        $modifies = $this->auditableValues($this->getChanges());

        if (array_key_exists('role_id', $modifies)) {
            AuditLogger::log('role_changed', $this, ['role' => [
                'avant' => $this->libelleRole($this->getRawOriginal('role_id')),
                'apres' => $this->libelleRole($modifies['role_id']),
            ]]);
            unset($modifies['role_id']);
        }

        if (array_key_exists('deactivated_at', $modifies)) {
            AuditLogger::log($modifies['deactivated_at'] === null ? 'reactivated' : 'deactivated', $this, ['deactivated_at' => [
                'avant' => $this->getRawOriginal('deactivated_at'),
                'apres' => $modifies['deactivated_at'],
            ]]);
            unset($modifies['deactivated_at']);
        }

        if (array_key_exists('statut', $modifies)) {
            AuditLogger::log('status_changed', $this, ['statut' => [
                'avant' => $this->libelleStatutAudit($this->getRawOriginal('statut')),
                'apres' => $this->libelleStatutAudit($modifies['statut']),
            ]]);
            unset($modifies['statut']);
        }

        if ($modifies === []) {
            return;
        }

        $changes = [];
        foreach ($modifies as $champ => $valeur) {
            $changes[$champ] = ['avant' => $this->getRawOriginal($champ), 'apres' => $valeur];
        }

        AuditLogger::log('updated', $this, $changes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableValues(array $attributes): array
    {
        $ignores = array_merge(self::$auditIgnoreDefaut, property_exists($this, 'auditIgnore') ? $this->auditIgnore : []);

        return array_diff_key($attributes, array_flip($ignores));
    }

    /**
     * LibellÃ© du statut si le modÃ¨le dÃ©clare STATUT_LABELS (Â« deposee Â» â†’ Â« DÃ©posÃ©e Â»), sinon la valeur brute.
     */
    private function libelleStatutAudit(mixed $statut): mixed
    {
        $constante = static::class.'::STATUT_LABELS';
        $libelles = defined($constante) ? constant($constante) : [];

        return is_string($statut) && is_array($libelles) && isset($libelles[$statut]) ? $libelles[$statut] : $statut;
    }

    private function libelleRole(mixed $roleId): ?string
    {
        if ($roleId === null) {
            return null;
        }

        return Role::query()->whereKey($roleId)->value('label') ?? '#'.$roleId;
    }
}
