<?php

use App\Models\LoginAttempt;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F37 : purge du journal des tentatives de connexion (rétention dans config/security.php).
Schedule::command('model:prune', ['--model' => [LoginAttempt::class]])->daily();

// F39 : prolonge chaque nuit l'agenda des créneaux de rendez-vous (idempotent).
Schedule::command('appointments:generate-slots')->dailyAt('01:00');

// F40 : rappel des rendez-vous confirmés qui commencent dans moins de 24 h (idempotent).
Schedule::command('appointments:send-reminders')->everyFiveMinutes()->withoutOverlapping();

// F30 : notifie les habitants des annonces importantes programmées, au moment où elles commencent (idempotent).
Schedule::command('annonces:notify')->everyMinute()->withoutOverlapping();
