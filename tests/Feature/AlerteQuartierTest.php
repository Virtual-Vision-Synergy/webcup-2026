<?php

use App\Models\Annonce;
use App\Models\Quartier;
use App\Models\User;
use Database\Seeders\AlerteMonteeDesEauxSeeder;
use Livewire\Livewire;

/**
 * F29 — Alertes ciblées par quartier (montée des eaux — quartier sud), extension des messages généraux de D18.
 */
function alerteSud(array $valeurs = []): Annonce
{
    return Annonce::factory()->active()->create($valeurs + [
        'titre' => 'Montée des eaux — quartier sud',
        'contenu' => 'Niveau de l’eau anormalement haut.',
        'niveau' => 'alerte',
        'quartier_id' => Quartier::idPour('sud'),
        'consignes' => "Éloignez-vous des berges.\n\nMettez vos documents en hauteur.",
    ]);
}

/**
 * Variantes des bandeaux dans l'ordre d'affichage.
 *
 * @return list<string>
 */
function variantesAffichees(string $html): array
{
    preg_match_all('/data-variante="([a-z]+)"/', $html, $matches);

    return $matches[1];
}

test('les quartiers de base existent', function () {
    expect(Quartier::query()->pluck('slug')->sort()->values()->all())->toBe(['centre', 'est', 'nord', 'ouest', 'sud']);
});

test('un invité est redirigé vers la connexion sur les pages de gestion', function () {
    $alerte = alerteSud();

    $this->get(route('agent.annonces.create'))->assertRedirect(route('login'));
    $this->get(route('agent.annonces.edit', $alerte))->assertRedirect(route('login'));
});

test('un citoyen ne peut ni créer, ni modifier, ni supprimer une alerte', function () {
    $alerte = alerteSud();
    $citoyen = User::factory()->citoyen()->quartier('sud')->create();

    $this->actingAs($citoyen)->get(route('agent.annonces.create'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.annonces.edit', $alerte))->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::annonces.form')->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::annonces.form', ['annonce' => $alerte])->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::annonces.index')->assertForbidden();

    expect(Annonce::count())->toBe(1);
});

test('un agent crée une alerte ciblant le quartier sud avec des consignes', function () {
    $agent = User::factory()->agent()->create();

    Livewire::actingAs($agent)
        ->test('pages::annonces.form')
        ->set('titre', 'Montée des eaux — quartier sud')
        ->set('contenu', 'Niveau de l’eau anormalement haut.')
        ->set('niveau', 'danger')
        ->set('quartier_id', (string) Quartier::idPour('sud'))
        ->set('consignes', "Éloignez-vous des berges.\nMettez vos documents en hauteur.")
        ->call('save')
        ->assertHasNoErrors();

    $alerte = Annonce::sole();
    expect($alerte->quartier_id)->toBe(Quartier::idPour('sud'))
        ->and($alerte->niveau)->toBe('danger')
        ->and($alerte->listeConsignes())->toBe(['Éloignez-vous des berges.', 'Mettez vos documents en hauteur.'])
        ->and($alerte->user_id)->toBe($agent->id);
});

test('« Toute la ville » enregistre une alerte sans quartier et un quartier inconnu est refusé', function () {
    $agent = User::factory()->agent()->create();
    $formulaire = fn () => Livewire::actingAs($agent)->test('pages::annonces.form')
        ->set('titre', 'Coupure générale')
        ->set('contenu', 'Toute la ville.');

    $formulaire()->set('quartier_id', '999')->call('save')->assertHasErrors(['quartier_id' => 'exists']);
    $formulaire()->set('quartier_id', '')->call('save')->assertHasNoErrors();

    expect(Annonce::sole()->quartier_id)->toBeNull();
});

test('les champs réservés envoyés par le client sont ignorés', function () {
    $agent = User::factory()->agent()->create();
    $autre = User::factory()->admin()->create();

    expect(fn () => Livewire::actingAs($agent)->test('pages::annonces.form')->set('user_id', $autre->id))
        ->toThrow(Exception::class);

    $alerte = new Annonce(['user_id' => $autre->id, 'quartier_id' => Quartier::idPour('sud')]);
    expect($alerte->user_id)->toBeNull()
        ->and($alerte->quartier_id)->toBe(Quartier::idPour('sud'));
});

test('un habitant du quartier sud voit l’alerte en premier, renforcée, avec les consignes', function () {
    Annonce::factory()->active()->create(['titre' => 'Danger pour toute la ville', 'niveau' => 'danger']);
    alerteSud();
    $habitantSud = User::factory()->citoyen()->quartier('sud')->create();

    $html = $this->actingAs($habitantSud)->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Montée des eaux — quartier sud', 'Danger pour toute la ville'])
        ->assertSee('Concerne votre quartier : Sud')
        ->assertSee('<li>Éloignez-vous des berges.</li>', false)
        ->assertSee('<li>Mettez vos documents en hauteur.</li>', false)
        ->assertSee('Consignes à suivre')
        ->getContent();

    expect(variantesAffichees($html))->toBe(['renforce', 'standard']);
});

test('l’alerte grave du quartier se replie mais ne se ferme pas ; une alerte d’information se ferme', function () {
    $alerte = alerteSud();
    $information = alerteSud(['titre' => 'Information du quartier sud', 'niveau' => 'information']);
    $habitantSud = User::factory()->citoyen()->quartier('sud')->create();

    $this->actingAs($habitantSud)->get(route('home'))
        ->assertSee('Replier')
        ->assertDontSee('aria-label="Fermer le message : '.$alerte->titre.'"', false)
        ->assertSee('aria-label="Fermer le message : '.$information->titre.'"', false);
});

test('un habitant d’un autre quartier voit la version compacte, après les messages de toute la ville', function () {
    Annonce::factory()->active()->create(['titre' => 'Message pour toute la ville', 'niveau' => 'information']);
    $alerte = alerteSud();
    $habitantNord = User::factory()->citoyen()->quartier('nord')->create();

    $html = $this->actingAs($habitantNord)->get(route('home'))
        ->assertOk()
        ->assertSee('Quartier Sud uniquement')
        ->assertSee('Voir les consignes')
        ->assertSee(route('alertes.show', $alerte->id), false)
        ->assertDontSee('Concerne votre quartier')
        ->assertDontSee('Éloignez-vous des berges.')
        ->getContent();

    // Gravité d'abord pour qui n'est pas concerné : l'alerte (plus grave) passe avant l'information, mais en compact.
    expect(variantesAffichees($html))->toBe(['compact', 'standard']);
});

test('un visiteur voit la version compacte avec le lien vers les consignes', function () {
    alerteSud();

    $this->get(route('home'))
        ->assertSee('Quartier Sud uniquement')
        ->assertSee('Voir les consignes')
        ->assertDontSee('Concerne votre quartier');
});

test('un citoyen sans quartier voit l’alerte en version standard avec une invitation à renseigner son quartier', function () {
    alerteSud();
    $citoyen = User::factory()->citoyen()->create(['quartier_id' => null]);

    $this->actingAs($citoyen)->get(route('home'))
        ->assertSee('Éloignez-vous des berges.')
        ->assertSee('Indiquez votre quartier pour recevoir les alertes qui vous concernent')
        ->assertDontSee('Concerne votre quartier');
});

test('la gravité est affichée en texte, avec role="alert" pour Alerte et Danger', function (string $niveau, string $libelle, string $role) {
    alerteSud(['niveau' => $niveau]);

    $this->get(route('home'))
        ->assertSee($libelle)
        ->assertSee('role="'.$role.'"', false);
})->with([
    ['information', 'Information', 'status'],
    ['vigilance', 'Vigilance', 'status'],
    ['alerte', 'Alerte', 'alert'],
    ['danger', 'Danger', 'alert'],
]);

test('l’alerte du quartier est aussi en tête du tableau de bord de l’habitant concerné', function () {
    alerteSud();

    $this->actingAs(User::factory()->citoyen()->quartier('sud')->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Alertes dans votre quartier')
        ->assertSee('Éloignez-vous des berges.');

    $this->actingAs(User::factory()->citoyen()->quartier('nord')->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Alertes dans votre quartier');
});

test('la page publique d’une alerte en cours est accessible sans compte', function () {
    $alerte = alerteSud();

    $this->get(route('alertes.show', $alerte))
        ->assertOk()
        ->assertSee('Montée des eaux — quartier sud')
        ->assertSee('Quartier Sud uniquement')
        ->assertSee('Éloignez-vous des berges.')
        ->assertSee('Alerte');
});

test('la page publique renvoie 404 pour une alerte expirée ou programmée', function () {
    $expiree = Annonce::factory()->expiree()->create();
    $programmee = Annonce::factory()->programmee()->create();

    $this->get(route('alertes.show', $expiree))->assertNotFound();
    $this->get(route('alertes.show', $programmee))->assertNotFound();
    $this->actingAs(User::factory()->agent()->create())->get(route('alertes.show', $programmee))->assertNotFound();
});

test('les consignes sont affichées échappées', function () {
    alerteSud(['consignes' => '<script>alert("xss")</script>']);

    $this->actingAs(User::factory()->citoyen()->quartier('sud')->create())
        ->get(route('home'))
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
});

test('l’habitant choisit son quartier dans la liste de son profil', function () {
    $citoyen = User::factory()->citoyen()->create(['quartier_id' => null]);

    Livewire::actingAs($citoyen)
        ->test('pages::settings.profile')
        ->set('quartier_id', '999')
        ->call('updateProfileInformation')
        ->assertHasErrors(['quartier_id' => 'exists']);

    Livewire::actingAs($citoyen)
        ->test('pages::settings.profile')
        ->set('quartier_id', (string) Quartier::idPour('sud'))
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($citoyen->fresh()->quartier_id)->toBe(Quartier::idPour('sud'))
        ->and($citoyen->fresh()->quartier)->toBe('Sud');
});

test('le seeder publie l’alerte de démo active et peut être relancé sans doublon', function () {
    User::factory()->agent()->create();

    $this->seed(AlerteMonteeDesEauxSeeder::class);
    $this->seed(AlerteMonteeDesEauxSeeder::class);

    $alerte = Annonce::query()->where('titre', AlerteMonteeDesEauxSeeder::TITRE)->sole();
    expect($alerte->statut())->toBe('en_cours')
        ->and($alerte->niveau)->toBe('alerte')
        ->and($alerte->quartier_id)->toBe(Quartier::idPour('sud'))
        ->and($alerte->fin->greaterThan(now()->addDays(29)))->toBeTrue()
        ->and(count($alerte->listeConsignes()))->toBeGreaterThanOrEqual(4)
        ->and(User::query()->where('email', 'sud@example.com')->sole()->quartier_id)->toBe(Quartier::idPour('sud'))
        ->and(User::query()->where('email', 'nord@example.com')->sole()->quartier_id)->toBe(Quartier::idPour('nord'));
});

test('un agent met à jour la situation : une ligne horodatée est ajoutée sans recréer l’alerte', function () {
    $this->travelTo(now()->setDateTime(2026, 10, 3, 11, 30));
    $alerte = alerteSud();
    $agent = User::factory()->agent()->create();

    Livewire::actingAs($agent)
        ->test('pages::annonces.index')
        ->call('ouvrirMiseAJour', $alerte->id)
        ->set('miseAJour', '')
        ->call('enregistrerMiseAJour')
        ->assertHasErrors(['miseAJour' => 'required'])
        ->set('miseAJour', 'Le niveau se stabilise.')
        ->call('enregistrerMiseAJour')
        ->assertHasNoErrors();

    expect(Annonce::count())->toBe(1)
        ->and($alerte->fresh()->contenu)->toEndWith("\nMise à jour 14 h 30 : Le niveau se stabilise.");

    $this->get(route('home'))->assertSee('Mise à jour 14 h 30 : Le niveau se stabilise.');
});

test('le nombre de messages en cours apparaît dans la navigation de l’espace agent', function () {
    alerteSud();
    Annonce::factory()->expiree()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('agent.annonces.index'))
        ->assertSee('Messages généraux, 1 en cours');
});
