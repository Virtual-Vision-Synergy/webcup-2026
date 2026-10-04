<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * F85 : événement de sécurité (activité inhabituelle détectée).
 *
 * Aucun champ n'est remplissable : les lignes sont écrites uniquement par App\Services\SurveillanceSecurite
 * (affectation explicite côté serveur, jamais à partir des champs de la requête).
 *
 * @property int $id
 * @property int|null $user_id
 * @property-read User|null $user
 * @property string $type
 * @property string $niveau
 * @property string $description
 * @property array<string, mixed>|null $details
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
class SecurityEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public const TYPE_NOUVEL_APPAREIL = 'nouvel_appareil';

    public const TYPE_RAFALE = 'rafale_actions';

    public const TYPE_ACCES_REFUSES = 'acces_refuses';

    public const TYPE_MODIFICATIONS_MASSIVES = 'modifications_massives';

    public const TYPE_CONNEXION_BLOQUEE = 'connexion_bloquee';

    public const TYPE_COMPTE_VERROUILLE = 'compte_verrouille';

    public const TYPE_COMPTE_DEVERROUILLE = 'compte_deverrouille';

    public const TYPE_SESSIONS_FERMEES = 'sessions_fermees';

    /** @var array<string, string> */
    public const TYPE_OPTIONS = [
        self::TYPE_NOUVEL_APPAREIL => 'Nouvel appareil / nouvelle adresse',
        self::TYPE_RAFALE => 'Rafale d’actions',
        self::TYPE_ACCES_REFUSES => 'Accès refusés répétés',
        self::TYPE_MODIFICATIONS_MASSIVES => 'Modifications massives',
        self::TYPE_CONNEXION_BLOQUEE => 'Connexion bloquée (mots de passe)',
        self::TYPE_COMPTE_VERROUILLE => 'Compte verrouillé',
        self::TYPE_COMPTE_DEVERROUILLE => 'Compte déverrouillé',
        self::TYPE_SESSIONS_FERMEES => 'Sessions fermées',
    ];

    public const NIVEAU_INFO = 'info';

    public const NIVEAU_MOYEN = 'moyen';

    public const NIVEAU_ELEVE = 'eleve';

    /** @var array<string, string> */
    public const NIVEAU_OPTIONS = [
        self::NIVEAU_INFO => 'Information',
        self::NIVEAU_MOYEN => 'Moyen',
        self::NIVEAU_ELEVE => 'Élevé',
    ];

    /** Durée de conservation des événements (Prunable). */
    public const RETENTION_JOURS = 90;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libelleType(): string
    {
        return self::TYPE_OPTIONS[$this->type] ?? $this->type;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_JOURS));
    }
}
