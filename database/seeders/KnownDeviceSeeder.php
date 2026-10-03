<?php

namespace Database\Seeders;

use App\Models\KnownDevice;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * F54 : pour user@example.com, deux appareils connus (ordinateur et téléphone), un historique de connexions
 * réussies et une alerte « nouvel appareil » non lue. Idempotent : relancer ne crée pas de doublon.
 */
class KnownDeviceSeeder extends Seeder
{
    private const UA_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    private const UA_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1';

    private const UA_ANDROID = 'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0';

    public function run(): void
    {
        $demo = User::where('email', 'user@example.com')->first();

        if ($demo === null || $demo->knownDevices()->exists()) {
            return;
        }

        $this->appareil($demo, 'Chrome', 'Windows', KnownDevice::TYPE_ORDINATEUR, '102.16.44.x', now()->subDays(40), now()->subHours(2));
        $this->appareil($demo, 'Safari', 'iOS', KnownDevice::TYPE_MOBILE, '41.188.12.x', now()->subDays(12), now()->subDay());
        $inconnu = $this->appareil($demo, 'Firefox', 'Android', KnownDevice::TYPE_MOBILE, '197.149.20.x', now()->subMinutes(25), now()->subMinutes(25));

        foreach ([
            [self::UA_WINDOWS, '102.16.44.12', now()->subDays(3)],
            [self::UA_IPHONE, '41.188.12.87', now()->subDays(2)],
            [self::UA_WINDOWS, '102.16.44.12', now()->subDay()->subHours(4)],
            [self::UA_IPHONE, '41.188.12.87', now()->subDay()],
            [self::UA_WINDOWS, '102.16.44.12', now()->subHours(2)],
            [self::UA_ANDROID, '197.149.20.33', now()->subMinutes(25)],
        ] as [$ua, $ip, $moment]) {
            LoginAttempt::factory()->forUser($demo)->successful()->create(['user_agent' => $ua, 'ip' => $ip, 'created_at' => $moment]);
        }

        // Alerte non lue, enregistrée directement en base (pas d'e-mail envoyé par le seeder).
        $notification = new NewDeviceLogin($inconnu, now()->subMinutes(25));
        $demo->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NewDeviceLogin::class,
            'data' => $notification->toArray($demo),
            'read_at' => null,
            'created_at' => now()->subMinutes(25),
        ]);
    }

    private function appareil(User $user, string $browser, string $os, string $type, string $ip, CarbonInterface $premiere, CarbonInterface $derniere): KnownDevice
    {
        return KnownDevice::factory()->for($user)->create([
            'browser' => $browser,
            'os' => $os,
            'device_type' => $type,
            'ip_approx' => $ip,
            'first_seen_at' => $premiere,
            'last_seen_at' => $derniere,
        ]);
    }
}
