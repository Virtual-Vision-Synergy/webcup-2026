<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Seul point d'écriture du journal d'audit (F47).
 *
 * Tout est rempli côté serveur : auteur (utilisateur connecté, instantané nom + rôle), IP, navigateur, date.
 * Rien ne vient des champs de la requête. L'écriture a lieu après le commit de la transaction en cours :
 * une opération annulée n'est jamais journalisée. Une erreur d'écriture ne casse jamais l'action métier.
 */
class AuditLogger
{
    /** Clés dont la valeur n'est jamais enregistrée en clair (comparaison sur le nom de champ, en minuscules). */
    private const SENSITIVE = ['password', 'remember_token', 'token', 'secret', 'api_key', 'apikey', 'recovery_codes'];

    /** Longueur maximale d'une valeur enregistrée (les longs textes sont tronqués). */
    private const MAX_VALUE_LENGTH = 500;

    private static bool $enabled = true;

    /** F85 : actions comptées pour repérer les modifications massives. */
    private const ACTIONS_MODIFICATION = ['created', 'updated', 'deleted', 'status_changed'];

    /** Attribut de requête : le refus de cette requête est déjà au journal (F70). */
    public const REFUS_JOURNALISE = 'audit.refus_journalise';

    /**
     * @param  array<string, array{avant: mixed, apres: mixed}>  $changes
     */
    public static function log(string $action, Model $subject, array $changes = [], ?string $subjectType = null, ?string $subjectLabel = null): void
    {
        if (! self::$enabled) {
            return;
        }

        try {
            $entry = self::buildEntry($action, $subject, $changes);

            if ($subjectType !== null) {
                $entry['subject_type'] = $subjectType;
                $entry['subject_id'] = null;
            }

            if ($subjectLabel !== null) {
                $entry['subject_label'] = $subjectLabel;
            }
        } catch (Throwable $e) {
            report($e);

            return;
        }

        DB::afterCommit(function () use ($entry): void {
            try {
                (new AuditLog)->forceFill($entry)->save();
            } catch (Throwable $e) {
                report($e);
            }

            // F85 : modifications massives d'un même compte → événement de sécurité.
            $acteur = auth()->user();
            if ($acteur instanceof User && in_array($entry['action'], self::ACTIONS_MODIFICATION, true)) {
                app(SurveillanceSecurite::class)->compterModification($acteur);
            }
        });
    }

    /**
     * F70 : vérifie un droit sur un élément ; en cas de refus, journalise la tentative (avec l'élément) puis lève le 403
     * dont le message vient de la policy (Response::deny). À utiliser dans les actions sensibles à la place de authorize().
     *
     * @throws AuthorizationException
     */
    public static function autoriser(string $ability, Model $subject): void
    {
        $reponse = Gate::inspect($ability, $subject);

        if ($reponse->denied()) {
            self::logRefus((string) $reponse->message(), $subject);
            $reponse->authorize();
        }
    }

    /**
     * F70 : journalise un accès refusé (403) : qui, quand, quelle page, quel élément, motif, IP.
     * Une seule entrée par requête (le gestionnaire d'exceptions ne double pas une entrée déjà écrite ici).
     */
    public static function logRefus(string $motif, ?Model $subject = null, ?Request $request = null): void
    {
        $request ??= request();

        if ($request->attributes->get(self::REFUS_JOURNALISE) === true) {
            return;
        }

        $request->attributes->set(self::REFUS_JOURNALISE, true);

        // F85 : accès refusés répétés → événement de sécurité.
        $utilisateur = $request->user();
        if ($utilisateur instanceof User) {
            app(SurveillanceSecurite::class)->compterRefus($utilisateur);
        }

        // Pour une action Livewire, la page d'origine est plus parlante que /livewire/update.
        $page = $request->is('livewire*/update') ? (string) $request->headers->get('referer', $request->fullUrl()) : $request->fullUrl();
        $chemin = '/'.ltrim((string) parse_url($page, PHP_URL_PATH), '/');

        $changes = [
            'route' => ['avant' => null, 'apres' => $request->route()?->getName() ?? $chemin],
            'url' => ['avant' => null, 'apres' => $chemin],
            'motif' => ['avant' => null, 'apres' => $motif !== '' ? $motif : 'Droits insuffisants'],
        ];

        if ($subject !== null) {
            self::log('access_denied', $subject, $changes);

            return;
        }

        $sujet = new AuditLog;
        self::log('access_denied', $sujet, $changes, AuditLog::SUJET_ACCES, Str::limit('Page : '.$chemin, 250));
    }

    /**
     * Exécute $callback sans journaliser (outils internes, imports). Les seeders passent déjà par WithoutModelEvents.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /**
     * Libellé lisible d'un élément : « Service : État civil ».
     */
    public static function labelFor(Model $subject): string
    {
        $type = class_basename($subject);
        $nom = method_exists($subject, 'auditLabel')
            ? $subject->auditLabel()
            : ($subject->getAttribute('name') ?? $subject->getAttribute('nom') ?? $subject->getAttribute('titre') ?? $subject->getAttribute('label') ?? '#'.$subject->getKey());

        return Str::limit(AuditLog::libelleType($type).' : '.$nom, 250);
    }

    /**
     * Remplace les valeurs sensibles par « [masqué] » et tronque les valeurs longues.
     *
     * @param  array<string, array{avant: mixed, apres: mixed}>  $changes
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public static function sanitize(array $changes): array
    {
        $propres = [];

        foreach ($changes as $champ => $valeurs) {
            $sensible = Str::contains(Str::lower((string) $champ), self::SENSITIVE);

            $propres[$champ] = [
                'avant' => $sensible && $valeurs['avant'] !== null ? AuditLog::MASQUE : self::normalize($valeurs['avant']),
                'apres' => $sensible && $valeurs['apres'] !== null ? AuditLog::MASQUE : self::normalize($valeurs['apres']),
            ];
        }

        return $propres;
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return is_string($value) ? Str::limit($value, self::MAX_VALUE_LENGTH) : $value;
    }

    /**
     * @param  array<string, array{avant: mixed, apres: mixed}>  $changes
     * @return array<string, mixed>
     */
    private static function buildEntry(string $action, Model $subject, array $changes): array
    {
        $actor = auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return [
            'actor_id' => $actor instanceof User ? $actor->id : null,
            'actor_name' => $actor instanceof User ? Str::limit($actor->name, 250) : 'Système',
            'actor_role' => $actor instanceof User ? Role::query()->whereKey($actor->role_id)->value('label') : null,
            'action' => Str::limit($action, 40, ''),
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'subject_label' => self::labelFor($subject),
            'changes' => $changes === [] ? null : self::sanitize($changes),
            'ip' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') ?: null : null,
            'created_at' => now(),
        ];
    }
}
