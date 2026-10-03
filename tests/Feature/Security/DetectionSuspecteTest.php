<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\Avis;
use App\Services\SecurityMonitor;
use Illuminate\Support\Facades\Notification;

/*
| F69 : protection perceptible. Les refus sont journalisés (F47) ; au-delà du seuil, blocage temporaire (429)
| et une seule alerte aux administrateurs. L'usage normal n'est jamais gêné par un refus isolé.
*/

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

function evenementsSecurite(string $action): int
{
    return AuditLog::query()->where('subject_type', AuditLog::SUJET_SECURITE)->where('action', $action)->count();
}

test('un accès interdit isolé est journalisé mais ne bloque pas l’habitant', function () {
    $habitant = User::factory()->citoyen()->create();

    $this->actingAs($habitant)->get(route('agent.tableau-de-bord'))->assertForbidden();
    $this->actingAs($habitant)->get(route('dashboard'))->assertOk();

    $entree = AuditLog::query()->where('action', SecurityMonitor::ACCES_REFUSE)->sole();
    expect($entree->actor_id)->toBe($habitant->id)
        ->and($entree->subject_label)->toContain('agent.tableau-de-bord')
        ->and($entree->ip)->toEndWith('.x');
    Notification::assertNothingSent();
});

test('au-delà du seuil, l’habitant est bloqué (429) et l’admin est prévenu une seule fois', function () {
    $habitant = User::factory()->citoyen()->create();
    $seuil = (int) config('security.suspect.user_max');

    for ($i = 0; $i < $seuil + 3; $i++) {
        $this->actingAs($habitant)->get(route('agent.tableau-de-bord'));
    }

    $this->actingAs($habitant)->get(route('dashboard'))
        ->assertStatus(429)
        ->assertSee('Activité inhabituelle détectée');

    expect(evenementsSecurite(SecurityMonitor::ACCES_REFUSE))->toBe($seuil)
        ->and(evenementsSecurite(SecurityMonitor::BLOCAGE))->toBe(1);
    Notification::assertSentToTimes($this->admin, Avis::class, 1);
});

test('le blocage expire de lui-même : l’usage normal reprend', function () {
    $habitant = User::factory()->citoyen()->create();

    for ($i = 0; $i < (int) config('security.suspect.user_max'); $i++) {
        $this->actingAs($habitant)->get(route('agent.tableau-de-bord'));
    }
    $this->actingAs($habitant)->get(route('dashboard'))->assertStatus(429);

    $this->travel((int) config('security.suspect.block_minutes') + 1)->minutes();

    $this->actingAs($habitant)->get(route('dashboard'))->assertOk();
});

test('un administrateur n’est jamais bloqué', function () {
    for ($i = 0; $i < (int) config('security.suspect.user_max') + 2; $i++) {
        SecurityMonitor::signaler(SecurityMonitor::ACCES_REFUSE, request()->setUserResolver(fn () => $this->admin));
    }

    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
});

test('un paramètre de traversée de répertoire est journalisé sans bloquer la page', function () {
    $habitant = User::factory()->citoyen()->create();

    $this->actingAs($habitant)->get(route('dashboard', ['fichier' => '../../.env']))->assertOk();

    expect(evenementsSecurite(SecurityMonitor::MOTIF))->toBe(1);
});

test('un formulaire sans jeton CSRF (419) est compté comme événement de sécurité', function () {
    $this->app['env'] = 'local';

    $this->actingAs(User::factory()->create())->post(route('notifications.read-all'))->assertStatus(419);

    expect(evenementsSecurite(SecurityMonitor::CSRF))->toBe(1);
});

test('le journal ne contient jamais le contenu de la requête', function () {
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('agent.tableau-de-bord', ['motdepasse' => 'Secret-123']));

    expect(json_encode(AuditLog::query()->where('subject_type', AuditLog::SUJET_SECURITE)->get()->toArray()))
        ->not->toContain('Secret-123');
});

test('les événements de sécurité sont cachés aux agents dans le journal d’audit', function () {
    $this->actingAs(User::factory()->citoyen()->create())->get(route('agent.tableau-de-bord'));
    $entree = AuditLog::query()->where('subject_type', AuditLog::SUJET_SECURITE)->sole();
    $agent = User::factory()->agent()->create();

    expect($agent->can('view', $entree))->toBeFalse()
        ->and($this->admin->can('view', $entree))->toBeTrue();
    $this->actingAs($agent)->get(route('agent.audit.show', $entree))->assertForbidden();
});
