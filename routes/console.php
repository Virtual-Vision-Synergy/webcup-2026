<?php

use App\Models\FormSubmission;
use App\Models\KnownDevice;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\TentativeBloquee;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// F37 : purge du journal des tentatives de connexion (rétention dans config/security.php).
// F54 : purge des appareils inactifs depuis plus de 6 mois.
// F81 : purge du journal des envois de formulaires bloqués (même rétention que F37).
// F85 : purge des événements de sécurité de plus de 90 jours.
// F82 : purge des envois de formulaires mémorisés pour les doublons (plus d'un jour).
Schedule::command('model:prune', ['--model' => [LoginAttempt::class, KnownDevice::class, TentativeBloquee::class, SecurityEvent::class, FormSubmission::class]])->daily();

// F39 : prolonge chaque nuit l'agenda des créneaux de rendez-vous (idempotent).
Schedule::command('appointments:generate-slots')->dailyAt('01:00');

// F40 : rappel des rendez-vous confirmés qui commencent dans moins de 24 h (idempotent).
Schedule::command('appointments:send-reminders')->everyFiveMinutes()->withoutOverlapping();

// F30 : notifie les habitants des annonces importantes programmées, au moment où elles commencent (idempotent).
Schedule::command('annonces:notify')->everyMinute()->withoutOverlapping();

// F87 : vérifie la dernière sauvegarde de la base et alerte les admins si elle manque ou échoue.
Schedule::command('sauvegardes:surveiller')->everyFifteenMinutes()->withoutOverlapping();

// F85 : contrôle d'intégrité des données (anomalies listées aux admins dans Filament → Sécurité).
Schedule::command('securite:controle-integrite')->hourly()->withoutOverlapping();

// F80 : recalcule chaque heure la priorité suggérée des demandes ouvertes (ancienneté, relances ; jamais une priorité fixée par un agent).
Schedule::command('demarches:prioriser')->hourly()->withoutOverlapping();

// F31 : alertes canicule programmées, notifiées aux habitants des quartiers touchés à leur début (idempotent).
Schedule::command('canicule:notify')->everyMinute()->withoutOverlapping();

// F97 : interruptions de lignes de transport programmées, notifiées aux habitants abonnés à leur début (idempotent).
Schedule::command('transports:notify')->everyMinute()->withoutOverlapping();
