<?php

namespace App\Models;

use Database\Factories\ActionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Journal des actions. Écriture uniquement par le code : ActionLog::record('deleted', $incident).
 * user_id n'est volontairement PAS remplissable : il est assigné dans record().
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
#[Fillable(['action', 'subject_type', 'subject_id', 'ip'])]
class ActionLog extends Model
{
    /** @use HasFactory<ActionLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'export' => 'Export',
        'account_deactivated' => 'Compte désactivé',
        'account_reactivated' => 'Compte réactivé',
        'login_unlocked' => 'Connexion débloquée',
        'rendez_vous_reserve' => 'Rendez-vous réservé',
        'rendez_vous_annule' => 'Rendez-vous annulé',
        'rendez_vous_statut' => 'Statut de rendez-vous modifié',
        'service_indisponible' => 'Service rendu indisponible',
        'service_retabli' => 'Service rétabli',
    ];

    /**
     * Enregistre une action de l'utilisateur connecté. Ne fait jamais échouer l'action journalisée.
     */
    public static function record(string $action, ?Model $subject = null): void
    {
        try {
            $log = new self([
                'action' => Str::limit($action, 50, ''),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip' => request()->ip(),
            ]);
            $userId = auth()->id();
            $log->user_id = $userId === null ? null : (int) $userId;
            $log->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Libellé français de l'action (l'action brute si elle est inconnue).
     */
    public function libelle(): string
    {
        return __(self::ACTION_LABELS[$this->action] ?? $this->action);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
