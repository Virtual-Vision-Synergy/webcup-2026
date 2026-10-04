<?php

use App\Models\AuditLog;
use App\Models\KnownDevice;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use App\Services\DeviceRecognizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * F54 : alerte de connexion depuis un nouvel appareil.
 */
const UA_FIREFOX_ANDROID = 'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0';
const UA_FIREFOX_ANDROID_RECENT = 'Mozilla/5.0 (Android 14; Mobile; rv:133.0) Gecko/133.0 Firefox/133.0';
const UA_CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';
const UA_SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1';

/**
 * Connexion par le formulaire avec un navigateur donné, puis déconnexion (sans passer par la route logout)
 * pour pouvoir enchaîner plusieurs connexions dans un même test.
 */
function connexion(User $user, string $ua, string $ip = '41.188.12.34', ?string $cookie = null, string $password = 'password'): TestResponse
{
    $requete = test()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $ua);

    if ($cookie !== null) {
        $requete = $requete->withCookie(DeviceRecognizer::COOKIE, $cookie);
    }

    $response = $requete->post(route('login.store'), jetonAntiRobot('connexion') + ['email' => $user->email, 'password' => $password]);

    Auth::guard('web')->logout();
    test()->flushSession();

    return $response;
}

function cookieAppareil(TestResponse $response): ?string
{
    return $response->getCookie(DeviceRecognizer::COOKIE)?->getValue();
}

beforeEach(function () {
    Notification::fake();
});

test('première connexion du compte : appareil enregistré, aucune alerte', function () {
    $user = User::factory()->citoyen()->create();

    $response = connexion($user, UA_FIREFOX_ANDROID);

    expect($user->knownDevices()->count())->toBe(1)
        ->and(cookieAppareil($response))->not->toBeNull();

    $device = $user->knownDevices()->first();
    expect($device->browser)->toBe('Firefox')
        ->and($device->os)->toBe('Android')
        ->and($device->device_type)->toBe('mobile')
        ->and($device->ip_approx)->toBe('41.188.12.x')
        ->and($device->device_token_hash)->toBe(hash('sha256', cookieAppareil($response)));

    Notification::assertNothingSent();
});

test('même appareil (même cookie) : aucune alerte et last_seen_at mis à jour', function () {
    $user = User::factory()->citoyen()->create();
    $cookie = cookieAppareil(connexion($user, UA_FIREFOX_ANDROID));

    $this->travel(2)->hours();
    connexion($user, UA_FIREFOX_ANDROID, '102.16.44.12', $cookie);

    expect($user->knownDevices()->count())->toBe(1)
        ->and($user->knownDevices()->first()->last_seen_at->greaterThan(now()->subMinute()))->toBeTrue();
    Notification::assertNothingSent();
});

test('même navigateur, même système et même IP approximative sans cookie : aucune alerte', function () {
    $user = User::factory()->citoyen()->create();
    connexion($user, UA_FIREFOX_ANDROID, '41.188.12.34');

    connexion($user, UA_FIREFOX_ANDROID, '41.188.12.200');

    expect($user->knownDevices()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('même navigateur dans une version plus récente : aucune alerte', function () {
    $user = User::factory()->citoyen()->create();
    connexion($user, UA_FIREFOX_ANDROID);

    connexion($user, UA_FIREFOX_ANDROID_RECENT);

    expect($user->knownDevices()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('nouvel appareil : exactement une alerte, en base et par e-mail, avec date, heure, appareil et navigateur', function () {
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 3)->setTime(11, 32));
    $user = User::factory()->citoyen()->create();
    connexion($user, UA_CHROME_WINDOWS, '102.16.44.12');

    connexion($user, UA_FIREFOX_ANDROID, '41.188.12.34');

    expect($user->knownDevices()->count())->toBe(2);
    Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
    Notification::assertSentTo($user, NewDeviceLogin::class, function (NewDeviceLogin $notification, array $channels) use ($user): bool {
        $data = $notification->toArray($user);
        $mail = $notification->toMail($user);
        $texteMail = implode("\n", $mail->introLines);

        return $channels === ['database', 'mail']
            && $data['sujet'] === NewDeviceLogin::SUJET
            && str_contains($data['lignes'][0], 'le samedi 3 octobre 2026 à 14 h 32 (heure de Nova Terra) depuis Firefox sur Android (mobile), adresse approximative 41.188.x.x')
            && $data['libelle'] === 'Ce n’était pas moi'
            && str_contains($texteMail, 'Samedi 3 octobre 2026')
            && str_contains($texteMail, '14 h 32')
            && str_contains($texteMail, 'Appareil : Android (mobile)')
            && str_contains($texteMail, 'Navigateur : Firefox')
            && $mail->actionText === 'Ce n’était pas moi'
            && str_contains((string) $mail->actionUrl, 'signature=');
    });
});

test('un autre système avec le même navigateur est aussi un nouvel appareil', function () {
    $user = User::factory()->citoyen()->create();
    connexion($user, UA_CHROME_WINDOWS);

    connexion($user, str_replace('Windows NT 10.0; Win64; x64', 'X11; Linux x86_64', UA_CHROME_WINDOWS));

    Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
});

test('un appareil révoqué n’est plus reconnu, même avec son cookie', function () {
    $user = User::factory()->citoyen()->create();
    $cookie = cookieAppareil(connexion($user, UA_FIREFOX_ANDROID));
    $user->knownDevices()->first()->forceFill(['revoked_at' => now()])->save();

    connexion($user, UA_FIREFOX_ANDROID, cookie: $cookie);

    Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
});

test('connexion échouée : aucun appareil créé, aucune alerte', function () {
    $user = User::factory()->citoyen()->create();
    KnownDevice::factory()->for($user)->create();

    connexion($user, UA_FIREFOX_ANDROID, password: 'mauvais-mot-de-passe');

    expect($user->knownDevices()->count())->toBe(1);
    Notification::assertNothingSent();
});

test('compte désactivé : connexion refusée, aucun appareil créé', function () {
    $user = User::factory()->citoyen()->deactivated()->create();

    connexion($user, UA_FIREFOX_ANDROID);

    expect($user->knownDevices()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('« Ce n’était pas moi » : autres sessions supprimées, remember_token changé, appareil révoqué, invitation à changer le mot de passe', function () {
    $user = User::factory()->citoyen()->create(['remember_token' => 'ancien-jeton']);
    $autre = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create();
    foreach ([['s-intrus', $user], ['s-ancienne', $user], ['s-autre-compte', $autre]] as [$id, $proprietaire]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $proprietaire->id, 'payload' => '', 'last_activity' => time()]);
    }

    $this->actingAs($user)
        ->post(route('profile.devices.not-me', $device))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('appareil_signale', DeviceRecognizer::MESSAGE_SIGNALE);

    $this->assertAuthenticatedAs($user);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 's-autre-compte')->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('ancien-jeton')
        ->and($device->fresh()->isRevoked())->toBeTrue()
        ->and(AuditLog::where('action', 'device_reported')->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->withSession(['auth.password_confirmed_at' => time(), 'appareil_signale' => DeviceRecognizer::MESSAGE_SIGNALE])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Les autres appareils ont été déconnectés. Changez maintenant votre mot de passe pour sécuriser votre compte.');
});

test('la session courante est conservée lors de la déconnexion des autres appareils', function () {
    $user = User::factory()->citoyen()->create();
    foreach (['courante', 'autre'] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }

    app(DeviceRecognizer::class)->logoutOtherSessions($user, 'courante');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['courante']);
});

test('les champs réservés envoyés avec « Ce n’était pas moi » sont ignorés', function () {
    $user = User::factory()->citoyen()->create();
    $autre = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create(['browser' => 'Chrome']);

    $this->actingAs($user)->post(route('profile.devices.not-me', $device), [
        'user_id' => $autre->id,
        'revoked_at' => null,
        'browser' => 'Pirate',
        'device_token_hash' => 'x',
    ])->assertRedirect();

    $device->refresh();
    expect($device->user_id)->toBe($user->id)
        ->and($device->browser)->toBe('Chrome')
        ->and($device->revoked_at)->not->toBeNull()
        ->and($device->device_token_hash)->not->toBe('x');
});

test('lien signé valide : page de confirmation, sans rien révoquer', function () {
    $user = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create();
    $url = URL::temporarySignedRoute('profile.devices.report', now()->addDay(), ['knownDevice' => $device->id, 'user' => $user->id]);

    $this->get($url)->assertOk()->assertSee('Déconnecter les autres appareils');

    expect($device->fresh()->isRevoked())->toBeFalse();
});

test('lien signé validé en POST par un invité : révocation puis réinitialisation du mot de passe', function () {
    $user = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create();
    DB::table('sessions')->insert(['id' => 's-intrus', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $url = URL::temporarySignedRoute('profile.devices.report', now()->addDay(), ['knownDevice' => $device->id, 'user' => $user->id]);

    $this->post($url)
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', DeviceRecognizer::MESSAGE_SIGNALE);

    expect($device->fresh()->isRevoked())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('lien signé altéré ou expiré : 403 avec un message en français', function () {
    $user = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create();
    $url = URL::temporarySignedRoute('profile.devices.report', now()->addDay(), ['knownDevice' => $device->id, 'user' => $user->id]);

    $this->get(str_replace('user='.$user->id, 'user=999', $url))->assertForbidden()->assertSee('Ce lien n’est plus valable');
    $this->post($url.'x')->assertForbidden();

    $this->travel(25)->hours();
    $this->get($url)->assertForbidden()->assertSee('Ce lien n’est plus valable');

    expect($device->fresh()->isRevoked())->toBeFalse();
});

test('citoyen A : 403 sur « Ce n’était pas moi » et sur le retrait d’un appareil de B', function () {
    $a = User::factory()->citoyen()->create();
    $deviceB = KnownDevice::factory()->for(User::factory()->citoyen())->create();

    $this->actingAs($a)->get(route('profile.devices.confirm', $deviceB))->assertForbidden();
    $this->actingAs($a)->post(route('profile.devices.not-me', $deviceB))->assertForbidden();

    $urlB = URL::temporarySignedRoute('profile.devices.report', now()->addDay(), ['knownDevice' => $deviceB->id, 'user' => $deviceB->user_id]);
    $this->actingAs($a)->post($urlB)->assertForbidden();

    Livewire::actingAs($a)->test('pages::profile.devices')->call('remove', $deviceB->id)->assertForbidden();

    expect($deviceB->fresh()->isRevoked())->toBeFalse();
});

test('le propriétaire peut retirer son appareil depuis la liste', function () {
    $user = User::factory()->citoyen()->create();
    $device = KnownDevice::factory()->for($user)->create();

    Livewire::actingAs($user)->test('pages::profile.devices')->call('remove', $device->id)->assertOk();

    expect($device->fresh()->isRevoked())->toBeTrue();
});

test('la liste ne montre que mes appareils et mes connexions récentes', function () {
    $a = User::factory()->citoyen()->create();
    $b = User::factory()->citoyen()->create();
    KnownDevice::factory()->for($a)->create(['browser' => 'Firefox', 'os' => 'Android', 'device_type' => 'mobile']);
    KnownDevice::factory()->for($b)->create(['browser' => 'Opera', 'os' => 'Windows', 'ip_approx' => '203.0.113.x']);
    LoginAttempt::factory()->forUser($a)->successful()->create(['user_agent' => UA_SAFARI_IPHONE, 'ip' => '41.188.12.34']);
    LoginAttempt::factory()->forUser($b)->successful()->create(['user_agent' => UA_CHROME_WINDOWS, 'ip' => '198.51.100.7']);

    $this->actingAs($a)->get(route('profile.devices.index'))
        ->assertOk()
        ->assertSee('Firefox sur Android')
        ->assertSee('Safari sur iOS (mobile)')
        ->assertSee('41.188.x.x')
        ->assertDontSee('Opera')
        ->assertDontSee('203.0.x.x')
        ->assertDontSee('198.51')
        ->assertDontSee('41.188.12.34');
});

test('invité : redirection vers la connexion sur /profil/appareils', function () {
    $this->get(route('profile.devices.index'))->assertRedirect(route('login'));
});

test('le parseur reconnaît les familles courantes et masque les IP', function () {
    $recognizer = app(DeviceRecognizer::class);

    expect($recognizer->parse(UA_SAFARI_IPHONE))->toBe(['browser' => 'Safari', 'os' => 'iOS', 'device_type' => 'mobile'])
        ->and($recognizer->parse(UA_CHROME_WINDOWS))->toBe(['browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'ordinateur'])
        ->and($recognizer->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0')['browser'])->toBe('Edge')
        ->and($recognizer->parse('curl/8.0')['browser'])->toBe('Navigateur inconnu')
        ->and($recognizer->maskIp('41.188.12.34'))->toBe('41.188.12.x')
        ->and($recognizer->maskIp('2001:db8:abcd:12::1'))->toBe('2001:db8:abcd::/48')
        ->and($recognizer->maskIp('pas-une-ip'))->toBeNull();
});
