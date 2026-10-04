<?php

namespace App\Models;

use Database\Factories\TentativeBloqueeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * F81 : envoi de formulaire bloqué par la protection anti-robots.
 *
 * Aucun champ n'est remplissable : les lignes sont créées uniquement par App\Services\ProtectionFormulaires
 * (affectation explicite côté serveur). Aucune donnée saisie (mot de passe, message…) n'est conservée.
 *
 * @property int $id
 * @property string $formulaire
 * @property string $motif
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class TentativeBloquee extends Model
{
    /** @use HasFactory<TentativeBloqueeFactory> */
    use HasFactory, MassPrunable;

    protected $table = 'tentatives_bloquees';

    public const UPDATED_AT = null;

    public const FORMULAIRE_CONNEXION = 'connexion';

    public const FORMULAIRE_INSCRIPTION = 'inscription';

    public const FORMULAIRE_CONTACT = 'contact';

    public const FORMULAIRE_SIGNALEMENT = 'signalement';

    public const FORMULAIRE_MOT_DE_PASSE = 'mot_de_passe';

    /** @var array<string, string> */
    public const FORMULAIRE_OPTIONS = [
        self::FORMULAIRE_CONNEXION => 'Connexion',
        self::FORMULAIRE_INSCRIPTION => 'Inscription',
        self::FORMULAIRE_CONTACT => 'Contact mairie',
        self::FORMULAIRE_SIGNALEMENT => 'Signalement',
        self::FORMULAIRE_MOT_DE_PASSE => 'Mot de passe oublié',
    ];

    public const MOTIF_HONEYPOT = 'honeypot';

    public const MOTIF_TROP_RAPIDE = 'trop_rapide';

    public const MOTIF_JETON_INVALIDE = 'jeton_invalide';

    public const MOTIF_DEBIT = 'debit';

    /** @var array<string, string> */
    public const MOTIF_OPTIONS = [
        self::MOTIF_HONEYPOT => 'Champ piège rempli',
        self::MOTIF_TROP_RAPIDE => 'Envoi trop rapide',
        self::MOTIF_JETON_INVALIDE => 'Formulaire contourné ou expiré',
        self::MOTIF_DEBIT => 'Trop d’envois',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rétention limitée, comme le journal des connexions : `php artisan model:prune` (planifié chaque jour).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('security.login.retention_days')));
    }
}
