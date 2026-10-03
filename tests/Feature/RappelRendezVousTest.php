<?php

use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Notifications\RappelRendezVous;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
| F40 — Rappel automatique avant le rendez-vous. Horloge figée : lundi 5 octobre 2026, 08:00 UTC
| (11 h 00 à Nova Terra, UTC+3). Rappel 24 h avant (config rendez_vous.rappel_heures_avant).
*/

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-05 08:00:00', 'UTC'));

    $this->etatCivil = Service::factory()->create([
        'nom' => 'État civil',
        'lieu_rendez_vous' => 'Hôtel de ville, guichet 2',
        'duree_rendez_vous' => 30,
        'pieces_a_fournir' => "Carte nationale d’identité\nLivret de famille",
    ]);
});

function rendezVousA(Service $service, string $debutUtc, ?User $user = null, string $statut = 'confirme'): RendezVous
{
    $creneau = CreneauRendezVous::factory()->a($debutUtc)->for($service)->create();

    return RendezVous::factory()->for($user ?? User::factory()->citoyen()->create())->create([
        'creneau_id' => $creneau->id,
        'statut' => $statut,
        'motif' => 'Copie d’acte de naissance',
    ]);
}

test('un rendez-vous dans 23 h reçoit son rappel et reminder_sent_at est renseigné', function () {
    Notification::fake();
    $rendezVous = rendezVousA($this->etatCivil, '2026-10-06 07:00:00');

    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('1 rappel(s) envoyé(s), 0 erreur(s)')
        ->assertSuccessful();

    Notification::assertSentTo($rendezVous->user, RappelRendezVous::class, function (RappelRendezVous $n, array $canaux) use ($rendezVous) {
        return $n->rendezVous->is($rendezVous) && $canaux === ['database', 'mail'];
    });
    expect($rendezVous->fresh()->reminder_sent_at)->not->toBeNull();
});

test('un rendez-vous dans 3 jours n’est rappelé qu’à J-1', function () {
    Notification::fake();
    $rendezVous = rendezVousA($this->etatCivil, '2026-10-08 07:00:00');

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo(now()->parse('2026-10-07 08:00:00', 'UTC'));
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertSentToTimes($rendezVous->user, RappelRendezVous::class, 1);
});

test('lancée deux fois, la commande n’envoie qu’un seul rappel', function () {
    Notification::fake();
    $rendezVous = rendezVousA($this->etatCivil, '2026-10-06 07:00:00');

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')
        ->expectsOutputToContain('0 rappel(s) envoyé(s)')
        ->assertSuccessful();

    Notification::assertSentToTimes($rendezVous->user, RappelRendezVous::class, 1);
});

test('un rendez-vous annulé, passé ou d’un compte désactivé ne reçoit aucun rappel', function () {
    Notification::fake();
    rendezVousA($this->etatCivil, '2026-10-06 07:00:00', statut: 'annule');
    rendezVousA($this->etatCivil, '2026-10-05 07:00:00');
    rendezVousA($this->etatCivil, '2026-10-06 07:30:00', User::factory()->citoyen()->deactivated()->create());

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(RendezVous::query()->whereNotNull('reminder_sent_at')->count())->toBe(0);
});

test('le rappel reprend service, date complète, heures, fuseau, lieu et pièces à apporter', function () {
    $rendezVous = rendezVousA($this->etatCivil, '2026-10-06 06:30:00');
    $rendezVous->update(['motif' => '<script>alert(1)</script>']);
    $notification = new RappelRendezVous($rendezVous->fresh());
    $notification->id = '9b1f1c4e-0000-4000-8000-000000000000';

    expect($notification->sujet())
        ->toBe('Rappel : rendez-vous État civil demain, mardi 6 octobre 2026 de 09 h 30 à 10 h 00 (heure de Nova Terra).');

    $mail = $notification->toMail($rendezVous->user);
    $html = (string) $mail->render();

    expect($mail->introLines)->toContain(
        'Service : État civil',
        'Date : Mardi 6 octobre 2026',
        'Horaire : 09 h 30 à 10 h 00 (heure de Nova Terra)',
        'Lieu : Hôtel de ville, guichet 2',
        '• Carte nationale d’identité',
        '• Livret de famille',
    )
        ->and($mail->actionUrl)->toBe(route('appointments.show', $rendezVous))
        ->and(implode(' ', $mail->outroLines))->toContain(route('appointments.show', $rendezVous).'#annuler')
        ->and($html)->toContain('&lt;script&gt;')->not->toContain('<script>alert(1)</script>');

    $donnees = $notification->toArray($rendezVous->user);
    expect($donnees['sujet'])->toContain('mardi 6 octobre 2026 de 09 h 30 à 10 h 00 (heure de Nova Terra)')
        ->and($donnees['lignes'])->toBe(['Lieu : Hôtel de ville, guichet 2', 'Pièces à apporter : Carte nationale d’identité, Livret de famille.'])
        ->and($donnees['rendez_vous_id'])->toBe($rendezVous->id);
});

test('la commande est planifiée toutes les 5 minutes sans chevauchement', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'appointments:send-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

test('reminder_sent_at envoyé en masse ou à la réservation est ignoré', function () {
    expect((new RendezVous(['motif' => 'Motif', 'reminder_sent_at' => now()]))->getAttributes())
        ->toBe(['statut' => 'confirme', 'motif' => 'Motif']);

    $citoyen = User::factory()->citoyen()->create();
    $creneau = CreneauRendezVous::factory()->a('2026-10-06 06:30:00')->for($this->etatCivil)->create();

    Livewire::actingAs($citoyen)
        ->test('pages::rendez-vous.form')
        ->call('choisirService', $this->etatCivil->slug)
        ->call('choisirCreneau', $creneau->id)
        ->call('confirmer')
        ->assertHasNoErrors();

    expect(RendezVous::sole()->reminder_sent_at)->toBeNull();
});

test('un citoyen reçoit un 403 sur la fiche et l’annulation du rendez-vous d’un autre (lien du rappel)', function () {
    $rendezVousDeB = rendezVousA($this->etatCivil, '2026-10-06 07:00:00');
    $citoyenA = User::factory()->citoyen()->create();

    $this->actingAs($citoyenA)->get(route('appointments.show', $rendezVousDeB).'#annuler')->assertForbidden();

    Livewire::actingAs($citoyenA)
        ->test('pages::rendez-vous.show', ['rendezVous' => $rendezVousDeB])
        ->assertForbidden();

    expect($rendezVousDeB->fresh()->statut)->toBe('confirme');
});

test('la notification de rappel de B est interdite à A, et ouvre la fiche pour B', function () {
    $citoyenB = User::factory()->citoyen()->create();
    $rendezVous = rendezVousA($this->etatCivil, '2026-10-06 07:00:00', $citoyenB);
    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $notification = $citoyenB->notifications()->sole();
    $citoyenA = User::factory()->citoyen()->create();

    $this->actingAs($citoyenA)->post(route('notifications.read', $notification->id))->assertForbidden();
    $this->actingAs($citoyenA)->get(route('notifications.open', $notification->id))->assertForbidden();
    expect($notification->fresh()->read_at)->toBeNull();

    $this->actingAs($citoyenB)->get(route('notifications.open', $notification->id))
        ->assertRedirect(route('appointments.show', $rendezVous));
    expect($notification->fresh()->read_at)->not->toBeNull();
});
