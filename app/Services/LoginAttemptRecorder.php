<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * F37 : seul point d'écriture du journal des tentatives de connexion.
 * Affectation explicite champ par champ (aucune assignation de masse) ; le mot de passe n'est jamais lu ici.
 */
class LoginAttemptRecorder
{
    public function record(Request $request, string $email, ?User $user, bool $successful, ?string $reason = null): void
    {
        try {
            $attempt = new LoginAttempt;
            $attempt->email = Str::limit(Str::lower(trim($email)), 255, '');
            $attempt->user_id = $user->id ?? User::where('email', Str::lower(trim($email)))->value('id');
            $attempt->ip = $request->ip();
            $attempt->user_agent = Str::limit((string) $request->userAgent(), 255, '') ?: null;
            $attempt->successful = $successful;
            $attempt->reason = $reason;
            $attempt->save();
        } catch (Throwable $e) {
            // Le journal ne doit jamais empêcher (ni débloquer) une connexion.
            report($e);
        }
    }
}
