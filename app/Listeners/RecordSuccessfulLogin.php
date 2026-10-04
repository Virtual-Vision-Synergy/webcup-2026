<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\LoginAttemptRecorder;
use Illuminate\Auth\Events\Login;

/**
 * F37 : journalise les connexions réussies (ligne légère, utile à l'agent pour repérer une connexion après des échecs).
 */
class RecordSuccessfulLogin
{
    public function __construct(private LoginAttemptRecorder $recorder) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $this->recorder->record(request(), $event->user->email, $event->user, successful: true);
    }
}
