<?php

use App\Filament\Resources\ReglesAssistant\Pages\ManageReglesAssistant;
use App\Models\ConversationAssistant;
use App\Models\MotCleService;
use App\Models\Service;
use App\Models\User;
use App\Services\AssistantOrientation;
use App\Services\ReformulateurRequete;
use Livewire\Livewire;

function servicesAssistant(): array
{
    $proprete = Service::factory()->create(['nom' => 'Environnement et propreté', 'categorie' => 'urbanisme', 'description' => 'Collecte des ordures.']);
    $etatCivil = Service::factory()->create(['nom' => 'État civil', 'categorie' => 'administratif', 'description' => 'Actes officiels.']);

    MotCleService::query()->create(['service_id' => $proprete->id, 'mot' => 'poubelle']);
    MotCleService::query()->create(['service_id' => $etatCivil->id, 'mot' => 'acte de naissance']);

    return [$proprete, $etatCivil];
}

test('une demande mal écrite mène au bon service, avec une action concrète', function () {
    [$proprete] = servicesAssistant();

    $reponse = app(AssistantOrientation::class)->repondre('ma poubel n est pas ramasée');

    expect($reponse['type'])->toBe(ConversationAssistant::TYPE_SERVICE)
        ->and($reponse['service_id'])->toBe($proprete->id)
        ->and(array_column($reponse['actions'], 'url'))->toContain(route('services.show', $proprete), route('messages.create'));
});

test('si la reformulation (IA) est indisponible, la recherche par mots-clés prend le relais', function () {
    [$proprete] = servicesAssistant();

    app()->bind(ReformulateurRequete::class, fn () => new class implements ReformulateurRequete
    {
        public function reformuler(string $requete): ?string
        {
            throw new RuntimeException('IA indisponible');
        }
    });

    $reponse = app(AssistantOrientation::class)->repondre('poubelle');

    expect($reponse['service_id'])->toBe($proprete->id)
        ->and(array_column($reponse['actions'], 'url'))->toContain(route('services.show', $proprete));
});

test('une demande trop vague obtient une question de précision, jamais une impasse', function () {
    servicesAssistant();

    $reponse = app(AssistantOrientation::class)->repondre('bonjour je veux faire une demande');

    expect($reponse['type'])->toBe(ConversationAssistant::TYPE_PRECISION)
        ->and(end($reponse['actions'])['url'])->toBe(route('messages.create'));
});

test('une règle de l’admin répond aux questions fréquentes, même avec des fautes', function () {
    $reponse = app(AssistantOrientation::class)->repondre('j ai oublié mon mot de pase');

    expect($reponse['type'])->toBe(ConversationAssistant::TYPE_REGLE)
        ->and(array_column($reponse['actions'], 'url'))->toContain(url('/settings/security'));
});

test('l’habitant converse avec la bulle et l’échange est enregistré anonymisé', function () {
    [$proprete] = servicesAssistant();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('assistant-orientation')
        ->set('message', 'poubelle pleine, appelez moi au 034 12 345 67 ou jean@exemple.mg')
        ->call('envoyer')
        ->assertHasNoErrors()
        ->assertSee('Environnement et propreté')
        ->assertSee('Contacter la mairie');

    $echange = ConversationAssistant::query()->sole();

    expect($echange->service_id)->toBe($proprete->id)
        ->and($echange->message)->not->toContain('jean@exemple.mg')
        ->and($echange->message)->not->toContain('345')
        ->and($echange->getAttributes())->not->toHaveKey('user_id');
});

test('l’habitant choisit un service proposé après une question de précision', function () {
    [, $etatCivil] = servicesAssistant();

    Livewire::actingAs(User::factory()->create())
        ->test('assistant-orientation')
        ->call('choisir', $etatCivil->id)
        ->assertSee(route('services.show', $etatCivil), false)
        ->assertSee(route('demarches.create', ['service' => $etatCivil->id]), false);
});

test('l’assistant limite le nombre de questions par minute', function () {
    $component = Livewire::actingAs(User::factory()->create())->test('assistant-orientation');

    foreach (range(1, 10) as $i) {
        $component->set('message', 'poubelle')->call('envoyer')->assertHasNoErrors();
    }

    $component->set('message', 'poubelle')->call('envoyer')->assertHasErrors('throttle');
});

test('la bulle d’aide est présente sur les pages de l’espace connecté', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="assistant-bulle"', false)
        ->assertSee('Assistant automatisé');
});

test('seul un admin consulte les conversations et les règles de l’assistant', function () {
    ConversationAssistant::factory()->create();

    $this->actingAs(User::factory()->create())->get('/admin/conversations-assistant')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/regles-assistant')->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get('/admin/conversations-assistant')->assertOk();
});

test('une règle ne peut pas pointer vers un lien externe ou javascript', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageReglesAssistant::class)
        ->callAction('create', data: [
            'declencheurs' => 'piège',
            'reponse' => 'Cliquez ici',
            'lien_libelle' => 'Lien',
            'lien_url' => 'javascript:alert(1)',
        ])
        ->assertHasFormErrors(['lien_url']);
});
