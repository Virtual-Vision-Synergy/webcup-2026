<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Historique des modifications d'un élément (F48), lu dans le journal d'audit de F47 (aucune table en plus).
 *
 * Le journal stocke subject_type = nom court de la classe (« Service »), pas le nom complet :
 * d'où un hasMany filtré plutôt qu'un morphMany (un morphMap global casserait les notifications).
 *
 * @mixin Model
 */
trait HasAuditHistory
{
    /**
     * Entrées du journal concernant cet élément, de la plus récente à la plus ancienne.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')
            ->where('subject_type', class_basename(static::class))
            ->latest('id');
    }

    public function derniereModification(): ?AuditLog
    {
        return $this->auditLogs()->first();
    }
}
