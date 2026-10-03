<?php

use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PartnerSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * F74 — Page publique des partenaires (horaires, adresse, carte) et gestion par les agents.
 */
function remplirPartenaire(mixed $test, array $valeurs = []): mixed
{
    $valeurs += [
        'name' => 'Ludothèque d\'Isotry',
        'type' => 'culture',
        'address' => '3 rue des Jeux, Isotry, Nova Terra',
        'phone' => '+261 20 00 000 09',
        'latitude' => '-18.9100',
        'longitude' => '47.5200',
        'hours.2.0.start' => '08:30',
        'hours.2.0.end' => '12:00',
    ];

    foreach ($valeurs as $champ => $valeur) {
        $test->set($champ, $valeur);
    }

    return $test;
}

test('la page Partenaires est accessible sans compte et ne montre que les partenaires publiés', function () {
    $this->seed(PartnerSeeder::class);
    Partner::factory()->unpublished()->create(['name' => 'Partenaire en brouillon']);

    $this->get(route('partners.index'))
        ->assertOk()
        ->assertSee('Centre de santé d&#039;Ampefiloha', false)
        ->assertSee('Banque alimentaire Fanampiana')
        ->assertSee('Transports urbains de Nova Terra')
        ->assertSee('Maison de l&#039;emploi et de la formation', false)
        ->assertSee('data-carte', false)
        ->assertDontSee('Partenaire en brouillon');
});

test('le filtre par type ne garde que les partenaires de ce type', function () {
    $this->seed(PartnerSeeder::class);

    $this->get(route('partners.index', ['type' => 'transport']))
        ->assertOk()
        ->assertSee('Transports urbains de Nova Terra')
        ->assertDontSee('Banque alimentaire Fanampiana');
});

test('la fiche montre horaires, adresse, téléphone cliquable, carte et lien itinéraire', function () {
    $partner = Partner::factory()->create(['name' => 'Centre social', 'address' => '5 rue Haute, Nova Terra', 'phone' => '+261 20 00 000 07', 'latitude' => -18.91, 'longitude' => 47.52]);

    $this->get(route('partners.show', $partner))
        ->assertOk()
        ->assertSee('Horaires de la semaine')
        ->assertSee('8 h 30 – 12 h 00 · 14 h 00 – 17 h 30')
        ->assertSee('5 rue Haute, Nova Terra')
        ->assertSee('tel:+26120000000', false)
        ->assertSee('data-carte', false)
        ->assertSee('https://www.openstreetmap.org/directions?to=-18.9100000%2C47.5200000', false);
});

test('statut : ouvert un mardi à 10 h, fermé pendant la pause, fermé le dimanche', function () {
    $partner = Partner::factory()->make(['opening_hours' => [
        1 => [['08:30', '12:00'], ['14:00', '17:30']],
        2 => [['08:30', '12:00'], ['14:00', '17:30']],
    ]]);

    // 6 octobre 2026 = mardi, 4 octobre 2026 = dimanche.
    expect($partner->openingStatus(Carbon::parse('2026-10-06 10:00', config('app.timezone'))))
        ->toBe(['open' => true, 'label' => 'Ouvert maintenant · ferme à 12 h 00']);
    expect($partner->openingStatus(Carbon::parse('2026-10-06 12:30', config('app.timezone'))))
        ->toBe(['open' => false, 'label' => 'Fermé · ouvre à 14 h 00']);
    expect($partner->openingStatus(Carbon::parse('2026-10-04 11:00', config('app.timezone'))))
        ->toBe(['open' => false, 'label' => 'Fermé aujourd\'hui · ouvre lundi à 8 h 30']);
    expect($partner->openingStatus(Carbon::parse('2026-10-06 18:00', config('app.timezone'))))
        ->toBe(['open' => false, 'label' => 'Fermé · ouvre lundi à 8 h 30']);
});

test('le statut affiché sur la page suit l\'heure courante', function () {
    $partner = Partner::factory()->create();

    $this->travelTo(Carbon::parse('2026-10-06 10:00', config('app.timezone')));
    $this->get(route('partners.show', $partner))->assertSee('Ouvert maintenant · ferme à 12 h 00');

    $this->travelTo(Carbon::parse('2026-10-06 12:30', config('app.timezone')));
    $this->get(route('partners.show', $partner))->assertSee('Fermé · ouvre à 14 h 00');
});

test('slug inconnu ou partenaire non publié : 404 en français', function () {
    $brouillon = Partner::factory()->unpublished()->create();

    $this->get('/partenaires/nexiste-pas')->assertNotFound()->assertSee('Page introuvable');
    $this->get(route('partners.show', $brouillon))->assertNotFound()->assertSee('Page introuvable');
    $this->actingAs(User::factory()->citoyen()->create())->get(route('partners.show', $brouillon))->assertNotFound();
});

test('un nom contenant du HTML est affiché échappé', function () {
    $partner = Partner::factory()->create(['name' => '<script>alert(1)</script>Asso']);

    $this->get(route('partners.index'))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    $this->get(route('partners.show', $partner))->assertDontSee('<script>alert(1)</script>', false);
});

test('un invité est redirigé vers la connexion sur la gestion des partenaires', function () {
    $partner = Partner::factory()->create();

    $this->get(route('agent.partners.index'))->assertRedirect(route('login'));
    $this->get(route('agent.partners.create'))->assertRedirect(route('login'));
    $this->get(route('agent.partners.edit', $partner))->assertRedirect(route('login'));
});

test('un citoyen reçoit un 403 sur la création, la modification et la suppression', function () {
    $partner = Partner::factory()->create(['name' => 'Nom d\'origine']);
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('agent.partners.index'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.partners.create'))->assertForbidden();
    $this->actingAs($citoyen)->get(route('agent.partners.edit', $partner))->assertForbidden();

    Livewire::actingAs($citoyen)->test('pages::partners.form')->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::partners.form', ['partner' => $partner])->assertForbidden();
    Livewire::actingAs($citoyen)->test('pages::partners.manage')->assertForbidden();

    expect($citoyen->can('create', Partner::class))->toBeFalse()
        ->and($citoyen->can('update', $partner))->toBeFalse()
        ->and($citoyen->can('delete', $partner))->toBeFalse();

    expect($partner->fresh()->name)->toBe('Nom d\'origine');
});

test('un agent crée un partenaire ; created_by envoyé par le client est ignoré', function () {
    $agent = User::factory()->agent()->create();
    $autre = User::factory()->create();

    $test = Livewire::actingAs($agent)->test('pages::partners.form');
    remplirPartenaire($test);
    // Champ réservé : n'est pas une propriété du composant, la tentative est refusée sans effet.
    try {
        $test->set('created_by', $autre->id);
    } catch (Throwable) {
        // Propriété inexistante : rien n'est assigné.
    }
    $test->call('save')->assertHasNoErrors()->assertRedirect(route('agent.partners.index'));

    $partner = Partner::query()->sole();
    expect($partner->created_by)->toBe($agent->id)
        ->and($partner->slug)->toBe('ludotheque-disotry')
        ->and($partner->is_published)->toBeTrue()
        ->and($partner->hoursFor(2))->toBe([['08:30', '12:00']]);

    // created_by passé en assignation de masse : ignoré (hors #[Fillable]).
    $partner->fill(['created_by' => $autre->id])->save();
    expect($partner->fresh()->created_by)->toBe($agent->id);
});

test('un agent modifie un partenaire et ses horaires', function () {
    $agent = User::factory()->agent()->create();
    $partner = Partner::factory()->create(['name' => 'Ancien nom']);
    $slug = $partner->slug;

    Livewire::actingAs($agent)->test('pages::partners.form', ['partner' => $partner])
        ->assertSet('hours.1.1.start', '14:00')
        ->set('name', 'Nouveau nom')
        ->set('hours.6.0.start', '09:00')
        ->set('hours.6.0.end', '12:00')
        ->call('save')
        ->assertHasNoErrors();

    $partner->refresh();
    expect($partner->name)->toBe('Nouveau nom')
        ->and($partner->slug)->toBe($slug)
        ->and($partner->hoursFor(6))->toBe([['09:00', '12:00']]);
});

test('un agent supprime un partenaire', function () {
    $agent = User::factory()->agent()->create();
    $partner = Partner::factory()->create();

    Livewire::actingAs($agent)->test('pages::partners.manage')->call('delete', $partner->id)->assertOk();

    expect(Partner::query()->count())->toBe(0);
});

test('coordonnées hors bornes, plages incohérentes, téléphone et site invalides sont refusés', function () {
    $agent = User::factory()->agent()->create();

    remplirPartenaire(Livewire::actingAs($agent)->test('pages::partners.form'), [
        'latitude' => '120',
        'longitude' => '-200',
        'phone' => 'appelez-nous',
        'website' => 'javascript:alert(1)',
    ])->call('save')->assertHasErrors(['latitude', 'longitude', 'phone', 'website']);

    remplirPartenaire(Livewire::actingAs($agent)->test('pages::partners.form'), [
        'hours.3.0.start' => '14:00',
        'hours.3.0.end' => '12:00',
    ])->call('save')->assertHasErrors(['hours.3.0.end']);

    remplirPartenaire(Livewire::actingAs($agent)->test('pages::partners.form'), [
        'hours.4.0.start' => '08:00',
        'hours.4.0.end' => '12:00',
        'hours.4.1.start' => '11:00',
        'hours.4.1.end' => '15:00',
    ])->call('save')->assertHasErrors(['hours.4.1.start']);

    remplirPartenaire(Livewire::actingAs($agent)->test('pages::partners.form'), [
        'hours.5.0.start' => '8h',
        'hours.5.0.end' => '',
    ])->call('save')->assertHasErrors(['hours.5.0.start', 'hours.5.0.end']);

    expect(Partner::query()->count())->toBe(0);
});

test('le seeder est idempotent', function () {
    $this->seed(PartnerSeeder::class);
    $this->seed(PartnerSeeder::class);

    expect(Partner::query()->count())->toBe(4)
        ->and(Partner::query()->published()->count())->toBe(4)
        ->and(Partner::query()->distinct()->count('type'))->toBe(4);
});
