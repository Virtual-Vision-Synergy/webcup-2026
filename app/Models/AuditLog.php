<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * Journal d'audit des opérations sensibles (F47). IMMUABLE : aucune entrée ne peut être modifiée ni supprimée,
 * même par un administrateur ou en tinker (événements + surcharge des méthodes de mise à jour / suppression).
 *
 * Aucun champ n'est remplissable : les entrées sont écrites uniquement par App\Services\AuditLogger,
 * qui remplit tout côté serveur (jamais à partir de la requête).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $actor_name
 * @property string|null $actor_role
 * @property string $action
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string $subject_label
 * @property array<string, array{avant: mixed, apres: mixed}>|null $changes
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Valeur affichée à la place d'une donnée sensible (mot de passe, jeton, secret). */
    public const MASQUE = '[masqué]';

    /** Les dates sont stockées en UTC et affichées à l'heure de Madagascar. */
    public const FUSEAU = Annonce::FUSEAU;

    /** @var array<string, string> */
    public const ACTION_LABELS = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'status_changed' => 'Changement de statut',
        'role_changed' => 'Changement de rôle',
        'deactivated' => 'Compte désactivé',
        'reactivated' => 'Compte réactivé',
        'exported' => 'Export',
    ];

    /** @var array<string, string> */
    public const ACTION_VERBES = [
        'created' => 'a créé',
        'updated' => 'a modifié',
        'deleted' => 'a supprimé',
        'status_changed' => 'a changé le statut de',
        'role_changed' => 'a changé le rôle de',
        'deactivated' => 'a désactivé',
        'reactivated' => 'a réactivé',
        'exported' => 'a exporté',
    ];

    /** État du badge (couleur + texte, voir <x-tn.status-badge>). */
    public const ACTION_ETATS = [
        'created' => 'normal',
        'updated' => 'info',
        'deleted' => 'alerte',
        'status_changed' => 'perturbe',
        'role_changed' => 'perturbe',
        'deactivated' => 'alerte',
        'reactivated' => 'normal',
        'exported' => 'info',
    ];

    /**
     * Types d'éléments journalisés (liste blanche) : classe, libellé, tournure de phrase, page de détail et droit requis pour le lien.
     *
     * @var array<string, array{classe: class-string<Model>, libelle: string, article: string, route: string|null, droit: string}>
     */
    public const SUBJECTS = [
        'User' => ['classe' => User::class, 'libelle' => 'Compte', 'article' => 'le compte de', 'route' => 'agent.citizens.show', 'droit' => 'viewAccount'],
        'Service' => ['classe' => Service::class, 'libelle' => 'Service', 'article' => 'le service', 'route' => 'services.show', 'droit' => 'view'],
        'Annonce' => ['classe' => Annonce::class, 'libelle' => 'Message général', 'article' => 'le message général', 'route' => 'agent.annonces.edit', 'droit' => 'update'],
        'Role' => ['classe' => Role::class, 'libelle' => 'Rôle', 'article' => 'le rôle', 'route' => 'roles.show', 'droit' => 'view'],
        'Actualite' => ['classe' => Actualite::class, 'libelle' => 'Actualité', 'article' => 'l’actualité', 'route' => 'actualites.show', 'droit' => 'view'],
        'Demarche' => ['classe' => Demarche::class, 'libelle' => 'Démarche', 'article' => 'la démarche', 'route' => 'demarches.show', 'droit' => 'view'],
        'Signalement' => ['classe' => Signalement::class, 'libelle' => 'Signalement', 'article' => 'le signalement', 'route' => 'signalements.show', 'droit' => 'view'],
        'Message' => ['classe' => Message::class, 'libelle' => 'Message', 'article' => 'le message', 'route' => 'messages.show', 'droit' => 'view'],
        'AuditLog' => ['classe' => AuditLog::class, 'libelle' => 'Journal', 'article' => 'le journal', 'route' => null, 'droit' => 'viewAny'],
    ];

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'name' => 'Nom',
        'nom' => 'Nom',
        'email' => 'E-mail',
        'password' => 'Mot de passe',
        'telephone' => 'Téléphone',
        'quartier' => 'Quartier',
        'role' => 'Rôle',
        'role_id' => 'Rôle',
        'deactivated_at' => 'Désactivé le',
        'two_factor_secret' => 'Double authentification (secret)',
        'two_factor_recovery_codes' => 'Codes de secours',
        'two_factor_confirmed_at' => 'Double authentification confirmée le',
        'description' => 'Description',
        'horaires' => 'Horaires',
        'adresse' => 'Adresse',
        'slug' => 'Adresse web',
        'titre' => 'Titre',
        'contenu' => 'Contenu',
        'niveau' => 'Niveau',
        'debut' => 'Début de diffusion',
        'fin' => 'Fin de diffusion',
        'code' => 'Code',
        'label' => 'Libellé',
        'date' => 'Date',
        'image' => 'Image',
        'photo' => 'Photo',
        'statut' => 'Statut',
        'service_id' => 'Service (n°)',
        'user_id' => 'Auteur (n°)',
        'categorie' => 'Catégorie',
        'lieu' => 'Lieu',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'sujet' => 'Sujet',
        'message' => 'Message',
        'filtres' => 'Filtres appliqués',
        'lignes' => 'Nombre de lignes',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Une entrée du journal d’audit ne peut pas être modifiée.'));
        static::deleting(fn (): never => throw new LogicException('Une entrée du journal d’audit ne peut pas être supprimée.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Garde-fou indépendant des événements (Model::withoutEvents, tinker…).
     *
     * @param  Builder<static>  $query
     */
    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Une entrée du journal d’audit ne peut pas être modifiée.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Une entrée du journal d’audit ne peut pas être supprimée.');
    }

    /**
     * Bloque aussi les mises à jour / suppressions de masse : AuditLog::query()->update([...]) ou ->delete().
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): AuditLogBuilder
    {
        return new AuditLogBuilder($query);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Nom lisible quand le journal lui-même est l'élément concerné (export).
     */
    public function auditLabel(): string
    {
        return 'Journal d’audit';
    }

    public function libelleAction(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    public function etatAction(): string
    {
        return self::ACTION_ETATS[$this->action] ?? 'info';
    }

    public static function libelleChamp(string $champ): string
    {
        return self::FIELD_LABELS[$champ] ?? ucfirst(str_replace('_', ' ', $champ));
    }

    /**
     * Valeur affichable d'un champ avant / après.
     */
    public static function formatValeur(mixed $valeur): string
    {
        return match (true) {
            $valeur === null, $valeur === '' => '—',
            is_bool($valeur) => $valeur ? 'Oui' : 'Non',
            is_scalar($valeur) => (string) $valeur,
            default => (string) json_encode($valeur, JSON_UNESCAPED_UNICODE),
        };
    }

    public static function libelleType(string $type): string
    {
        return self::SUBJECTS[$type]['libelle'] ?? $type;
    }

    /**
     * Nom lisible de l'élément (la partie après « Type : »).
     */
    public function nomElement(): string
    {
        return Str::contains($this->subject_label, ' : ') ? Str::after($this->subject_label, ' : ') : $this->subject_label;
    }

    public function auteur(): string
    {
        return $this->actor_role ? $this->actor_name.' ('.Str::lower($this->actor_role).')' : $this->actor_name;
    }

    /**
     * Phrase lisible : « Marie Rakoto (agent municipal) a désactivé le compte de Jean Dupont ».
     */
    public function phrase(): string
    {
        $verbe = self::ACTION_VERBES[$this->action] ?? $this->action;

        if ($this->subject_type === 'AuditLog') {
            return $this->auteur().' '.$verbe.' le journal d’audit';
        }
        $article = self::SUBJECTS[$this->subject_type]['article'] ?? Str::lower($this->subject_type);
        $nom = $this->subject_type === 'User' ? $this->nomElement() : '« '.$this->nomElement().' »';

        return $this->auteur().' '.$verbe.' '.$article.' '.$nom;
    }

    public function dateLocale(string $format = 'd/m/Y H:i'): string
    {
        return $this->created_at?->timezone(self::FUSEAU)->format($format) ?? '—';
    }

    /**
     * L'élément concerné s'il existe encore (null s'il a été supprimé ou si le type est inconnu).
     */
    public function subject(): ?Model
    {
        $classe = self::SUBJECTS[$this->subject_type]['classe'] ?? null;

        if ($classe === null || $this->subject_id === null || $classe === self::class) {
            return null;
        }

        return $classe::query()->find($this->subject_id);
    }
}
