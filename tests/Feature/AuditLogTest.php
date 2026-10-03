<?php

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function creerService(User $auteur, array $attributs = []): Service
{
    $service = new Service(array_merge(['nom' => 'État civil', 'description' => 'Actes de naissance', 'horaires' => 'Lun-Ven 8h-16h'], $attributs));
    $service->user()->associate($auteur);
    $service->save();

    return $service;
}

test('créer, modifier et supprimer un élément audité crée une entrée avec auteur, action, élément et avant/après', function () {
    $agent = User::factory()->agent()->create(['name' => 'Marie Rakoto']);
    $this->actingAs($agent);

    $service = creerService($agent);
    $service->update(['horaires' => 'Lun-Sam 8h-12h']);
    $service->delete();

    $entrees = AuditLog::where('subject_type', 'Service')->orderBy('id')->get();

    expect($entrees->pluck('action')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($entrees->pluck('actor_id')->unique()->all())->toBe([$agent->id])
        ->and($entrees[0]->actor_name)->toBe('Marie Rakoto')
        ->and($entrees[0]->actor_role)->toBe('Agent municipal')
        ->and($entrees[0]->subject_id)->toBe($service->id)
        ->and($entrees[0]->subject_label)->toBe('Service : État civil')
        ->and($entrees[0]->changes['nom'])->toBe(['avant' => null, 'apres' => 'État civil'])
        ->and($entrees[1]->changes)->toBe(['horaires' => ['avant' => 'Lun-Ven 8h-16h', 'apres' => 'Lun-Sam 8h-12h']])
        ->and($entrees[2]->changes['nom'])->toBe(['avant' => 'État civil', 'apres' => null]);
});

test('un changement de rôle crée une entrée role_changed avec l\'ancien et le nouveau rôle', function () {
    $admin = User::factory()->admin()->create();
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($admin);
    $citoyen->changerRole(Role::where('code', Role::AGENT)->firstOrFail());

    $entree = AuditLog::where('action', 'role_changed')->sole();

    expect($entree->actor_id)->toBe($admin->id)
        ->and($entree->subject_id)->toBe($citoyen->id)
        ->and($entree->changes)->toBe(['role' => ['avant' => 'Citoyen', 'apres' => 'Agent municipal']])
        ->and(AuditLog::where('action', 'updated')->exists())->toBeFalse();
});

test('un changement de statut crée une entrée status_changed avec les libellés', function () {
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);
    $this->actingAs(User::factory()->agent()->create());

    $demarche->changerStatut('traitee');

    expect(AuditLog::where('action', 'status_changed')->sole()->changes)
        ->toBe(['statut' => ['avant' => 'Déposée', 'apres' => 'Traitée']]);
});

test('la désactivation d\'un compte par un agent (F34) apparaît dans le journal', function () {
    $agent = User::factory()->agent()->create();
    $citoyen = User::factory()->citoyen()->create(['name' => 'Jean Dupont']);

    Livewire::actingAs($agent)->test('pages::agent.citizens.show', ['user' => $citoyen])->call('deactivate');

    $entree = AuditLog::where('action', 'deactivated')->sole();
    expect($entree->actor_id)->toBe($agent->id)
        ->and($entree->phrase())->toContain('a désactivé le compte de Jean Dupont');

    $this->actingAs($agent)->get(route('agent.audit.index'))
        ->assertOk()
        ->assertSee('a désactivé le compte de Jean Dupont');
});

test('un mot de passe modifié apparaît masqué, jamais en clair ni haché', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->update(['password' => 'NouveauSecret!42']);

    $entree = AuditLog::where('subject_type', 'User')->where('action', 'updated')->sole();
    $brut = DB::table('audit_logs')->where('id', $entree->id)->value('changes');

    expect($entree->changes['password'])->toBe(['avant' => AuditLog::MASQUE, 'apres' => AuditLog::MASQUE])
        ->and($brut)->not->toContain('NouveauSecret')
        ->and($brut)->not->toContain('$2y$');
});

test('une opération dans une transaction annulée n\'est pas journalisée', function () {
    $agent = User::factory()->agent()->create();
    $this->actingAs($agent);

    try {
        DB::transaction(function () use ($agent): void {
            creerService($agent);

            throw new RuntimeException('annulation');
        });
    } catch (RuntimeException) {
    }

    expect(AuditLog::where('subject_type', 'Service')->exists())->toBeFalse();
});

test('une entrée du journal ne peut être ni modifiée ni supprimée, même sans événements ou en masse', function () {
    $entree = AuditLog::factory()->create(['actor_name' => 'Marie Rakoto']);

    expect(fn () => $entree->forceFill(['actor_name' => 'Pirate'])->save())->toThrow(LogicException::class)
        ->and(fn () => $entree->delete())->toThrow(LogicException::class)
        ->and(fn () => AuditLog::withoutEvents(fn () => $entree->forceFill(['actor_name' => 'Pirate'])->save()))->toThrow(LogicException::class)
        ->and(fn () => AuditLog::withoutEvents(fn () => $entree->delete()))->toThrow(LogicException::class)
        ->and(fn () => AuditLog::query()->update(['actor_name' => 'Pirate']))->toThrow(LogicException::class)
        ->and(fn () => AuditLog::query()->delete())->toThrow(LogicException::class)
        ->and($entree->fresh()->actor_name)->toBe('Marie Rakoto');
});

test('aucune route ne permet de modifier ou supprimer une entrée du journal', function (string $methode) {
    $entree = AuditLog::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->call($methode, '/agent/journal/'.$entree->id)
        ->assertMethodNotAllowed();

    expect($entree->fresh())->not->toBeNull();
})->with(['PUT', 'PATCH', 'DELETE', 'POST']);

test('les champs actor_id, created_at et changes envoyés dans une requête n\'influencent pas l\'entrée', function () {
    $agent = User::factory()->agent()->create();
    $pirate = User::factory()->admin()->create();
    $this->actingAs($agent);
    request()->merge(['actor_id' => $pirate->id, 'actor_name' => 'Faux', 'created_at' => '2000-01-01', 'changes' => ['x' => 'y']]);

    creerService($agent);

    $entree = AuditLog::where('subject_type', 'Service')->sole();
    expect($entree->actor_id)->toBe($agent->id)
        ->and($entree->actor_name)->toBe($agent->name)
        ->and($entree->created_at->isToday())->toBeTrue()
        ->and($entree->changes)->not->toHaveKey('x')
        ->and(fn () => (new AuditLog)->fill(['actor_id' => $pirate->id])->save())->toThrow(Exception::class);
});

test('withoutAuditing ne journalise rien', function () {
    $agent = User::factory()->agent()->create();

    AuditLogger::withoutAuditing(fn () => creerService($agent));

    expect(AuditLog::where('subject_type', 'Service')->exists())->toBeFalse();
});

test('un invité est redirigé vers la connexion', function () {
    $entree = AuditLog::factory()->create();

    $this->get(route('agent.audit.index'))->assertRedirect(route('login'));
    $this->get(route('agent.audit.show', $entree))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la liste et la fiche du journal', function () {
    $citoyen = User::factory()->citoyen()->create();
    $entree = AuditLog::factory()->create();

    $this->actingAs($citoyen)->get(route('agent.audit.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.audit.show', $entree))->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::agent.audit.index')->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::agent.audit.show', ['auditLog' => $entree])->assertForbidden();
});

test('un agent et un admin consultent la liste et la fiche, sans bouton de modification', function (string $role) {
    $entree = AuditLog::factory()->create([
        'subject_label' => 'Service : Voirie',
        'changes' => ['horaires' => ['avant' => '8h-16h', 'apres' => '8h-12h']],
    ]);
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)->get(route('agent.audit.index'))->assertOk()->assertSee('le service « Voirie »', false);
    $this->actingAs($user)->get(route('agent.audit.show', $entree))
        ->assertOk()
        ->assertSee(['Horaires', '8h-16h', '8h-12h', 'élément supprimé'])
        ->assertDontSee(['Modifier', 'Supprimer']);
})->with(['agent', 'admin']);

test('les filtres par type, auteur, élément et dates renvoient les bonnes entrées', function () {
    $agent = User::factory()->agent()->create();
    $marie = User::factory()->agent()->create(['name' => 'Marie Rakoto']);

    AuditLog::factory()->par($marie)->action('deleted')->create(['subject_label' => 'Service : Cible suppression', 'created_at' => now()->subDays(3)]);
    AuditLog::factory()->par($agent)->action('created')->create(['subject_label' => 'Service : Autre création', 'created_at' => now()->subDays(3)]);
    AuditLog::factory()->par($marie)->action('created')->create(['subject_type' => 'Annonce', 'subject_label' => 'Message général : Récent', 'created_at' => now()]);

    $page = Livewire::actingAs($agent)->test('pages::agent.audit.index');

    $page->set('action', 'deleted')->assertSee('Cible suppression')->assertDontSee(['Autre création', 'Récent']);
    $page->call('resetFilters')->set('auteur', (string) $marie->id)->assertSee(['Cible suppression', 'Récent'])->assertDontSee('Autre création');
    $page->call('resetFilters')->set('element', 'Annonce')->assertSee('Récent')->assertDontSee(['Cible suppression', 'Autre création']);
    $page->call('resetFilters')
        ->set('du', now()->subDays(4)->timezone(AuditLog::FUSEAU)->format('Y-m-d'))
        ->set('au', now()->subDays(2)->timezone(AuditLog::FUSEAU)->format('Y-m-d'))
        ->assertSee(['Cible suppression', 'Autre création'])->assertDontSee('Récent');

    $this->actingAs($agent)->get(route('agent.audit.index', ['action' => 'deleted']))
        ->assertSee('Cible suppression')->assertDontSee('Autre création');
});

test('l\'export CSV des résultats filtrés est lui-même journalisé', function () {
    $agent = User::factory()->agent()->create();
    AuditLog::factory()->action('deleted')->create(['subject_label' => 'Service : Exporté']);

    Livewire::actingAs($agent)->test('pages::agent.audit.index')
        ->set('action', 'deleted')
        ->call('export')
        ->assertFileDownloaded();

    expect(AuditLog::where('action', 'exported')->where('actor_id', $agent->id)->exists())->toBeTrue();
});
