<?php

use App\Models\Demarche;
use App\Models\FormSubmission;
use App\Models\Message;
use App\Models\Service;
use App\Models\ServiceReview;
use App\Models\Signalement;
use App\Models\User;
use App\Services\EnvoisUniques;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * F82 : un même envoi (double clic, retour arrière, rafraîchissement) ne crée pas de doublon.
 * Chaque formulaire du dataset est décrit par : composant, paramètres de mount, données valides,
 * modèle compté, et URL de la page « voir ma demande » de l'enregistrement d'origine.
 */

/**
 * @return array{composant: string, params: array<string, mixed>, donnees: array<string, string>, modele: class-string, url: Closure(Model): string}
 */
function formulaireDemarcheF82(): array
{
    return [
        'composant' => 'pages::demarches.form',
        'params' => [],
        'donnees' => ['titre' => 'Demande d’acte de naissance', 'description' => 'Pour mon fils né le 2 mars 2019.', 'service_id' => (string) Service::factory()->create()->id],
        'modele' => Demarche::class,
        'url' => fn ($demarche): string => route('demarches.show', $demarche),
    ];
}

function formulaireSignalementF82(): array
{
    return [
        'composant' => 'pages::signalements.form',
        'params' => [],
        'donnees' => ['categorie' => Signalement::CATEGORIE_OPTIONS[0], 'description' => 'Le lampadaire devant le n° 12 est cassé.', 'lieu' => 'Rue des Lumières'],
        'modele' => Signalement::class,
        'url' => fn ($signalement): string => route('signalements.show', $signalement),
    ];
}

function formulaireContactF82(): array
{
    return [
        'composant' => 'pages::messages.form',
        'params' => [],
        'donnees' => ['sujet' => 'Horaires de la mairie', 'message' => 'Bonjour, la mairie est-elle ouverte le samedi ?'],
        'modele' => Message::class,
        'url' => fn ($message): string => route('messages.show', $message),
    ];
}

function formulaireAvisF82(): array
{
    return [
        'composant' => 'pages::service-reviews.form',
        'params' => ['service' => Service::factory()->create()],
        'donnees' => ['rating' => '4', 'comment' => 'Accueil rapide et agent très aimable.'],
        'modele' => ServiceReview::class,
        'url' => fn (): string => route('services.reviews.mine'),
    ];
}

dataset('formulaires F82', [
    'démarches' => [fn (): array => formulaireDemarcheF82()],
    'signalements' => [fn (): array => formulaireSignalementF82()],
    'contact' => [fn (): array => formulaireContactF82()],
    'avis' => [fn (): array => formulaireAvisF82()],
]);

/**
 * Affiche le formulaire, le remplit, attend 5 s (délai minimal F81) et l'envoie.
 *
 * @param  array<string, mixed>  $formulaire
 */
function envoyerF82(User $user, array $formulaire, ?array $donnees = null): Testable
{
    $composant = Livewire::actingAs($user)->test($formulaire['composant'], $formulaire['params']);

    foreach ($donnees ?? $formulaire['donnees'] as $champ => $valeur) {
        $composant->set($champ, $valeur);
    }

    test()->travel(5)->seconds();

    return $composant->call('save');
}

test('double clic sur le même formulaire : un seul enregistrement', function (Closure $preparer) {
    $formulaire = $preparer();
    $user = User::factory()->citoyen()->create();

    envoyerF82($user, $formulaire)->call('save');

    expect($formulaire['modele']::count())->toBe(1)
        ->and(FormSubmission::count())->toBe(1);
})->with('formulaires F82');

test('même contenu renvoyé dans la fenêtre (retour arrière) : un seul enregistrement et un message daté avec lien', function (Closure $preparer) {
    $formulaire = $preparer();
    $user = User::factory()->citoyen()->create();

    envoyerF82($user, $formulaire);
    $envoi = FormSubmission::sole();
    $this->travel(2)->minutes();

    envoyerF82($user, $formulaire)
        ->assertHasNoErrors()
        ->assertSee('déjà été envoy')
        ->assertSee($envoi->envoyeLe())
        ->assertSeeHtml('href="'.$formulaire['url']($envoi->submittable).'"');

    expect($formulaire['modele']::count())->toBe(1)
        ->and(FormSubmission::count())->toBe(1);
})->with('formulaires F82');

test('même contenu après la fenêtre de 5 minutes : envoyé à nouveau', function (Closure $preparer) {
    $formulaire = $preparer();
    $user = User::factory()->citoyen()->create();

    envoyerF82($user, $formulaire);
    $this->travel(6)->minutes();

    envoyerF82($user, $formulaire)->assertDontSee('déjà été envoy');

    // L'avis reste unique par habitant et par service (mis à jour), les autres formulaires créent une 2e ligne.
    expect(FormSubmission::count())->toBe(2)
        ->and($formulaire['modele']::count())->toBe($formulaire['modele'] === ServiceReview::class ? 1 : 2);
})->with('formulaires F82');

test('deux habitants envoient le même contenu : deux enregistrements, aucun message de doublon', function (Closure $preparer) {
    $formulaire = $preparer();

    envoyerF82(User::factory()->citoyen()->create(), $formulaire);
    envoyerF82(User::factory()->citoyen()->create(), $formulaire)
        ->assertHasNoErrors()
        ->assertDontSee('déjà été envoy');

    expect($formulaire['modele']::count())->toBe(2);
})->with('formulaires F82');

test('le bouton d’envoi est désactivé pendant l’envoi avec « Envoi en cours… »', function (Closure $preparer) {
    $formulaire = $preparer();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test($formulaire['composant'], $formulaire['params'])
        ->assertSeeHtml('wire:loading.attr="disabled"')
        ->assertSeeHtml('wire:target="save"')
        ->assertSee('Envoi en cours…');
})->with('formulaires F82');

test('échec de validation puis envoi corrigé avec le même jeton : enregistré', function (Closure $preparer) {
    $formulaire = $preparer();
    $user = User::factory()->citoyen()->create();
    $champ = array_key_first(array_filter($formulaire['donnees'], fn (string $valeur, string $champ): bool => $champ !== 'service_id', ARRAY_FILTER_USE_BOTH));

    $composant = envoyerF82($user, $formulaire, [...$formulaire['donnees'], $champ => ''])->assertHasErrors($champ);
    $jeton = $composant->get('jetonEnvoi');

    expect(FormSubmission::count())->toBe(0);

    $composant->set($champ, $formulaire['donnees'][$champ])->call('save')->assertHasNoErrors();

    expect($formulaire['modele']::count())->toBe(1)
        ->and(FormSubmission::sole()->token)->toBe($jeton);
})->with('formulaires F82');

test('un invité est renvoyé vers la connexion', function (Closure $route) {
    $this->get($route())->assertRedirect(route('login'));
})->with([
    'démarches' => [fn (): string => route('demarches.create')],
    'signalements' => [fn (): string => route('signalements.create')],
    'contact' => [fn (): string => route('messages.create')],
    'avis' => [fn (): string => route('services.reviews.edit', Service::factory()->create())],
]);

test('la page « voir ma demande » reste interdite à un autre habitant', function (Closure $preparer) {
    $formulaire = $preparer();
    envoyerF82(User::factory()->citoyen()->create(), $formulaire);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get($formulaire['url'](FormSubmission::sole()->submittable))
        ->assertForbidden();
})->with([
    'démarches' => [fn (): array => formulaireDemarcheF82()],
    'signalements' => [fn (): array => formulaireSignalementF82()],
    'contact' => [fn (): array => formulaireContactF82()],
]);

test('« Mes avis » d’un autre habitant ne montre pas l’avis', function () {
    $formulaire = formulaireAvisF82();
    envoyerF82(User::factory()->citoyen()->create(), $formulaire);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.reviews.mine'))
        ->assertOk()
        ->assertDontSee($formulaire['donnees']['comment']);
});

test('un jeton déjà utilisé est un doublon, même avec un autre contenu, et rien n’est créé', function () {
    $user = User::factory()->citoyen()->create();
    envoyerF82($user, formulaireSignalementF82());
    $jeton = FormSubmission::sole()->token;
    $envois = app(EnvoisUniques::class);

    $this->actingAs($user);
    expect($envois->doublon('signalement', $jeton, $envois->empreinte(['description' => 'autre chose'])))->not->toBeNull();

    // Deux requêtes simultanées avec le même jeton : la seconde est interceptée (index unique), jamais de 500.
    $resultat = $envois->enregistrer('signalement', $jeton, 'autre', fn (): Signalement => Signalement::factory()->for($user)->create());

    expect($resultat)->toBeInstanceOf(FormSubmission::class)
        ->and(Signalement::count())->toBe(1)
        ->and(FormSubmission::count())->toBe(1);
});

test('l’empreinte ignore les espaces en trop', function () {
    $envois = app(EnvoisUniques::class);

    expect($envois->empreinte(['message' => "  Bonjour   la\n mairie "]))->toBe($envois->empreinte(['message' => 'Bonjour la mairie']));
});

test('les champs réservés envoyés par le navigateur sont refusés', function (string $champ, string $exception) {
    $composant = Livewire::actingAs(User::factory()->citoyen()->create())->test('pages::signalements.form');

    expect(fn () => $composant->set($champ, 'falsifie'))->toThrow($exception);
})->with([
    'user_id' => ['user_id', PublicPropertyNotFoundException::class],
    'content_hash' => ['content_hash', PublicPropertyNotFoundException::class],
    'jetonEnvoi' => ['jetonEnvoi', CannotUpdateLockedPropertyException::class],
    'envoiDejaFaitUrl' => ['envoiDejaFaitUrl', CannotUpdateLockedPropertyException::class],
]);

test('FormSubmission n’accepte aucune assignation de masse', function () {
    expect(fn () => (new FormSubmission)->fill(['user_id' => 1, 'token' => 'x', 'content_hash' => 'y', 'submittable_id' => 1]))
        ->toThrow(MassAssignmentException::class);
});

test('le contenu saisi est échappé sur la page de détail', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireSignalementF82();

    envoyerF82($user, $formulaire, [...$formulaire['donnees'], 'description' => '<script>alert("x")</script>']);

    $this->actingAs($user)
        ->get(route('signalements.show', Signalement::sole()))
        ->assertOk()
        ->assertDontSee('<script>alert("x")</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

test('F81 : un envoi rejeté comme robot ne crée rien et ne consomme pas le jeton', function () {
    $user = User::factory()->citoyen()->create();
    $formulaire = formulaireSignalementF82();

    $composant = envoyerF82($user, $formulaire, [...$formulaire['donnees'], 'site_web' => 'robot'])->assertHasErrors('throttle');
    $jeton = $composant->get('jetonEnvoi');

    expect(Signalement::count())->toBe(0)
        ->and(FormSubmission::count())->toBe(0);

    $composant->set('site_web', '')->call('save')->assertHasNoErrors();

    expect(Signalement::count())->toBe(1)
        ->and(FormSubmission::sole()->token)->toBe($jeton);
});
