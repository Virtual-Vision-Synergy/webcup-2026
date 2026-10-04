<?php

use App\Models\User;
use App\Support\DependancesExternes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Serveur d'e-mails en panne : tout envoi échoue comme le ferait un sendmail ou un SMTP injoignable.
 */
function serveurEmailEnPanne(): void
{
    Mail::extend('panne', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Serveur d’e-mails injoignable');
        }

        public function __toString(): string
        {
            return 'panne://';
        }
    });

    config(['mail.mailers.panne' => ['transport' => 'panne'], 'mail.default' => 'panne']);
}

test('la page « Vous êtes hors ligne » est publique et donne les numéros d’urgence', function () {
    $this->get(route('hors-ligne'))
        ->assertOk()
        ->assertSee('Vous êtes hors ligne')
        ->assertSee('tel:117', false)
        ->assertSee('Pages consultables sans réseau');
});

test('une panne du serveur d’e-mails n’empêche pas la page de s’afficher', function () {
    serveurEmailEnPanne();
    $user = User::factory()->create();

    $this->post(route('login-link.store'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(DependancesExternes::enPanne(DependancesExternes::EMAIL))->toBeTrue();
});

test('une notification par e-mail qui échoue est journalisée sans erreur pour l’habitant', function () {
    serveurEmailEnPanne();
    $user = User::factory()->create();

    $user->notifyNow(new class extends Notification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->line('Votre démarche a avancé.');
        }
    });

    expect(DependancesExternes::enPanne(DependancesExternes::EMAIL))->toBeTrue();
});

test('pendant une panne d’e-mails, un bandeau clair l’explique sur les pages', function () {
    DependancesExternes::signalerPanne(DependancesExternes::EMAIL);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="bandeau-panne-email"', false)
        ->assertSee('Envoi des e-mails momentanément interrompu');

    DependancesExternes::retablie(DependancesExternes::EMAIL);

    $this->get(route('dashboard'))->assertDontSee('data-test="bandeau-panne-email"', false);
});

test('le bandeau d’état du réseau est présent et la session est identifiée sans exposer l’identifiant', function () {
    $user = User::factory()->create();

    $reponse = $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="etat-reseau"', false)
        ->assertSee('name="tn-session"', false);

    preg_match('/name="tn-session" content="([^"]+)"/', $reponse->getContent(), $session);

    expect($session[1])->toHaveLength(16)->not->toBe((string) $user->id);
});

test('les formulaires essentiels sont gardés en brouillon hors ligne', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('demarches.create'))->assertOk()->assertSee('data-brouillon="demarche"', false);
    $this->get(route('messages.create'))->assertOk()->assertSee('data-brouillon="contact"', false);
    $this->get(route('signalements.create'))->assertOk()->assertSee('data-brouillon="signalement"', false);
});
