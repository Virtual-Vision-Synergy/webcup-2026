<?php

namespace App\Listeners;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Notifications\TentativesConnexionSuspectes;
use App\Services\LoginAttemptRecorder;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Throwable;

/**
 * F37 : journalise chaque tentative refusée pour blocage et prévient le titulaire du compte (s'il existe),
 * au plus une fois par heure.
 */
class RecordLockout
{
    public function __construct(private LoginAttemptRecorder $recorder) {}

    public function handle(Lockout $event): void
    {
        $email = Str::lower(trim((string) $event->request->input(Fortify::username())));
        $user = $email === '' ? null : User::where('email', $email)->first();

        $this->recorder->record($event->request, $email, $user, successful: false, reason: LoginAttempt::REASON_LOCKED_OUT);

        if ($user === null) {
            return;
        }

        $ttl = now()->addMinutes((int) config('security.login.notify_every_minutes'));

        if (! Cache::add('login-alert:'.$user->id, true, $ttl)) {
            return;
        }

        try {
            $user->notify(new TentativesConnexionSuspectes(now(), (string) $event->request->ip()));
        } catch (Throwable $e) {
            // Un échec d'envoi d'e-mail ne doit pas casser la réponse de blocage.
            report($e);
        }
    }
}
