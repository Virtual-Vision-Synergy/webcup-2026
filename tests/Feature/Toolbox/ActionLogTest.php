<?php

use App\Filament\Resources\ActionLogs\ActionLogResource;
use App\Filament\Resources\ActionLogs\Pages\ListActionLogs;
use App\Models\ActionLog;
use App\Models\User;
use Livewire\Livewire;

test('record() enregistre l\'utilisateur, l\'objet et l\'adresse IP', function () {
    $user = User::factory()->create();
    $cible = User::factory()->create();

    $this->actingAs($user);
    ActionLog::record('deleted', $cible);

    $log = ActionLog::firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('deleted')
        ->and($log->subject_type)->toBe('User')
        ->and($log->subject_id)->toBe($cible->id)
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->created_at)->not->toBeNull();
});

test('record() fonctionne sans utilisateur connecté ni objet', function () {
    ActionLog::record('login');

    $log = ActionLog::firstOrFail();

    expect($log->user_id)->toBeNull()
        ->and($log->subject_type)->toBeNull()
        ->and($log->subject_id)->toBeNull();
});

test('user_id ne peut pas être assigné en masse', function () {
    $pirate = User::factory()->create();

    $log = new ActionLog(['action' => 'created', 'user_id' => $pirate->id]);

    expect($log->user_id)->toBeNull();
});

test('libelle() donne le libellé français, ou l\'action brute si elle est inconnue', function () {
    expect(ActionLog::factory()->make(['action' => 'deleted'])->libelle())->toBe('Suppression')
        ->and(ActionLog::factory()->make(['action' => 'login'])->libelle())->toBe('Connexion')
        ->and(ActionLog::factory()->make(['action' => 'inconnue'])->libelle())->toBe('inconnue');
});

test('un utilisateur normal ne peut pas voir le journal', function () {
    $this->actingAs(User::factory()->create())->get('/admin/action-logs')->assertForbidden();
});

test('un admin voit le journal avec les libellés français', function () {
    $admin = User::factory()->admin()->create();
    $logs = ActionLog::factory()->count(3)->create(['action' => 'deleted']);

    $this->actingAs($admin)->get('/admin/action-logs')->assertOk();

    Livewire::test(ListActionLogs::class)
        ->assertCanSeeTableRecords($logs)
        ->assertSee('Suppression');
});

test('le journal est en lecture seule', function () {
    $this->actingAs(User::factory()->admin()->create());
    $log = ActionLog::factory()->create();

    expect(ActionLogResource::canCreate())->toBeFalse()
        ->and(ActionLogResource::canEdit($log))->toBeFalse()
        ->and(ActionLogResource::canDelete($log))->toBeFalse()
        ->and(ActionLogResource::canDeleteAny())->toBeFalse();

    $this->get('/admin/action-logs/create')->assertNotFound();
    $this->get("/admin/action-logs/{$log->id}/edit")->assertNotFound();
});

test('supprimer un utilisateur conserve ses entrées du journal', function () {
    $user = User::factory()->create();
    $log = ActionLog::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($log->fresh()->user_id)->toBeNull();
});
