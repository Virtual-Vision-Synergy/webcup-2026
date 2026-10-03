<?php

use App\Models\Demarche;
use App\Models\User;
use App\Notifications\DemarcheStatutModifie;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * D11 — Suivi de mes demandes avec état et étapes datées.
 * F49 — Notification quand ma demande change d'état.
 */
test('un changement d\'état par un agent ajoute une étape datée avec son message', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);

    Livewire::actingAs(User::factory()->agent()->create())
        ->test('pages::demarches.show', ['demarche' => $demarche])
        ->set('commentaire', 'Votre carte est prête.')
        ->call('changerStatut', 'traitee')
        ->assertHasNoErrors();

    $etape = $demarche->etapes()->sole();

    expect($etape->statut)->toBe('traitee')
        ->and($etape->commentaire)->toBe('Votre carte est prête.')
        ->and($etape->created_at)->not->toBeNull();
});

test('l\'auteur voit l\'état actuel, les étapes datées et quoi faire ensuite', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'deposee', 'titre' => 'Certificat de résidence']);
    $demarche->changerStatut('refusee', 'Il manque un justificatif de domicile.');

    $this->actingAs($demarche->user)
        ->get(route('demarches.show', $demarche))
        ->assertOk()
        ->assertSee('Décision : Refusée')
        ->assertSee('Il manque un justificatif de domicile.')
        ->assertSee(Demarche::conseilStatut('refusee'));

    $this->actingAs($demarche->user)
        ->get(route('demarches.historique'))
        ->assertOk()
        ->assertSee('Certificat de résidence')
        ->assertSee('Refusée le');
});

test('la page Mes demandes ne montre que les demandes de l\'utilisateur connecté', function () {
    $moi = User::factory()->create();
    Demarche::factory()->for($moi)->create(['titre' => 'Ma demande à moi']);
    Demarche::factory()->create(['titre' => 'Demande du voisin']);

    $this->actingAs($moi)
        ->get(route('demarches.historique'))
        ->assertOk()
        ->assertSee('Ma demande à moi')
        ->assertDontSee('Demande du voisin');
});

test('un autre habitant ne peut pas consulter le suivi d\'une demande (403)', function () {
    $demarche = Demarche::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('demarches.show', $demarche))
        ->assertForbidden();
});

test('un changement d\'état notifie le seul auteur de la demande', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);
    $autre = User::factory()->create();
    $agent = User::factory()->agent()->create();

    Livewire::actingAs($agent)
        ->test('pages::demarches.show', ['demarche' => $demarche])
        ->call('changerStatut', 'en_cours');

    Notification::assertSentTo($demarche->user, DemarcheStatutModifie::class, function (DemarcheStatutModifie $notification, array $canaux) use ($demarche): bool {
        $donnees = $notification->toArray($demarche->user);

        return $canaux === ['database', 'mail']
            && $donnees['demarche_id'] === $demarche->id
            && $donnees['statut'] === 'en_cours'
            && $donnees['url'] === route('demarches.show', $demarche)
            && in_array(Demarche::conseilStatut('en_cours'), $donnees['lignes'], true);
    });
    Notification::assertNotSentTo([$autre, $agent], DemarcheStatutModifie::class);
});

test('aucune notification ni étape si l\'état ne change pas', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);

    $demarche->changerStatut('deposee');

    Notification::assertNothingSent();
    expect($demarche->etapes()->count())->toBe(0);
});

test('l\'auteur ne peut pas changer l\'état ni déclencher de notification', function () {
    Notification::fake();
    $demarche = Demarche::factory()->create(['statut' => 'deposee']);

    Livewire::actingAs($demarche->user)
        ->test('pages::demarches.show', ['demarche' => $demarche])
        ->call('changerStatut', 'traitee')
        ->assertForbidden();

    Notification::assertNothingSent();
    expect($demarche->etapes()->count())->toBe(0);
});
