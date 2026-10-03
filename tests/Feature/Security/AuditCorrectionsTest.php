<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\ActionLog;
use App\Models\Annonce;
use App\Models\Demarche;
use App\Models\LoginAttempt;
use App\Models\Message;
use App\Models\Remontee;
use App\Models\Role;
use App\Models\Service;
use App\Models\Signalement;
use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
| F69 : un test par ligne du rapport docs/SECURITY-AUDIT.md (A1 est aussi couvert dans ServiceTest),
| plus les contrôles transverses : mass assignment, injection dans les filtres, échappement XSS.
*/

test('A1 : un habitant reçoit 403 sur la création d’un service ; un agent y accède', function () {
    $this->actingAs(User::factory()->citoyen()->create())->get(route('services.create'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get(route('services.create'))->assertOk();
});

test('A5 : le mot de passe saisi dans l’admin suit la politique de l’application', function () {
    Password::defaults(fn (): Password => Password::min(12)->mixedCase()->numbers()->symbols());
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Jury', 'email' => 'jury@example.com', 'password' => 'court12!', 'role_id' => Role::idFor(Role::AGENT)])
        ->call('create')
        ->assertHasFormErrors(['password']);

    expect(User::where('email', 'jury@example.com')->exists())->toBeFalse();
});

test('A7 : un échec de connexion par téléphone est rattaché au compte visé', function () {
    $user = User::factory()->create(['telephone' => '0341234567']);

    $this->post(route('login.store'), ['email' => '034 12 345 67', 'password' => 'mauvais-mot-de-passe']);

    expect(LoginAttempt::sole()->user_id)->toBe($user->id);
});

test('A8 : le journal des actions n’est lisible que par un administrateur', function () {
    expect(User::factory()->agent()->create()->can('viewAny', ActionLog::class))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('viewAny', ActionLog::class))->toBeTrue();
});

test('A9 : un filtre ou une recherche malveillants sont ignorés, sans erreur SQL', function (string $propriete, string $valeur) {
    $user = User::factory()->create();
    Signalement::factory()->for($user)->create(['lieu' => 'Place du Marché']);

    Livewire::actingAs($user)
        ->test('pages::signalements.index')
        ->set($propriete, $valeur)
        ->assertOk()
        ->assertSee('Place du Marché');
})->with([
    'statut' => ['filterStatut', "nouveau' OR '1'='1"],
    'catégorie' => ['filterCategorie', 'voirie; DROP TABLE signalements; --'],
]);

test('A9 : un joker « % » saisi dans la recherche est cherché comme du texte, pas comme « tout »', function () {
    $user = User::factory()->create();
    Signalement::factory()->for($user)->create(['lieu' => 'Place du Marché', 'description' => 'Lampadaire éteint']);

    Livewire::actingAs($user)
        ->test('pages::signalements.index')
        ->set('search', '%')
        ->assertOk()
        ->assertDontSee('Place du Marché');
});

test('A9 : un identifiant de service non numérique est ignoré dans les filtres des démarches', function () {
    $user = User::factory()->create();
    Demarche::factory()->for($user)->create(['titre' => 'Copie d’acte de naissance']);

    Livewire::actingAs($user)
        ->test('pages::demarches.index')
        ->set('filterServiceId', '1 OR 1=1')
        ->assertOk()
        ->assertSee('Copie d’acte de naissance');
});

test('A10 : l’identifiant de l’annonce en cours de mise à jour ne peut pas être modifié par le navigateur', function () {
    $annonce = Annonce::factory()->create();

    expect(fn () => Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::annonces.index')
        ->set('miseAJourId', $annonce->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('A13 : la page récapitulative imprimable n’a plus de gestionnaire JavaScript en ligne', function () {
    $user = User::factory()->create();
    Demarche::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('demarches.recapitulatif'))
        ->assertOk()
        ->assertDontSee('onclick=', false);
});

test('les champs réservés envoyés en masse sont ignorés', function (string $modele, array $reserves, array $metier) {
    $vierge = new $modele;
    $instance = (new $modele)->fill([...$metier, ...$reserves]);

    foreach (array_keys($reserves) as $champ) {
        // Le champ garde sa valeur par défaut (ex. statut « nouveau ») : la valeur envoyée est ignorée.
        expect($instance->getAttributes()[$champ] ?? null)->toBe($vierge->getAttributes()[$champ] ?? null);
    }
})->with([
    'compte' => [User::class, ['role_id' => 3, 'deactivated_at' => '2026-01-01', 'identifiant' => 'HAB-ADMIN1'], ['name' => 'Rabe']],
    'signalement' => [Signalement::class, ['user_id' => 1, 'statut' => 'resolu'], ['lieu' => 'Rue A']],
    'démarche' => [Demarche::class, ['user_id' => 1, 'statut' => 'traitee'], ['titre' => 'Acte']],
    'service' => [Service::class, ['user_id' => 1, 'mis_en_avant' => true], ['nom' => 'Guichet']],
    'message' => [Message::class, ['user_id' => 1], ['sujet' => 'Bonjour']],
    'annonce' => [Annonce::class, ['notified_at' => '2026-01-01'], ['titre' => 'Coupure']],
]);

test('un contenu <script> saisi par un habitant est affiché comme du texte', function (string $route, Closure $creer) {
    $charge = '<script>alert("xss")</script>';
    $ressource = $creer($charge);

    $this->actingAs($ressource->user)
        ->get(route($route, $ressource))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee($charge, false);
})->with([
    'signalement (description, lieu)' => ['signalements.show', fn (string $c) => Signalement::factory()->create(['description' => $c, 'lieu' => $c])],
    'démarche (titre, description)' => ['demarches.show', fn (string $c) => Demarche::factory()->create(['titre' => $c, 'description' => $c])],
    'remontée (objet, message)' => ['concerns.show', fn (string $c) => Remontee::factory()->create(['objet' => $c, 'message' => $c])],
    'message (sujet, message)' => ['messages.show', fn (string $c) => Message::factory()->create(['sujet' => $c, 'message' => $c])],
]);
