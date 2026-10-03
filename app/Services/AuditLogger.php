<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    /**
     * @param  array<string, array{avant: mixed, apres: mixed}>  $changes
     */
    public static function log(string $action, Model $subject, array $changes = []): void
    {
        if (! self::$enabled) {
            return;
        }

        try {
            $entry = self::buildEntry($action, $subject, $changes);
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
        });
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
