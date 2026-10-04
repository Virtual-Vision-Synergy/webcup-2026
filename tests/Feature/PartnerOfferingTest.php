<?php

use App\Models\AvailabilitySubscription;
use App\Models\Partner;
use App\Models\PartnerOffering;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Avis;
use Carbon\CarbonImmutable;
use Database\Factories\PartnerFactory;
use Livewire\Livewire;

/*
 * F99 : services proposés par les partenaires dans le catalogue (espace partenaire, catalogue, fiche publique,
 * prochaine action, « Me prévenir quand disponible »).
 */

/** Partenaire publié ouvert du lundi au vendredi, 8 h – 12 h (heure de Nova Terra). */
function partenaireMatin(array $attributs = []): Partner
{
    return Partner::factory()->create([
        'opening_hours' => PartnerFactory::semaine([['start' => '08:00', 'end' => '12:00']], ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']),
        ...$attributs,
    ]);
}

/** Le 6 octobre 2026 est un mardi. */
function mardiNovaTerra(string $heure): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-06 '.$heure, Partner::FUSEAU);
}

// --- Catalogue côté habitant ---------------------------------------------------------------------------------------

test('le catalogue affiche les services partenaires publiés avec le badge Partenaire et le libellé d\'état', function () {
    $partner = partenaireMatin(['name' => 'Centre Fanilo']);
    PartnerOffering::factory()->for($partner)->create(['title' => 'Consultation générale']);
    PartnerOffering::factory()->for($partner)->full()->create(['title' => 'Vaccination des enfants']);
    PartnerOffering::factory()->for($partner)->unpublished()->create(['title' => 'Navette en préparation']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.index'))
        ->assertOk()
        ->assertSee('Partenaire')
        ->assertSee('Centre Fanilo')
        ->assertSee('Consultation générale')
        ->assertSee('Disponible')
        ->assertSee('Vaccination des enfants')
        ->assertSee('Complet')
        ->assertDontSee('Navette en préparation');
});

test('un service d\'un partenaire non publié n\'apparaît pas dans le catalogue', function () {
    $brouillon = Partner::factory()->unpublished()->create();
    PartnerOffering::factory()->for($brouillon)->create(['title' => 'Service caché']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.index'))
        ->assertDontSee('Service caché');
});

test('« Disponible maintenant » exclut les services complets, indisponibles et fermés à cette heure', function () {
    $this->travelTo(mardiNovaTerra('10:00'));

    $partner = partenaireMatin();
    $ouvert = PartnerOffering::factory()->for($partner)->create();
    PartnerOffering::factory()->for($partner)->full()->create();
    PartnerOffering::factory()->for($partner)->unavailable()->create();
    // Disponible, mais ses horaires propres (l'après-midi) le disent fermé à 10 h.
    PartnerOffering::factory()->for($partner)->create([
        'opening_hours' => PartnerFactory::semaine([['start' => '14:00', 'end' => '17:00']], ['mardi']),
    ]);

    $ids = Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::services.index')
        ->set('maintenant', true)
        ->instance()->offresPartenaires->pluck('id')->all();

    expect($ids)->toBe([$ouvert->id]);
});

test('le filtre « Disponible maintenant » est lisible dans l\'URL et combinable', function () {
    $this->travelTo(mardiNovaTerra('15:00'));

    PartnerOffering::factory()->for(partenaireMatin())->create(['title' => 'Atelier CV']);

    // 15 h : le partenaire (8 h – 12 h) est fermé.
    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.index', ['maintenant' => 1, 'type' => 'partenaires']))
        ->assertOk()
        ->assertDontSee('Atelier CV');
});

test('le filtre « Services municipaux » masque les services partenaires', function () {
    PartnerOffering::factory()->for(partenaireMatin())->create(['title' => 'Atelier CV']);

    $ids = Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::services.index')
        ->set('type', 'municipaux')
        ->instance()->offresPartenaires;

    expect($ids)->toBeEmpty();
});

// --- Prochaine action -----------------------------------------------------------------------------------------------

test('prochaine action : Réserver, Contacter, Me prévenir et alternative selon l\'état et les champs', function () {
    $partner = partenaireMatin(['phone' => '+261 20 00 000 99']);

    $reservable = PartnerOffering::factory()->for($partner)->bookable('https://rdv.example.org')->create();
    $contact = PartnerOffering::factory()->for($partner)->create(['contact_phone' => null, 'contact_email' => 'accueil@example.org']);
    $complet = PartnerOffering::factory()->for($partner)->full()->bookable()->create();
    $indispo = PartnerOffering::factory()->for($partner)->unavailable('2026-10-12')->withAlternative()->create();

    expect($reservable->prochaineAction()['principale'])->toMatchArray(['type' => 'reserver', 'label' => 'Réserver', 'url' => 'https://rdv.example.org', 'externe' => true])
        ->and($contact->prochaineAction()['principale'])->toMatchArray(['type' => 'contacter', 'label' => 'Contacter', 'url' => 'mailto:accueil@example.org'])
        ->and($complet->prochaineAction()['principale'])->toMatchArray(['type' => 'prevenir', 'label' => 'Me prévenir quand disponible'])
        ->and($indispo->prochaineAction()['principale']['type'])->toBe('prevenir')
        ->and($indispo->prochaineAction()['alternative'])->toMatchArray(['label' => 'Voir une alternative', 'url' => 'https://alternative.example.org'])
        ->and($reservable->prochaineAction()['alternative'])->toBeNull();
});

test('la fiche publique affiche l\'état, la prochaine action et des liens externes protégés', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin(['name' => 'Transports Nova']))->bookable('https://rdv.example.org')->create(['title' => 'Abonnement de bus']);

    $this->get(route('catalogue.partners.show', $offre))
        ->assertOk()
        ->assertSee('Abonnement de bus')
        ->assertSee('Transports Nova')
        ->assertSee('Disponible')
        ->assertSee('Réserver')
        ->assertSee('https://rdv.example.org', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

test('la fiche indique « Indisponible jusqu\'au » en toutes lettres et propose l\'alternative', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->unavailable('2026-10-12')->withAlternative()->create();

    $this->get(route('catalogue.partners.show', $offre))
        ->assertOk()
        ->assertSee('Indisponible jusqu\'au lundi 12 octobre 2026')
        ->assertSee('Me prévenir quand disponible')
        ->assertSee('Voir une alternative');
});

test('fiche non publiée, partenaire non publié ou inexistante : 404', function () {
    $brouillon = PartnerOffering::factory()->for(partenaireMatin())->unpublished()->create();
    $partenaireCache = PartnerOffering::factory()->for(Partner::factory()->unpublished())->create();

    $this->get(route('catalogue.partners.show', $brouillon))->assertNotFound();
    $this->get(route('catalogue.partners.show', $partenaireCache))->assertNotFound();
    $this->get('/catalogue/partenaires/service-inexistant')->assertNotFound();
    $this->actingAs(User::factory()->citoyen()->create())->get(route('catalogue.partners.show', $brouillon))->assertNotFound();
});

test('un titre contenant <script> est échappé dans le catalogue et sur la fiche', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->create(['title' => '<script>alert(1)</script>']);

    $this->get(route('catalogue.partners.show', $offre))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('services.index'))
        ->assertDontSee('<script>alert(1)</script>', false);
});

// --- Espace partenaire : accès et droits ----------------------------------------------------------------------------

test('un invité est envoyé vers la connexion ; un citoyen et un agent reçoivent 403', function () {
    $this->get(route('partner.offerings.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->citoyen()->create())->get(route('partner.offerings.index'))->assertForbidden();
    $this->actingAs(User::factory()->agent()->create())->get(route('partner.offerings.index'))->assertForbidden();
});

test('un partenaire ne voit que ses propres services', function () {
    $a = partenaireMatin();
    $b = partenaireMatin();
    $sien = PartnerOffering::factory()->for($a)->create(['title' => 'Service de A']);
    PartnerOffering::factory()->for($b)->create(['title' => 'Service de B']);

    $this->actingAs(User::factory()->partenaireDe($a)->create())
        ->get(route('partner.offerings.index'))
        ->assertOk()
        ->assertSee('Service de A')
        ->assertDontSee('Service de B');

    expect(PartnerOffering::query()->gerablesPar(User::factory()->partenaireDe($a)->create())->pluck('id')->all())->toBe([$sien->id]);
});

test('le partenaire peut créer un service : partner_id envoyé dans la requête est ignoré', function () {
    $a = partenaireMatin();
    $b = partenaireMatin();
    $compteA = User::factory()->partenaireDe($a)->create();

    Livewire::actingAs($compteA)
        ->test('pages::partner-offerings.form')
        ->set('title', 'Atelier CV')
        ->set('description', 'Aide à la rédaction du CV.')
        ->set('status', PartnerOffering::STATUS_AVAILABLE)
        ->set('partner_id', (string) $b->id)
        ->call('save')
        ->assertHasNoErrors();

    $offre = PartnerOffering::query()->where('title', 'Atelier CV')->sole();

    expect($offre->partner_id)->toBe($a->id)
        ->and($offre->created_by)->toBe($compteA->id)
        ->and($offre->slug)->toBe('atelier-cv');
});

test('le partenaire modifie son service sans pouvoir le déplacer vers un autre partenaire', function () {
    $a = partenaireMatin();
    $b = partenaireMatin();
    $offre = PartnerOffering::factory()->for($a)->create();

    Livewire::actingAs(User::factory()->partenaireDe($a)->create())
        ->test('pages::partner-offerings.form', ['partnerOffering' => $offre])
        ->set('title', 'Nouveau titre')
        ->set('partner_id', (string) $b->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($offre->fresh())->title->toBe('Nouveau titre')->partner_id->toBe($a->id);
});

test('partenaire A → 403 en modifiant, changeant l\'état ou supprimant un service du partenaire B', function () {
    $b = partenaireMatin();
    $offreB = PartnerOffering::factory()->for($b)->create(['title' => 'Service de B']);
    $compteA = User::factory()->partenaireDe(partenaireMatin())->create();

    $this->actingAs($compteA)->get(route('partner.offerings.edit', $offreB))->assertForbidden();

    Livewire::actingAs($compteA)
        ->test('pages::partner-offerings.index')
        ->call('changeStatus', $offreB->id, PartnerOffering::STATUS_FULL)
        ->assertForbidden();

    Livewire::actingAs($compteA)
        ->test('pages::partner-offerings.index')
        ->call('delete', $offreB->id)
        ->assertForbidden();

    expect($offreB->fresh())->not->toBeNull()->status->toBe(PartnerOffering::STATUS_AVAILABLE);
});

test('un compte partenaire sans partenaire rattaché n\'accède pas à l\'espace', function () {
    $orphelin = User::factory()->create(['role_id' => Role::idFor(Role::PARTENAIRE), 'partner_id' => null]);

    $this->actingAs($orphelin)->get(route('partner.offerings.index'))->assertForbidden();
});

test('changement d\'état rapide : Complet, puis Indisponible jusqu\'à une date', function () {
    $this->travelTo(mardiNovaTerra('10:00'));
    $a = partenaireMatin();
    $offre = PartnerOffering::factory()->for($a)->create();

    $composant = Livewire::actingAs(User::factory()->partenaireDe($a)->create())->test('pages::partner-offerings.index');

    $composant->call('changeStatus', $offre->id, PartnerOffering::STATUS_FULL)->assertHasNoErrors();
    expect($offre->fresh()->status)->toBe(PartnerOffering::STATUS_FULL);

    $composant->set("jusquAu.{$offre->id}", '2026-10-12')
        ->call('changeStatus', $offre->id, PartnerOffering::STATUS_UNAVAILABLE)
        ->assertHasNoErrors();

    expect($offre->fresh())
        ->status->toBe(PartnerOffering::STATUS_UNAVAILABLE)
        ->and($offre->fresh()->unavailable_until->toDateString())->toBe('2026-10-12');

    $composant->call('changeStatus', $offre->id, 'supprime')->assertHasErrors(['status']);
});

test('l\'admin gère les services de tous les partenaires et choisit le partenaire', function () {
    $a = partenaireMatin();
    $b = partenaireMatin();
    $offreA = PartnerOffering::factory()->for($a)->create(['title' => 'Service de A']);
    PartnerOffering::factory()->for($b)->create(['title' => 'Service de B']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('partner.offerings.index'))
        ->assertOk()
        ->assertSee('Service de A')
        ->assertSee('Service de B');

    Livewire::actingAs($admin)
        ->test('pages::partner-offerings.form')
        ->set('partner_id', (string) $b->id)
        ->set('title', 'Service ajouté par l\'admin')
        ->set('description', 'Description.')
        ->call('save')
        ->assertHasNoErrors();

    expect(PartnerOffering::query()->where('title', 'Service ajouté par l\'admin')->sole()->partner_id)->toBe($b->id);

    Livewire::actingAs($admin)
        ->test('pages::partner-offerings.index')
        ->call('changeStatus', $offreA->id, PartnerOffering::STATUS_FULL)
        ->call('delete', $offreA->id);

    expect(PartnerOffering::find($offreA->id))->toBeNull();
});

test('les URLs non https sont refusées à la validation', function (string $url) {
    $a = partenaireMatin();

    Livewire::actingAs(User::factory()->partenaireDe($a)->create())
        ->test('pages::partner-offerings.form')
        ->set('title', 'Atelier')
        ->set('description', 'Description.')
        ->set('booking_url', $url)
        ->set('alternative_url', $url)
        ->call('save')
        ->assertHasErrors(['booking_url', 'alternative_url']);

    expect(PartnerOffering::query()->count())->toBe(0);
})->with([
    'http en clair' => 'http://rdv.example.org',
    'javascript' => 'javascript:alert(1)',
    'pas une URL' => 'rdv.example.org',
]);

// --- Rôle partenaire : jamais attribuable par l'utilisateur ---------------------------------------------------------

test('le rôle partenaire n\'est pas attribuable à l\'inscription', function () {
    $partner = partenaireMatin();

    $this->post(route('register.store'), jetonAntiRobot('inscription') + [
        'name' => 'Faux Partenaire',
        'email' => 'faux.partenaire@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role_id' => Role::idFor(Role::PARTENAIRE),
        'partner_id' => $partner->id,
    ]);

    $compte = User::query()->where('email', 'faux.partenaire@example.com')->sole();

    expect($compte->isCitoyen())->toBeTrue()
        ->and($compte->partner_id)->toBeNull();
});

test('le rôle partenaire n\'est pas attribuable depuis le profil', function () {
    $citoyen = User::factory()->citoyen()->create();

    $composant = Livewire::actingAs($citoyen)->test('pages::settings.profile');

    expect(fn () => $composant->set('partner_id', partenaireMatin()->id))->toThrow(Exception::class);

    expect($citoyen->fresh())->isPartenaire()->toBeFalse()->partner_id->toBeNull();
});

test('seul l\'admin rattache un compte à un partenaire (rôle + partner_id) ; un agent reçoit 403', function () {
    $partner = partenaireMatin();
    $citoyen = User::factory()->citoyen()->create(['email' => 'futur.partenaire@example.com']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::partners.form', ['partner' => $partner])
        ->set('compteEmail', 'futur.partenaire@example.com')
        ->call('rattacherCompte')
        ->assertForbidden();

    expect($citoyen->fresh()->isPartenaire())->toBeFalse();

    Livewire::actingAs(User::factory()->admin()->create())
        ->test('pages::partners.form', ['partner' => $partner])
        ->set('compteEmail', 'futur.partenaire@example.com')
        ->call('rattacherCompte')
        ->assertHasNoErrors();

    expect($citoyen->fresh())->isPartenaire()->toBeTrue()->partner_id->toBe($partner->id);
});

// --- « Me prévenir quand disponible » -------------------------------------------------------------------------------

test('l\'abonnement est créé une seule fois, puis annulable', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->full()->create();
    $citoyen = User::factory()->citoyen()->create();

    $composant = Livewire::actingAs($citoyen)->test('pages::partner-offerings.show', ['partnerOffering' => $offre]);
    $composant->call('subscribe')->call('subscribe')->assertSee('Vous serez prévenu(e)');

    expect(AvailabilitySubscription::query()->where('user_id', $citoyen->id)->count())->toBe(1);

    $abonnement = AvailabilitySubscription::query()->where('user_id', $citoyen->id)->sole();
    $composant->call('unsubscribe', $abonnement->id)->assertSee('Me prévenir quand disponible');

    expect(AvailabilitySubscription::query()->count())->toBe(0);
});

test('annuler l\'abonnement d\'un autre habitant → 403', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->full()->create();
    $autre = User::factory()->citoyen()->create();
    $abonnementAutre = AvailabilitySubscription::abonner($autre, $offre);

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::partner-offerings.show', ['partnerOffering' => $offre])
        ->call('unsubscribe', $abonnementAutre->id)
        ->assertForbidden();

    expect(AvailabilitySubscription::find($abonnementAutre->id))->not->toBeNull();
});

test('on ne s\'abonne pas à un service déjà disponible', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->create();

    Livewire::actingAs(User::factory()->citoyen()->create())
        ->test('pages::partner-offerings.show', ['partnerOffering' => $offre])
        ->call('subscribe')
        ->assertForbidden();
});

test('un invité qui veut être prévenu passe par la connexion puis revient sur la fiche', function () {
    $offre = PartnerOffering::factory()->for(partenaireMatin())->full()->create();

    Livewire::test('pages::partner-offerings.show', ['partnerOffering' => $offre])
        ->call('subscribe')
        ->assertRedirect(route('catalogue.partners.notify', $offre));

    $this->get(route('catalogue.partners.notify', $offre))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('catalogue.partners.notify', $offre))
        ->assertRedirect(route('catalogue.partners.show', $offre));

    expect(AvailabilitySubscription::query()->count())->toBe(0);
});

test('au retour à Disponible, les abonnés reçoivent l\'avis une seule fois', function () {
    $a = partenaireMatin();
    $offre = PartnerOffering::factory()->for($a)->full()->create(['title' => 'Distribution de colis']);
    $abonne = User::factory()->citoyen()->create();
    AvailabilitySubscription::abonner($abonne, $offre);

    $composant = Livewire::actingAs(User::factory()->partenaireDe($a)->create())->test('pages::partner-offerings.index');

    $composant->call('changeStatus', $offre->id, PartnerOffering::STATUS_AVAILABLE);
    $composant->call('changeStatus', $offre->id, PartnerOffering::STATUS_FULL);
    $composant->call('changeStatus', $offre->id, PartnerOffering::STATUS_AVAILABLE);

    // Cloche (canal database) : un seul avis, malgré deux retours à Disponible.
    $avis = $abonne->fresh()->notifications;

    expect($avis)->toHaveCount(1)
        ->and($avis->first()->type)->toBe(Avis::class)
        ->and($avis->first()->data['sujet'])->toContain('Distribution de colis')
        ->and($avis->first()->data['url'])->toBe(route('catalogue.partners.show', $offre))
        ->and(AvailabilitySubscription::query()->sole()->notified_at)->not->toBeNull();
});
