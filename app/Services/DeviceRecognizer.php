<?php

namespace App\Services;

use App\Models\KnownDevice;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * F54 : seul point d'écriture des appareils connus.
 *
 * À chaque connexion réussie, l'appareil est comparé aux appareils connus de l'utilisateur :
 *   1. cookie d'appareil (jeton aléatoire, chiffré, HttpOnly) correspondant à un appareil non révoqué ;
 *   2. à défaut, même famille de navigateur + même système + même IP approximative.
 * Un nouvel appareil déclenche une seule alerte, sauf à la toute première connexion du compte.
 * Les familles sont comparées, jamais les versions : une mise à jour du navigateur ne déclenche rien.
 */
class DeviceRecognizer
{
    public const COOKIE = 'appareil';

    /** Durée de vie du cookie d'appareil (2 ans). */
    private const COOKIE_MINUTES = 60 * 24 * 365 * 2;

    public const MESSAGE_SIGNALE = 'Les autres appareils ont été déconnectés. Changez maintenant votre mot de passe pour sécuriser votre compte.';

    /**
     * Ne bloque jamais la connexion : toute erreur est seulement journalisée.
     */
    public function handleLogin(User $user, Request $request): void
    {
        try {
            $this->recognize($user, $request);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Appareil de l'utilisateur correspondant au cookie de la requête (non révoqué), s'il existe.
     */
    public function currentDevice(User $user, Request $request): ?KnownDevice
    {
        $jeton = $request->cookie(self::COOKIE);

        if (! is_string($jeton) || $jeton === '') {
            return null;
        }

        return $user->knownDevices()->active()->where('device_token_hash', hash('sha256', $jeton))->first();
    }

    /**
     * « Ce n'était pas moi » : appareil révoqué, autres sessions supprimées, jeton « se souvenir de moi » renouvelé.
     */
    public function reportNotMe(User $user, KnownDevice $device, ?string $currentSessionId): void
    {
        $this->revoke($device);
        $this->logoutOtherSessions($user, $currentSessionId);

        AuditLogger::log('device_reported', $user, [
            'appareil' => ['avant' => $device->libelleAppareil().', adresse '.$device->ipAffichee(), 'apres' => 'Révoqué'],
        ]);
    }

    public function revoke(KnownDevice $device): void
    {
        if ($device->revoked_at === null) {
            $device->revoked_at = now();
            $device->save();
        }
    }

    /**
     * Supprime toutes les sessions de l'utilisateur sauf la session courante, et renouvelle le remember_token
     * pour que les cookies « se souvenir de moi » des autres appareils ne reconnectent plus personne.
     */
    public function logoutOtherSessions(User $user, ?string $currentSessionId): void
    {
        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->when($currentSessionId !== null, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();

        $user->setRememberToken(Str::random(60));
        $user->saveQuietly();
    }

    /**
     * Familles de navigateur et de système, type d'appareil. Aucun numéro de version n'est conservé.
     *
     * @return array{browser: string, os: string, device_type: string}
     */
    public function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $browser = match (true) {
            preg_match('/Edg(e|A|iOS)?\//i', $ua) === 1 => 'Edge',
            preg_match('/OPR\/|Opera/i', $ua) === 1 => 'Opera',
            preg_match('/SamsungBrowser/i', $ua) === 1 => 'Samsung Internet',
            preg_match('/Firefox\/|FxiOS/i', $ua) === 1 => 'Firefox',
            preg_match('/Chrome\/|CriOS|Chromium/i', $ua) === 1 => 'Chrome',
            preg_match('/Safari\//i', $ua) === 1 && preg_match('/Version\//i', $ua) === 1 => 'Safari',
            default => 'Navigateur inconnu',
        };

        $os = match (true) {
            preg_match('/iPad/i', $ua) === 1 => 'iPadOS',
            preg_match('/iPhone|iPod/i', $ua) === 1 => 'iOS',
            preg_match('/Android/i', $ua) === 1 => 'Android',
            preg_match('/CrOS/i', $ua) === 1 => 'ChromeOS',
            preg_match('/Windows/i', $ua) === 1 => 'Windows',
            preg_match('/Macintosh|Mac OS X/i', $ua) === 1 => 'macOS',
            preg_match('/Linux/i', $ua) === 1 => 'Linux',
            default => 'Système inconnu',
        };

        $type = match (true) {
            preg_match('/iPad|Tablet/i', $ua) === 1 || ($os === 'Android' && preg_match('/Mobile/i', $ua) !== 1) => KnownDevice::TYPE_TABLETTE,
            preg_match('/Mobi|iPhone|iPod/i', $ua) === 1 => KnownDevice::TYPE_MOBILE,
            default => KnownDevice::TYPE_ORDINATEUR,
        };

        return ['browser' => $browser, 'os' => $os, 'device_type' => $type];
    }

    /**
     * IP masquée : /24 en IPv4 (« 41.188.12.x »), /48 en IPv6 (« 2001:db8:abcd::/48 »).
     */
    public function maskIp(?string $ip): ?string
    {
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ip);

            return $octets[0].'.'.$octets[1].'.'.$octets[2].'.x';
        }

        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $binaire = (string) inet_pton($ip);
            $prefixe = (string) inet_ntop(substr($binaire, 0, 6).str_repeat("\0", 10));

            return $prefixe.'/48';
        }

        return null;
    }

    private function recognize(User $user, Request $request): void
    {
        $maintenant = now();

        $device = $this->currentDevice($user, $request);
        if ($device !== null) {
            $device->last_seen_at = $maintenant;
            $device->save();

            return;
        }

        $empreinte = $this->parse($request->userAgent());
        $ipApprox = $this->maskIp($request->ip());

        $device = $user->knownDevices()->active()
            ->where('browser', $empreinte['browser'])
            ->where('os', $empreinte['os'])
            ->where('ip_approx', $ipApprox)
            ->first();

        if ($device !== null) {
            // Même appareil sans cookie (cookies effacés) : on lui redonne un jeton, sans alerte.
            $device->device_token_hash = $this->issueToken($request);
            $device->last_seen_at = $maintenant;
            $device->save();

            return;
        }

        $premierAppareil = ! $user->knownDevices()->exists();

        $device = new KnownDevice;
        $device->user_id = $user->id;
        $device->device_token_hash = $this->issueToken($request);
        $device->browser = $empreinte['browser'];
        $device->os = $empreinte['os'];
        $device->device_type = $empreinte['device_type'];
        $device->ip_approx = $ipApprox;
        $device->first_seen_at = $maintenant;
        $device->last_seen_at = $maintenant;
        $device->save();

        if (! $premierAppareil) {
            $user->notify(new NewDeviceLogin($device, $maintenant));
            // F85 : événement de sécurité (signal faible : enregistré, jamais bloquant).
            app(SurveillanceSecurite::class)->nouvelAppareil($user, $device);
        }
    }

    /**
     * Pose le cookie d'appareil (chiffré par EncryptCookies, HttpOnly) et renvoie l'empreinte à stocker.
     */
    private function issueToken(Request $request): string
    {
        $jeton = Str::random(40);

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $jeton,
            self::COOKIE_MINUTES,
            secure: $request->isSecure() || (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return hash('sha256', $jeton);
    }
}
