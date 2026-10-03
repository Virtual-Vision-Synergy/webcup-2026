<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAuditHistory;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
 */
#[Fillable(['nom', 'email', 'sujet', 'message'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use Auditable, HasAuditHistory, HasFactory;

    /**
     * Nom lisible dans le journal d'audit (F47).
     */
    public function auditLabel(): string
    {
        return (string) $this->sujet;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
