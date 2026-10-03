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
