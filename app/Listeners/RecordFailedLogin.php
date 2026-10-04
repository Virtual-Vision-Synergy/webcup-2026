<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\LoginAttemptRecorder;
use Illuminate\Auth\Events\Failed;

/**
 * F37 : journalise chaque échec de connexion (identifiants incorrects). Le mot de passe éventuel est ignoré.
 */
class RecordFailedLogin
{
    public function __construct(private LoginAttemptRecorder $recorder) {}

    public function handle(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $this->recorder->record(
            request(),
            (string) ($event->credentials['email'] ?? ''),
            $event->user instanceof User ? $event->user : null,
            successful: false,
            reason: LoginAttempt::REASON_BAD_CREDENTIALS,
        );
    }
}
