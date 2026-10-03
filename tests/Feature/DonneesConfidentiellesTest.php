<?php

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\Service;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| F70 : données confidentielles masquées par défaut (jamais dans le HTML), affichées sur « Afficher » avec un motif,
| chaque consultation journalisée sans la valeur.
*/

beforeEach(function () {
    $this->service = Service::factory()->create(['nom' => 'Action sociale']);
    $this->agent = User::factory()->agentDe($this->service)->create();

    $this->habitant = User::factory()->citoyen()->create([
        'email' => 'lalao.ravelo@example.com',
        'telephone' => '0347766554',
    ]);
    $this->demarche = Demarche::factory()->for($this->service)->for($this->habitant)->create();
});

function revelation(User $acteur, Demarche $demarche, string $champ = 'telephone_demandeur'): Testable
{
    return Livewire::actingAs($acteur)->test('donnee-confidentielle', ['subject' => $demarche, 'champ' => $champ]);
}

test('la fiche d’une démarche ne contient pas les coordonnées du demandeur', function () {
    $this->actingAs($this->agent)
        ->get(route('demarches.show', $this->demarche))
        ->assertOk()
        ->assertSee('(confidentiel)')
        ->assertDontSee('0347766554')
        ->assertDontSee('lalao.ravelo@example.com');
});

test('« Afficher » sans motif est refusé et rien n’est révélé ni journalisé', function () {
    revelation($this->agent, $this->demarche)
        ->call('reveler')
        ->assertHasErrors(['motif' => 'required'])
        ->assertDontSee('0347766554');

    expect(AuditLog::query()->where('action', 'confidential_viewed')->exists())->toBeFalse();
});

test('le motif « Autre » exige une précision', function () {
    revelation($this->agent, $this->demarche)
        ->set('motif', 'autre')
        ->call('reveler')
        ->assertHasErrors(['motifAutre' => 'required_if']);
});

test('« Afficher » avec un motif révèle la valeur et journalise la consultation sans la valeur', function () {
    revelation($this->agent, $this->demarche)
        ->set('motif', 'contact')
        ->call('reveler')
        ->assertHasNoErrors()
        ->assertSee('0347766554');

    $consultation = AuditLog::query()->where('action', 'confidential_viewed')->sole();

    expect($consultation->actor_id)->toBe($this->agent->id)
        ->and($consultation->subject_type)->toBe('Demarche')
        ->and($consultation->subject_id)->toBe($this->demarche->id)
        ->and($consultation->changes['champ']['apres'])->toBe('Téléphone du demandeur')
        ->and($consultation->changes['motif']['apres'])->toBe('Contact du citoyen')
        ->and($consultation->created_at)->not->toBeNull()
        ->and(json_encode($consultation->getAttributes()))->not->toContain('0347766554');
});

test('pendant 5 minutes, le même dossier se ré-affiche sans motif mais reste journalisé', function () {
    revelation($this->agent, $this->demarche)->set('motif', 'traitement')->call('reveler');

    revelation($this->agent, $this->demarche, 'email_demandeur')
        ->call('reveler')
        ->assertHasNoErrors()
        ->assertSee('lalao.ravelo@example.com');

    expect(AuditLog::query()->where('action', 'confidential_viewed')->count())->toBe(2);

    $this->travel(6)->minutes();

    revelation($this->agent, $this->demarche)->call('reveler')->assertHasErrors(['motif']);
});

test('un agent d’un autre service reçoit un 403 journalisé sur « Afficher »', function () {
    $autreAgent = User::factory()->agentDe(Service::factory()->create())->create();

    revelation($autreAgent, $this->demarche)
        ->set('motif', 'contact')
        ->call('reveler')
        ->assertForbidden();

    expect(AuditLog::query()->where('action', 'confidential_viewed')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'access_denied')->where('actor_id', $autreAgent->id)->where('subject_id', $this->demarche->id)->exists())->toBeTrue();
});

test('un champ hors de la liste blanche donne 404', function () {
    revelation($this->agent, $this->demarche, 'password')->assertNotFound();
});

test('seul l’admin voit le bloc des consultations sur la fiche', function () {
    revelation($this->agent, $this->demarche)->set('motif', 'verification')->call('reveler');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('demarches.show', $this->demarche))
        ->assertSee('Consultations des données confidentielles')
        ->assertSee('Vérification de pièce');

    $this->actingAs($this->agent)
        ->get(route('demarches.show', $this->demarche))
        ->assertDontSee('Consultations des données confidentielles');
});

test('le téléphone d’un habitant est masqué sur sa fiche agent et dans le journal', function () {
    $this->habitant->update(['telephone' => '0329988776']);

    $this->actingAs($this->agent)
        ->get(route('agent.citizens.show', $this->habitant))
        ->assertOk()
        ->assertDontSee('0329988776')
        ->assertDontSee('0347766554');

    $entree = AuditLog::query()->where('subject_type', 'User')->where('subject_id', $this->habitant->id)->where('action', 'updated')->sole();

    $this->actingAs($this->agent)
        ->get(route('agent.audit.show', $entree))
        ->assertOk()
        ->assertSee(AuditLog::MASQUE)
        ->assertDontSee('0329988776');
});
