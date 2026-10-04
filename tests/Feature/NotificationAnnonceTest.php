<?php

use App\Models\Annonce;
use App\Models\Quartier;
use App\Models\User;
use App\Notifications\ImportantAnnouncementPublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * F30 — Notifications des annonces importantes (cloche, compteur, marquer comme lue, e-mail).
 */
function publierAnnonce(User $agent, array $valeurs = []): mixed
{
    $valeurs += [
        'titre' => 'Montée des eaux',
        'contenu' => 'Le niveau de l’eau monte rapidement.',
        'consignes' => 'Éloignez-vous des berges.',
        'niveau' => 'danger',
        // Heure de Madagascar (UTC+3) : le test se place le 3 octobre à 10 h UTC = 13 h locale.
        'debut' => '2026-10-03T12:00',
        'fin' => '2026-10-04T12:00',
    ];

    $test = Livewire::actingAs($agent)->test('pages::annonces.form');
    foreach ($valeurs as $champ => $valeur) {
        $test->set($champ, $valeur);
    }

    return $test->call('save')->assertHasNoErrors();
}

function notificationPour(User $user, array $annonce = []): string
{
    $publiee = Annonce::factory()->active()->create($annonce + ['niveau' => 'alerte']);
    $publiee->forceFill(['notified_at' => now()])->save();

    $user->notifyNow(new ImportantAnnouncementPublished($publiee), ['database']);

    return $user->notifications()->latest()->firstOrFail()->id;
}

beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 3, 10, 0));
});

test('publier une annonce importante en cours notifie tous les citoyens, pas les agents ni les admins', function () {
    Notification::fake();
    $citoyens = User::factory(2)->citoyen()->create();
    $agent = User::factory()->agent()->create();
    $admin = User::factory()->admin()->create();

    publierAnnonce($agent, ['niveau' => 'alerte']);

    Notification::assertSentTo($citoyens, ImportantAnnouncementPublished::class);
    Notification::assertNotSentTo([$agent, $admin], ImportantAnnouncementPublished::class);
    expect(Annonce::sole()->notified_at)->not->toBeNull();
});

test('une annonce de niveau information ou vigilance ne notifie personne', function (string $niveau) {
    Notification::fake();
    User::factory()->citoyen()->create();

    publierAnnonce(User::factory()->agent()->create(), ['niveau' => $niveau]);

    Notification::assertNothingSent();
    expect(Annonce::sole()->notified_at)->toBeNull();
})->with(['information', 'vigilance']);

test('une annonce programmée est notifiée à son début, une seule fois', function () {
    Notification::fake();
    $citoyen = User::factory()->citoyen()->create();

    publierAnnonce(User::factory()->agent()->create(), ['debut' => '2026-10-03T15:00']);
    $this->artisan('annonces:notify')->assertSuccessful();

    Notification::assertNothingSent();

    $this->travelTo(now()->setDateTime(2026, 10, 3, 12, 1));
    $this->artisan('annonces:notify')->assertSuccessful();
    $this->artisan('annonces:notify')->assertSuccessful();

    Notification::assertSentToTimes($citoyen, ImportantAnnouncementPublished::class, 1);
});

test('sans planificateur, l’affichage d’une page envoie une annonce arrivée à son début', function () {
    $citoyen = User::factory()->citoyen()->create();
    Annonce::factory()->active()->create(['niveau' => 'danger']);

    $this->actingAs($citoyen)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('aria-label="1 notification non lue"', false);
});

test('une annonce programmée supprimée avant son début n’est jamais notifiée', function () {
    Notification::fake();
    User::factory()->citoyen()->create();
    Annonce::factory()->programmee()->create(['niveau' => 'danger'])->delete();

    $this->travelTo(now()->addDays(1)->addHour());
    $this->artisan('annonces:notify')->assertSuccessful();

    Notification::assertNothingSent();
});

test('une annonce ciblant le quartier sud ne notifie que les citoyens du quartier sud', function () {
    Notification::fake();
    $sud = User::factory()->citoyen()->quartier('sud')->create();
    $nord = User::factory()->citoyen()->quartier('nord')->create();

    publierAnnonce(User::factory()->agent()->create(), ['quartier_id' => (string) Quartier::idPour('sud')]);

    Notification::assertSentTo($sud, ImportantAnnouncementPublished::class);
    Notification::assertNotSentTo($nord, ImportantAnnouncementPublished::class);
});

test('modifier une annonce déjà notifiée ne renvoie pas de notification', function () {
    $citoyen = User::factory()->citoyen()->create();
    $agent = User::factory()->agent()->create();
    publierAnnonce($agent);
    $annonce = Annonce::sole();

    Livewire::actingAs($agent)->test('pages::annonces.form', ['annonce' => $annonce])
        ->set('titre', 'Montée des eaux : mise à jour')
        ->call('save')
        ->assertHasNoErrors();

    expect($citoyen->notifications()->count())->toBe(1);
});

test('notified_at n’est pas assignable depuis les données saisies', function () {
    $annonce = Annonce::factory()->make(['niveau' => 'danger']);
    $annonce->fill(['notified_at' => now()]);

    expect($annonce->notified_at)->toBeNull();
});

test('la notification dit quoi savoir et quoi faire, niveau en texte', function () {
    $citoyen = User::factory()->citoyen()->quartier('sud')->create();
    notificationPour($citoyen, ['titre' => 'Montée des eaux', 'niveau' => 'danger', 'quartier_id' => Quartier::idPour('sud'), 'consignes' => 'Éloignez-vous des berges.']);

    $data = $citoyen->notifications()->sole()->data;

    expect($data['sujet'])->toBe('Danger — Montée des eaux, quartier Sud : consultez les consignes')
        ->and($data['niveau_libelle'])->toBe('Danger')
        ->and($data['lignes'])->toContain('Consigne : Éloignez-vous des berges.');
});

test('la cloche affiche le nombre de non lues avec un libellé accessible', function () {
    $citoyen = User::factory()->citoyen()->create();
    notificationPour($citoyen);
    notificationPour($citoyen);
    notificationPour($citoyen);

    $this->actingAs($citoyen)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('aria-label="3 notifications non lues"', false);
});

test('la cloche affiche 9+ au-delà de neuf notifications et rien à zéro', function () {
    $citoyen = User::factory()->citoyen()->create();

    $this->actingAs($citoyen)->get(route('dashboard'))->assertSee('aria-label="Aucune notification non lue"', false);

    foreach (range(1, 10) as $i) {
        notificationPour($citoyen);
    }

    $this->actingAs($citoyen)->get(route('dashboard'))
        ->assertSee('aria-label="10 notifications non lues"', false)
        ->assertSee('>9+</span>', false);
});

test('marquer une notification comme lue décrémente le compteur', function () {
    $citoyen = User::factory()->citoyen()->create();
    $id = notificationPour($citoyen);
    notificationPour($citoyen);

    $this->actingAs($citoyen)->post(route('notifications.read', $id))->assertRedirect();

    expect($citoyen->unreadNotifications()->count())->toBe(1);
    $this->actingAs($citoyen)->getJson(route('notifications.count'))->assertExactJson(['non_lues' => 1]);
});

test('ouvrir une notification la marque comme lue puis mène à l’annonce', function () {
    $citoyen = User::factory()->citoyen()->create();
    $id = notificationPour($citoyen);
    $annonce = Annonce::sole();

    $this->actingAs($citoyen)->get(route('notifications.open', $id))
        ->assertRedirect(route('alertes.show', $annonce));

    expect($citoyen->unreadNotifications()->count())->toBe(0);
});

test('tout marquer comme lu ne touche que mes notifications', function () {
    $moi = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();
    notificationPour($moi);
    notificationPour($autre);

    $this->actingAs($moi)->post(route('notifications.read-all'))->assertRedirect();

    expect($moi->unreadNotifications()->count())->toBe(0)
        ->and($autre->unreadNotifications()->count())->toBe(1);
});

test('un citoyen reçoit un 403 sur la notification d’un autre', function () {
    $a = User::factory()->citoyen()->create();
    $b = User::factory()->citoyen()->create();
    $idDeB = notificationPour($b);

    $this->actingAs($a)->post(route('notifications.read', $idDeB))->assertForbidden();
    $this->actingAs($a)->get(route('notifications.open', $idDeB))->assertForbidden();

    expect($b->unreadNotifications()->count())->toBe(1);
});

test('la liste montre mes notifications, non lues en premier, sans celles des autres', function () {
    $moi = User::factory()->citoyen()->create();
    $lue = notificationPour($moi, ['titre' => 'Ancienne alerte lue']);
    $moi->notifications()->findOrFail($lue)->markAsRead();
    notificationPour($moi, ['titre' => 'Nouvelle alerte']);
    notificationPour(User::factory()->citoyen()->create(), ['titre' => 'Alerte d’un autre']);

    $this->actingAs($moi)->get(route('notifications.index'))
        ->assertOk()
        // Le bandeau D18 affiche aussi les titres : on vérifie le sujet propre à la notification.
        ->assertSeeInOrder(['Alerte — Nouvelle alerte :', 'Alerte — Ancienne alerte lue :'])
        ->assertDontSee('Alerte — Alerte d’un autre :');
});

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    $this->get(route('notifications.count'))->assertRedirect(route('login'));
});

test('l’e-mail part pour une annonce Danger si la préférence est activée', function () {
    Notification::fake();
    $accepte = User::factory()->citoyen()->create();
    $refuse = User::factory()->citoyen()->create(['notifier_par_email' => false]);

    publierAnnonce(User::factory()->agent()->create());

    Notification::assertSentTo($accepte, ImportantAnnouncementPublished::class, fn ($n, array $canaux) => $canaux === ['database', 'mail']);
    Notification::assertSentTo($refuse, ImportantAnnouncementPublished::class, fn ($n, array $canaux) => $canaux === ['database']);
});

test('pas d’e-mail pour une annonce de niveau Alerte', function () {
    Notification::fake();
    $citoyen = User::factory()->citoyen()->create();

    publierAnnonce(User::factory()->agent()->create(), ['niveau' => 'alerte']);

    Notification::assertSentTo($citoyen, ImportantAnnouncementPublished::class, fn ($n, array $canaux) => $canaux === ['database']);
});

test('un compte désactivé ne reçoit ni notification ni e-mail', function () {
    Notification::fake();
    $desactive = User::factory()->citoyen()->deactivated()->create();

    publierAnnonce(User::factory()->agent()->create());

    Notification::assertNotSentTo($desactive, ImportantAnnouncementPublished::class);
});

test('le citoyen peut désactiver les e-mails dans son profil', function () {
    $citoyen = User::factory()->citoyen()->create();

    Livewire::actingAs($citoyen)->test('pages::settings.profile')
        ->set('notifier_par_email', false)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($citoyen->fresh()->notifier_par_email)->toBeFalse();
});
