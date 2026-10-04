<?php

use App\Models\Demarche;
use App\Models\Signalement;
use App\Models\Soutien;
use App\Models\User;
use App\Notifications\StatutDemandeChange;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * F49 — Le citoyen est prévenu (cloche + e-mail) quand l'état de sa demande change.
 */
function avisDe(User $user): mixed
{
    return $user->notifications()->where('type', StatutDemandeChange::class)->latest()->firstOrFail();
}

test('un agent qui passe une démarche en cours ne prévient que son propriétaire', function () {
    Notification::fake();
    $proprietaire = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();
    $admin = User::factory()->admin()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);
    $agent = agentDeTousLesServices();

    Livewire::actingAs($agent)
        ->test('pages::agent.demandes')
        ->call('changerStatut', $demarche->id, 'en_cours')
        ->assertHasNoErrors();

    Notification::assertSentTo($proprietaire, StatutDemandeChange::class);
    Notification::assertNotSentTo([$agent, $autre, $admin], StatutDemandeChange::class);
});

test('un agent qui passe un signalement en cours ne prévient ni les soutiens ni le personnel', function () {
    Notification::fake();
    $proprietaire = User::factory()->citoyen()->create();
    $agent = User::factory()->agent()->create();
    $admin = User::factory()->admin()->create();
    $signalement = Signalement::factory()->for($proprietaire)->create(['statut' => 'nouveau']);
    $soutien = Soutien::factory()->for($signalement)->create();

    Livewire::actingAs($agent)
        ->test('pages::signalements.show', ['signalement' => $signalement])
        ->call('changerStatut', 'en_cours')
        ->assertHasNoErrors();

    Notification::assertSentTo($proprietaire, StatutDemandeChange::class);
    Notification::assertNotSentTo([$agent, $admin, $soutien->user], StatutDemandeChange::class);
});

test('l’avis passe par la cloche et l’e-mail et dit quoi faire ensuite', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['titre' => 'Acte de naissance', 'statut' => 'deposee']);

    $avis = new StatutDemandeChange($demarche, 'deposee', 'en_cours');
    $data = $avis->toArray($proprietaire);

    expect($avis->via($proprietaire))->toBe(['database', 'mail'])
        ->and($data['sujet'])->toBe('Votre demande « Acte de naissance » est maintenant : En cours')
        ->and($data['lignes'])->toBe([
            'État précédent : Déposée',
            'Nouvel état : En cours',
            'Ce que vous devez faire : Rien à faire pour l’instant : un agent étudie votre demande.',
        ])
        ->and($data['libelle'])->toBe('Voir ma demande')
        ->and($data['url'])->toBe(route('demarches.show', $demarche))
        ->and($data['demande_type'])->toBe('demarche')
        ->and($data['demande_id'])->toBe($demarche->id)
        ->and($data['statut_avant'])->toBe('deposee')
        ->and($data['statut_apres'])->toBe('en_cours')
        ->and($data)->not->toHaveKey('description');

    $mail = $avis->toMail($proprietaire);
    expect($mail->subject)->toBe($data['sujet'])
        ->and($mail->actionUrl)->toBe(route('demarches.show', $demarche));
});

test('chaque état a sa phrase « quoi faire », avec une phrase par défaut', function () {
    $signalement = Signalement::factory()->create(['statut' => 'en_cours']);

    $rejete = (new StatutDemandeChange($signalement, 'en_cours', 'rejete'))->lignes;
    $inconnu = StatutDemandeChange::quoiFaire('signalement', 'etat_futur');

    expect(end($rejete))->toBe('Ce que vous devez faire : Consultez la raison dans votre suivi. Écrivez-nous si vous n’êtes pas d’accord.')
        ->and($inconnu)->toBe('Consultez votre suivi pour en savoir plus.');
});

test('le même état ou un état inconnu n’envoie aucun avis', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'en_cours']);

    $demarche->changerStatut('en_cours');

    expect(fn () => $demarche->changerStatut('pirate'))->toThrow(InvalidArgumentException::class);
    Notification::assertNothingSent();
});

test('sans fake l’avis est enregistré et la cloche compte une non lue', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);

    $demarche->changerStatut('traitee');

    expect(avisDe($proprietaire)->data['statut_apres'])->toBe('traitee');
    $this->actingAs($proprietaire)->getJson(route('notifications.count'))->assertExactJson(['non_lues' => 1]);
});

test('un e-mail en échec ne bloque pas l’agent et la cloche garde l’avis', function () {
    app(ChannelManager::class)->extend('mail', fn () => new class
    {
        public function send(object $notifiable, BaseNotification $notification): void
        {
            throw new RuntimeException('sendmail indisponible');
        }
    });
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);

    Livewire::actingAs(agentDeTousLesServices())
        ->test('pages::agent.demandes')
        ->call('changerStatut', $demarche->id, 'refusee')
        ->assertOk()
        ->assertHasNoErrors();

    expect($demarche->fresh()->statut)->toBe('refusee')
        ->and($proprietaire->notifications()->where('type', StatutDemandeChange::class)->count())->toBe(1);
});

test('un citoyen ne peut pas changer l’état de sa propre demande', function () {
    Notification::fake();
    $citoyen = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($citoyen)->create(['statut' => 'deposee']);

    Livewire::actingAs($citoyen)
        ->test('pages::demarches.show', ['demarche' => $demarche])
        ->call('changerStatut', 'traitee')
        ->assertForbidden();

    expect($demarche->fresh()->statut)->toBe('deposee');
    Notification::assertNothingSent();
});

test('un autre citoyen reçoit un 403 sur l’avis du propriétaire', function () {
    $proprietaire = User::factory()->citoyen()->create();
    Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee'])->changerStatut('en_cours');
    $avis = avisDe($proprietaire);
    $autre = User::factory()->citoyen()->create();

    $this->actingAs($autre)->get(route('notifications.open', $avis->id))->assertForbidden();
    $this->actingAs($autre)->post(route('notifications.read', $avis->id))->assertForbidden();

    expect($avis->fresh()->read_at)->toBeNull();
});

test('ouvrir l’avis le marque comme lu et mène à la demande', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $signalement = Signalement::factory()->for($proprietaire)->create(['statut' => 'nouveau']);
    $signalement->changerStatut('resolu');
    $avis = avisDe($proprietaire);

    $this->actingAs($proprietaire)->get(route('notifications.open', $avis->id))
        ->assertRedirect(route('signalements.show', $signalement));

    expect($avis->fresh()->read_at)->not->toBeNull();
});

test('une url modifiée en base n’est jamais suivie', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);
    $demarche->changerStatut('en_cours');
    $avis = avisDe($proprietaire);
    $avis->forceFill(['data' => ['url' => 'https://evil.test'] + $avis->data])->save();

    $this->actingAs($proprietaire)->get(route('notifications.open', $avis->id))
        ->assertRedirect(route('demarches.show', $demarche));

    $avis->forceFill(['data' => ['demande_type' => 'evil'] + $avis->data])->save();

    $this->actingAs($proprietaire)->get(route('notifications.open', $avis->id))
        ->assertRedirect(route('notifications.index'));
});

test('l’avis d’une demande supprimée ramène à la liste des notifications', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);
    $demarche->changerStatut('en_cours');
    $demarche->delete();

    $this->actingAs($proprietaire)->get(route('notifications.open', avisDe($proprietaire)->id))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('status', 'Cette demande n’est plus disponible.');
});

test('le suivi de la demande ne montre que les avis de l’utilisateur connecté', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['titre' => 'Permis de construire', 'statut' => 'deposee']);
    $demarche->changerStatut('en_cours');

    $this->actingAs($proprietaire);
    $this->blade('<x-demande.notifications :demande="$demande" />', ['demande' => $demarche])
        ->assertSee('Votre demande « Permis de construire » est maintenant : En cours')
        ->assertSee('Ce que vous devez faire : Rien à faire pour l’instant : un agent étudie votre demande.')
        ->assertSee('Non lu');

    $this->actingAs(User::factory()->agent()->create());
    $this->blade('<x-demande.notifications :demande="$demande" />', ['demande' => $demarche])
        ->assertSee('Aucun avis pour cette demande pour l’instant.')
        ->assertDontSee('Permis de construire');
});

test('la demande d’autrui reste en 403 même avec le lien de l’avis', function () {
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['statut' => 'deposee']);

    $this->actingAs(User::factory()->citoyen()->create())
        ->get(route('demarches.show', $demarche))
        ->assertForbidden();
});

test('un titre piégé est échappé dans la cloche, la liste et le suivi', function () {
    $piege = '<script>alert(1)</script>';
    $proprietaire = User::factory()->citoyen()->create();
    $demarche = Demarche::factory()->for($proprietaire)->create(['titre' => $piege, 'statut' => 'deposee']);
    $demarche->changerStatut('en_cours');

    $this->actingAs($proprietaire)->get(route('notifications.index'))
        ->assertOk()
        ->assertDontSee($piege, false)
        ->assertSee(e($piege), false);

    Livewire::actingAs($proprietaire)
        ->test('cloche-notifications')
        ->assertDontSee($piege, false)
        ->assertSee(e($piege), false);

    $this->blade('<x-demande.notifications :demande="$demande" />', ['demande' => $demarche])
        ->assertDontSee($piege, false);
});

test('en anglais le sujet et la phrase « quoi faire » sont traduits', function () {
    $proprietaire = User::factory()->citoyen()->create();
    Demarche::factory()->for($proprietaire)->create(['titre' => 'Birth certificate', 'statut' => 'deposee'])->changerStatut('en_cours');

    $this->actingAs($proprietaire)
        ->withSession(['langue' => 'en'])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('is now: In progress')
        ->assertSee('What you need to do: Nothing to do for now: an officer is reviewing your request.');
});
