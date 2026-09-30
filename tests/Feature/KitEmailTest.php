<?php

use App\Mail\TestMail;
use App\Models\User;
use App\Notifications\Avis;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => app()->setLocale('fr'));

test('la réinitialisation du mot de passe envoie une notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('l\'e-mail de réinitialisation est en français', function () {
    $user = User::factory()->create();

    $mail = (new ResetPassword('jeton-de-test'))->toMail($user);
    $rendu = $mail->render()->toHtml();

    expect($mail->subject)->toBe('Réinitialisation de votre mot de passe')
        ->and($rendu)->toContain('Bonjour !')
        ->and($rendu)->toContain('Réinitialiser le mot de passe')
        ->and($rendu)->toContain('Cordialement,')
        ->and($rendu)->toContain('Tous droits réservés.')
        ->and($rendu)->not->toContain('Regards,')
        ->and($rendu)->not->toContain('Hello!');
});

test('app:test-mail envoie un e-mail de test', function () {
    Mail::fake();

    $this->artisan('app:test-mail', ['email' => 'jury@example.com'])->assertSuccessful();

    Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('jury@example.com'));
});

test('app:test-mail refuse une adresse invalide', function () {
    Mail::fake();

    $this->artisan('app:test-mail', ['email' => 'pas-une-adresse'])->assertFailed();

    Mail::assertNothingSent();
});

test('la notification Avis utilise les canaux database et mail', function () {
    $user = User::factory()->create();
    $avis = new Avis('Sujet', ['Ligne 1'], 'Voir', 'https://example.com/x');

    expect($avis->via($user))->toBe(['database', 'mail'])
        ->and($avis->toMail($user)->actionUrl)->toBe('https://example.com/x')
        ->and($avis->toArray($user)['sujet'])->toBe('Sujet');
});

test('la notification Avis est enregistrée pour l\'utilisateur', function () {
    $user = User::factory()->create();

    $user->notify(new Avis('Bienvenue', ['Merci de votre inscription.']));

    expect($user->unreadNotifications()->count())->toBe(1);
});

test('la cloche affiche le nombre de non lues et les notifications de l\'utilisateur', function () {
    $user = User::factory()->create();
    $user->notify(new Avis('Premier avis', ['Bonjour']));
    $user->notify(new Avis('Second avis'));

    Livewire::actingAs($user)
        ->test('cloche-notifications')
        ->assertSee('Premier avis')
        ->assertSee('Second avis')
        ->assertSeeHtml('data-test="cloche-compteur"');
});

test('tout marquer comme lu ne touche que ses propres notifications', function () {
    $user = User::factory()->create();
    $autre = User::factory()->create();
    $user->notify(new Avis('Pour moi'));
    $autre->notify(new Avis('Pour un autre'));

    Livewire::actingAs($user)
        ->test('cloche-notifications')
        ->call('markAllAsRead');

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($autre->unreadNotifications()->count())->toBe(1);
});

test('un utilisateur ne voit pas les notifications d\'un autre', function () {
    $user = User::factory()->create();
    $autre = User::factory()->create();
    $autre->notify(new Avis('Secret de l\'autre'));

    Livewire::actingAs($user)
        ->test('cloche-notifications')
        ->assertDontSee('Secret de l\'autre');
});

test('un utilisateur ne peut pas marquer lue la notification d\'un autre', function () {
    $user = User::factory()->create();
    $autre = User::factory()->create();
    $autre->notify(new Avis('Secret de l\'autre'));
    $idAutre = $autre->notifications()->first()->id;

    $cloche = Livewire::actingAs($user)->test('cloche-notifications');

    expect(fn () => $cloche->call('markAsRead', $idAutre))->toThrow(ModelNotFoundException::class);

    expect($autre->unreadNotifications()->count())->toBe(1);
});
