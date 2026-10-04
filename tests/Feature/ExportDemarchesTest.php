<?php

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\ExportPreset;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\ExportDemarches;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * F88 : export personnalisé des données de suivi (CSV / JSON), préréglages et journalisation.
 */

beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 4, 10, 0));
});

/**
 * Contenu du fichier téléchargé par une action Livewire.
 */
function contenuTelecharge(Testable $component): string
{
    return (string) base64_decode((string) data_get($component->effects, 'download.content'));
}

/**
 * @return list<array<string, mixed>>
 */
function lignesJson(Testable $component): array
{
    return json_decode(contenuTelecharge($component), true, flags: JSON_THROW_ON_ERROR);
}

// --- Accès -----------------------------------------------------------------

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('agent.exports.index'))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la page d’export', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.exports.index'))->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::agent.exports')->assertForbidden();
});

test('un agent et un admin ouvrent la page d’export', function () {
    $this->actingAs(User::factory()->agent()->create())->get(route('agent.exports.index'))->assertOk()->assertSee('Export des données de suivi');
    $this->actingAs(User::factory()->admin()->create())->get(route('agent.exports.index'))->assertOk();
});

test('chaque action vérifie les droits : aperçu, téléchargement et préréglages refusés à un citoyen', function (string $action) {
    $agent = User::factory()->agent()->create();
    $preset = ExportPreset::factory()->for($agent)->create();

    $component = Livewire::actingAs($agent)->test('pages::agent.exports')->set('nomPrereglage', 'Rapport');

    // Le compte perd son rôle d'agent après l'ouverture de la page : les actions suivantes sont refusées.
    $agent->role_id = Role::idFor(Role::CITOYEN);
    $agent->save();

    $arguments = in_array($action, ['exporterPrereglage', 'chargerPrereglage', 'supprimerPrereglage'], true) ? [$preset->id] : [];
    $component->call($action, ...$arguments)->assertForbidden();

    expect(AuditLog::where('action', 'exported')->exists())->toBeFalse();
})->with(['previsualiser', 'telecharger', 'enregistrerPrereglage', 'exporterPrereglage', 'chargerPrereglage', 'supprimerPrereglage']);

// --- Périmètre et filtres -----------------------------------------------------

test('un agent n’exporte que les demandes de ses services', function () {
    $transports = Service::factory()->create(['nom' => 'Transports']);
    $etatCivil = Service::factory()->create(['nom' => 'État civil']);
    $visible = Demarche::factory()->for($transports)->create();
    $cachee = Demarche::factory()->for($etatCivil)->create();

    $component = Livewire::actingAs(User::factory()->agentDe($transports)->create())
        ->test('pages::agent.exports')
        ->set('format', 'json')
        ->set('colonnes', ['reference'])
        ->call('telecharger');

    expect(array_column(lignesJson($component), 'reference'))
        ->toBe([$visible->numeroSuivi()])
        ->not->toContain($cachee->numeroSuivi());
});

test('l’admin exporte les demandes de tous les services', function () {
    Demarche::factory()->count(3)->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('format', 'json')
        ->call('telecharger');

    expect(lignesJson($component))->toHaveCount(3);
});

test('les filtres période, service, statut et priorité sont appliqués', function () {
    $transports = Service::factory()->create();
    $voirie = Service::factory()->create();
    $attendue = Demarche::factory()->for($transports)->create(['statut' => 'en_cours', 'priorite' => 'haute', 'priorite_manuelle' => true, 'created_at' => now()->subDays(3)]);
    Demarche::factory()->for($transports)->create(['statut' => 'en_cours', 'priorite' => 'haute', 'priorite_manuelle' => true, 'created_at' => now()->subDays(20)]);
    Demarche::factory()->for($voirie)->create(['statut' => 'en_cours', 'priorite' => 'haute', 'priorite_manuelle' => true, 'created_at' => now()->subDays(3)]);
    Demarche::factory()->for($transports)->create(['statut' => 'traitee', 'priorite' => 'haute', 'priorite_manuelle' => true, 'created_at' => now()->subDays(3)]);
    Demarche::factory()->for($transports)->create(['statut' => 'en_cours', 'priorite' => 'basse', 'priorite_manuelle' => true, 'created_at' => now()->subDays(3)]);

    $component = Livewire::actingAs(User::factory()->agentDe($transports, $voirie)->create())
        ->test('pages::agent.exports')
        ->set('periode', '')
        ->set('du', now()->subDays(7)->format('Y-m-d'))
        ->set('au', now()->format('Y-m-d'))
        ->set('service', (string) $transports->id)
        ->set('statut', 'en_cours')
        ->set('priorite', 'haute')
        ->set('format', 'json')
        ->set('colonnes', ['reference'])
        ->call('telecharger')
        ->assertHasNoErrors();

    expect(array_column(lignesJson($component), 'reference'))->toBe([$attendue->numeroSuivi()]);
});

test('un service hors du périmètre de l’agent est refusé par la validation', function () {
    $sien = Service::factory()->create();
    $autre = Service::factory()->create();

    Livewire::actingAs(User::factory()->agentDe($sien)->create())
        ->test('pages::agent.exports')
        ->set('service', (string) $autre->id)
        ->call('telecharger')
        ->assertHasErrors('service');
});

// --- Colonnes -----------------------------------------------------------------

test('seules les colonnes choisies sont exportées, une colonne hors liste blanche est ignorée', function () {
    Demarche::factory()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('format', 'json')
        ->set('colonnes', ['reference', 'statut', 'password', 'user_id', 'email'])
        ->call('telecharger');

    expect(array_keys(lignesJson($component)[0]))->toBe(['reference', 'statut']);
});

test('aucune colonne valide : erreur de validation, pas de fichier', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('colonnes', ['email'])
        ->call('telecharger')
        ->assertHasErrors('colonnes')
        ->assertNoFileDownloaded();
});

test('les colonnes personnelles ne sont ni proposées ni cochées par défaut', function () {
    $demarche = Demarche::factory()->create();
    $demarche->user->forceFill(['name' => 'Rasoa Habitante', 'telephone' => '+261340000000'])->save();

    $component = Livewire::actingAs(User::factory()->admin()->create())->test('pages::agent.exports');

    expect($component->get('colonnes'))->toBe(ExportDemarches::COLONNES_PAR_DEFAUT)
        ->and(ExportDemarches::COLONNES_PAR_DEFAUT)->not->toContain('demandeur_anonyme')
        ->and(array_intersect(['email', 'telephone', 'nom', 'name', 'adresse', 'quartier', 'user_id'], array_keys(ExportDemarches::COLONNES)))->toBeEmpty();

    $contenu = contenuTelecharge($component->call('telecharger'));

    expect($contenu)->not->toContain('Rasoa Habitante')->not->toContain('+261340000000')->not->toContain($demarche->user->email);
});

test('l’identifiant anonymisé du demandeur est stable et ne révèle pas son identité', function () {
    $habitant = User::factory()->citoyen()->create();
    Demarche::factory()->count(2)->for($habitant)->create();
    Demarche::factory()->create();

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('format', 'json')
        ->set('colonnes', ['demandeur_anonyme'])
        ->call('telecharger');

    $identifiants = array_column(lignesJson($component), 'demandeur_anonyme');

    expect(array_count_values($identifiants)[ExportDemarches::identifiantAnonyme($habitant->id)])->toBe(2)
        ->and(implode(' ', $identifiants))->not->toContain((string) $habitant->email)->not->toContain($habitant->name);
});

// --- Formats ------------------------------------------------------------------

test('le CSV a un BOM UTF-8, le séparateur « ; », des en-têtes français et neutralise les formules', function () {
    Demarche::factory()->create(['titre' => '=CMD()', 'statut' => 'deposee']);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('colonnes', ['reference', 'titre', 'created_at', 'statut'])
        ->call('telecharger')
        ->assertFileDownloaded('export-demandes-2026-10-04.csv');

    $contenu = contenuTelecharge($component);
    $lignes = explode("\n", trim(substr($contenu, 3)));

    expect(substr($contenu, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($lignes[0])->toBe('Référence;"Objet de la demande";"Date de création";Statut')
        ->and($lignes[1])->toContain(";'=CMD();")
        ->and($lignes[1])->toContain('04/10/2026 13:00')
        ->and($lignes[1])->toEndWith(';Déposée');
});

test('le JSON est valide avec les clés des colonnes choisies', function () {
    $demarche = Demarche::factory()->create(['statut' => 'traitee', 'urgence_medicale' => false]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('format', 'json')
        ->set('colonnes', ['reference', 'statut', 'urgence_medicale', 'cloture_le'])
        ->call('telecharger')
        ->assertFileDownloaded('export-demandes-2026-10-04.json');

    expect(lignesJson($component))->toBe([[
        'reference' => $demarche->numeroSuivi(),
        'statut' => 'Traitée',
        'urgence_medicale' => false,
        'cloture_le' => '2026-10-04T13:00:00+03:00',
    ]]);
});

// --- Aperçu -------------------------------------------------------------------

test('l’aperçu donne le nombre exact de lignes et 5 lignes au plus, sans journaliser', function () {
    Demarche::factory()->count(7)->create(['statut' => 'deposee']);
    Demarche::factory()->count(2)->create(['statut' => 'traitee']);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->set('statut', 'deposee')
        ->call('previsualiser')
        ->assertNoFileDownloaded()
        ->assertSee('7 ligne(s) seront exportées');

    expect($component->get('apercu')['total'])->toBe(7)
        ->and($component->get('apercu')['lignes'])->toHaveCount(5)
        ->and(AuditLog::where('action', 'exported')->exists())->toBeFalse();
});

// --- Journalisation -------------------------------------------------------------

test('chaque téléchargement est journalisé : qui, format, filtres, colonnes, nombre de lignes', function () {
    Demarche::factory()->count(2)->create(['statut' => 'en_cours']);
    Demarche::factory()->create(['statut' => 'traitee']);
    $agent = agentDeTousLesServices();

    Livewire::actingAs($agent)
        ->test('pages::agent.exports')
        ->set('statut', 'en_cours')
        ->set('colonnes', ['reference', 'statut'])
        ->call('telecharger');

    $entree = AuditLog::where('action', 'exported')->sole();

    expect($entree->actor_id)->toBe($agent->id)
        ->and($entree->subject_type)->toBe('Export')
        ->and($entree->changes['format']['apres'])->toBe('CSV')
        ->and($entree->changes['filtres']['apres'])->toContain('statut En cours')
        ->and($entree->changes['colonnes']['apres'])->toBe('reference, statut')
        ->and($entree->changes['lignes']['apres'])->toBe(2)
        ->and($entree->changes['prereglage']['apres'])->toBe('aucun');
});

// --- Préréglages ----------------------------------------------------------------

test('un agent enregistre un préréglage ; user_id envoyé dans la requête est ignoré', function () {
    $agent = User::factory()->agent()->create();
    $autre = User::factory()->agent()->create();

    Livewire::actingAs($agent)
        ->test('pages::agent.exports')
        ->set('periode', '7j')
        ->set('statut', 'deposee')
        ->set('colonnes', ['reference', 'statut', 'inconnue'])
        ->set('format', 'json')
        ->set('nomPrereglage', 'Rapport hebdo transports')
        ->call('enregistrerPrereglage')
        ->assertHasNoErrors();

    $preset = ExportPreset::sole();

    expect($preset->user_id)->toBe($agent->id)
        ->and($preset->name)->toBe('Rapport hebdo transports')
        ->and($preset->columns)->toBe(['reference', 'statut'])
        ->and($preset->filters['periode'])->toBe('7j')
        ->and($preset->filters['statut'])->toBe('deposee')
        ->and($preset->format)->toBe('json');

    // Assignation de masse : seul le nom est remplissable.
    $force = new ExportPreset(['name' => 'Piège', 'user_id' => $autre->id, 'format' => 'xml', 'columns' => ['email']]);

    expect($force->user_id)->toBeNull()
        ->and($force->format)->toBeNull()
        ->and($force->columns)->toBeNull();
});

test('un préréglage s’exporte en un clic avec la période relative recalculée, et l’export le mentionne', function () {
    $recente = Demarche::factory()->create(['created_at' => now()->subDays(2)]);
    Demarche::factory()->create(['created_at' => now()->subDays(10)]);
    $agent = agentDeTousLesServices();
    $preset = ExportPreset::factory()->for($agent)->create([
        'name' => 'Rapport hebdo transports',
        'filters' => ['periode' => '7j', 'du' => '2020-01-01', 'au' => '2020-01-31', 'service' => '', 'statut' => '', 'priorite' => ''],
        'columns' => ['reference'],
        'format' => 'json',
    ]);

    $component = Livewire::actingAs($agent)->test('pages::agent.exports')->call('exporterPrereglage', $preset->id);

    expect(array_column(lignesJson($component), 'reference'))->toBe([$recente->numeroSuivi()])
        ->and(AuditLog::where('action', 'exported')->sole()->changes['prereglage']['apres'])->toBe('Rapport hebdo transports');
});

test('le préréglage d’un autre agent est refusé (403) et n’apparaît pas dans ma liste', function (string $action) {
    $proprietaire = User::factory()->agent()->create();
    $preset = ExportPreset::factory()->for($proprietaire)->create(['name' => 'Préréglage privé']);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::agent.exports')
        ->assertDontSee('Préréglage privé')
        ->call($action, $preset->id)
        ->assertForbidden();

    expect(ExportPreset::whereKey($preset->id)->exists())->toBeTrue();
})->with(['exporterPrereglage', 'chargerPrereglage', 'supprimerPrereglage']);

test('l’auteur supprime son préréglage', function () {
    $agent = User::factory()->agent()->create();
    $preset = ExportPreset::factory()->for($agent)->create();

    Livewire::actingAs($agent)->test('pages::agent.exports')->call('supprimerPrereglage', $preset->id)->assertHasNoErrors();

    expect(ExportPreset::count())->toBe(0);
});
