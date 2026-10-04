<?php

use App\Models\Partner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerFactory;
use Database\Seeders\PartnerSeeder;
use Livewire\Livewire;

/*
 * F74 : page publique des partenaires (horaires, adresse, carte) et gestion par les agents / admins.
 */

/**
 * Lundi → vendredi, 8 h 30 – 12 h 00 et 14 h 00 – 17 h 30 ; fermé le week-end.
 */
function partenaireAvecPause(array $attributs = []): Partner
{
    return Partner::factory()->create([
        'opening_hours' => PartnerFactory::semaine(
            [['start' => '08:30', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:30']],
            ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'],
        ),
        ...$attributs,
    ]);
}

/** Heure de Nova Terra (le 6 octobre 2026 est un mardi, le 4 un dimanche). */
function heureNovaTerra(string $dateHeure): CarbonImmutable
{
    return CarbonImmutable::parse($dateHeure, Partner::FUSEAU);
}

/**
 * @return array<string, mixed>
 */
function champsPartenaire(array $surcharge = []): array
{
    return [
        'name' => 'Clinique du Lac',
        'type' => 'sante',
        'address' => '1 rue du Lac, Nova Terra',
        'phone' => '+261 20 00 000 09',
        'email' => 'contact@clinique.exemple.mg',
        'website' => 'https://clinique.example.org',
        'latitude' => '-18.91',
        'longitude' => '47.52',
        'hours.lundi.0.start' => '08:00',
        'hours.lundi.0.end' => '12:00',
        ...$surcharge,
    ];
}

test('la page partenaires est publique et ne montre que les partenaires publiés', function () {
    $this->seed(PartnerSeeder::class);
    $brouillon = Partner::factory()->unpublished()->create(['name' => 'Partenaire en préparation']);

    $reponse = $this->get('/partenaires')->assertOk();

    foreach (Partner::query()->published()->pluck('name') as $nom) {
        $reponse->assertSee($nom);
    }

    expect(Partner::query()->published()->count())->toBe(4);
    $reponse->assertDontSee($brouillon->name)->assertSee('data-carte', false);
});

test('le filtre par type ne garde que les partenaires du type choisi', function () {
    Partner::factory()->create(['name' => 'Bus Express', 'type' => 'transport']);
    Partner::factory()->create(['name' => 'Dispensaire Soa', 'type' => 'sante']);

    $this->get('/partenaires?type=transport')->assertSee('Bus Express')->assertDontSee('Dispensaire Soa');
});

test('la fiche affiche horaires, adresse, téléphone, carte et itinéraire', function () {
    $partner = partenaireAvecPause(['address' => '7 rue des Lilas, Nova Terra', 'phone' => '+261 20 00 000 07', 'latitude' => -18.9065, 'longitude' => 47.5225]);

    $this->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('8 h 30 – 12 h 00, 14 h 00 – 17 h 30')
        ->assertSee('7 rue des Lilas, Nova Terra')
        ->assertSee('tel:+261200000007', false)
        ->assertSee('data-carte', false)
        ->assertSee('https://www.openstreetmap.org/directions?to=-18.9065%2C47.5225', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

test('ouvert à 10 h un mardi', function () {
    $statut = partenaireAvecPause()->openingStatus(heureNovaTerra('2026-10-06 10:00'));

    expect($statut['open'])->toBeTrue()
        ->and($statut['label'])->toBe('Ouvert maintenant · ferme à 12 h 00');
});

test('fermé pendant la pause déjeuner, rouvre à 14 h 00', function () {
    $statut = partenaireAvecPause()->openingStatus(heureNovaTerra('2026-10-06 12:30'));

    expect($statut['open'])->toBeFalse()
        ->and($statut['label'])->toBe('Fermé · ouvre à 14 h 00');
});

test('fermé le dimanche, avec le prochain jour d\'ouverture', function () {
    $statut = partenaireAvecPause()->openingStatus(heureNovaTerra('2026-10-04 10:00'));

    expect($statut['open'])->toBeFalse()
        ->and($statut['label'])->toBe('Fermé aujourd\'hui · ouvre demain à 8 h 30');

    expect(partenaireAvecPause()->openingStatus(heureNovaTerra('2026-10-03 10:00'))['label'])
        ->toBe('Fermé aujourd\'hui · ouvre lundi à 8 h 30');
});

test('le statut suit l\'heure de Nova Terra et s\'affiche avec un texte', function () {
    $partner = partenaireAvecPause();
    // 7 h 00 UTC = 10 h 00 à Nova Terra.
    $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00', 'UTC'));

    $this->get(route('partners.show', $partner))->assertSee('Ouvert maintenant · ferme à 12 h 00');
});

test('un slug inconnu ou un partenaire non publié donne une 404 en français', function () {
    $brouillon = Partner::factory()->unpublished()->create();

    $this->get('/partenaires/inconnu')->assertNotFound()->assertSee('Page introuvable');
    $this->get(route('partners.show', $brouillon))->assertNotFound();
});

test('un agent voit la fiche d\'un partenaire non publié', function () {
    $brouillon = Partner::factory()->unpublished()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->get(route('partners.show', $brouillon))
        ->assertOk()
        ->assertSee('Non publié');
});

test('un invité est redirigé vers la connexion sur la gestion', function () {
    $this->get('/agent/partenaires')->assertRedirect(route('login'));
    $this->get('/agent/partenaires/create')->assertRedirect(route('login'));
});

test('un citoyen ne peut ni créer, ni modifier, ni supprimer un partenaire', function () {
    $citoyen = User::factory()->citoyen()->create();
    $partner = Partner::factory()->create();

    $this->actingAs($citoyen);
    $this->get('/agent/partenaires')->assertForbidden();
    $this->get('/agent/partenaires/create')->assertForbidden();
    $this->get(route('agent.partners.edit', $partner))->assertForbidden();

    Livewire::test('pages::partners.form')->assertForbidden();
    Livewire::test('pages::partners.form', ['partner' => $partner])->assertForbidden();

    expect($citoyen->can('create', Partner::class))->toBeFalse()
        ->and($citoyen->can('update', $partner))->toBeFalse()
        ->and($citoyen->can('delete', $partner))->toBeFalse();
});

test('un agent crée un partenaire ; created_by envoyé par le client est ignoré', function () {
    $agent = User::factory()->agent()->create();
    $autre = User::factory()->create();

    $composant = Livewire::actingAs($agent)->test('pages::partners.form');

    foreach (champsPartenaire() as $champ => $valeur) {
        $composant->set($champ, $valeur);
    }

    expect(fn () => $composant->set('created_by', $autre->id))->toThrow(Exception::class);

    $composant->call('save')->assertHasNoErrors();

    $partner = Partner::query()->where('name', 'Clinique du Lac')->firstOrFail();
    expect($partner->created_by)->toBe($agent->id)
        ->and($partner->slug)->toBe('clinique-du-lac')
        ->and($partner->hoursFor('lundi'))->toBe([['start' => '08:00', 'end' => '12:00']])
        ->and($partner->hoursFor('mardi'))->toBe([]);
});

test('created_by et is_published ne sont pas assignables en masse', function () {
    $partner = new Partner(['name' => 'X', 'created_by' => 99, 'is_published' => true, 'slug' => 'pirate']);

    expect($partner->created_by)->toBeNull()
        ->and($partner->is_published)->toBeNull()
        ->and($partner->slug)->toBeNull();
});

test('un agent modifie un partenaire mais seul un admin le supprime (D09)', function () {
    $agent = User::factory()->agent()->create();
    $partner = Partner::factory()->create(['name' => 'Ancien nom']);

    Livewire::actingAs($agent)
        ->test('pages::partners.form', ['partner' => $partner])
        ->set('name', 'Nouveau nom')
        ->set('is_published', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($partner->fresh()->name)->toBe('Nouveau nom')
        ->and($partner->fresh()->is_published)->toBeFalse();

    Livewire::actingAs($agent)
        ->test('pages::partners.form', ['partner' => $partner->fresh()])
        ->call('delete')
        ->assertForbidden();

    expect(Partner::query()->find($partner->id))->not->toBeNull();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::partners.form', ['partner' => $partner->fresh()])
        ->call('delete');

    expect(Partner::query()->find($partner->id))->toBeNull();
});

test('les coordonnées hors bornes et les plages incohérentes sont refusées', function (array $surcharge, string $erreur) {
    $composant = Livewire::actingAs(User::factory()->agent()->create())->test('pages::partners.form');

    foreach (champsPartenaire($surcharge) as $champ => $valeur) {
        $composant->set($champ, $valeur);
    }

    $composant->call('save')->assertHasErrors($erreur);
    expect(Partner::query()->count())->toBe(0);
})->with([
    'latitude' => [['latitude' => '120'], 'latitude'],
    'longitude' => [['longitude' => '-200'], 'longitude'],
    'fin avant début' => [['hours.lundi.0.start' => '17:00', 'hours.lundi.0.end' => '09:00'], 'hours.lundi.0.end'],
    'plages qui se chevauchent' => [['hours.lundi.1.start' => '11:00', 'hours.lundi.1.end' => '15:00'], 'hours.lundi.1.start'],
    'format horaire' => [['hours.lundi.0.start' => '8h'], 'hours.lundi.0.start'],
    'téléphone' => [['phone' => 'appelez-nous'], 'phone'],
    'site web' => [['website' => 'javascript:alert(1)'], 'website'],
]);

test('un nom contenant du HTML est affiché échappé', function () {
    $partner = Partner::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->get(route('partners.show', $partner))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);

    $this->get('/partenaires')->assertDontSee('<script>alert(1)</script>', false);
});

test('le seeder est idempotent', function () {
    $this->seed(PartnerSeeder::class);
    $this->seed(PartnerSeeder::class);

    expect(Partner::query()->count())->toBe(4)
        ->and(Partner::query()->distinct()->count('type'))->toBe(4);
});
