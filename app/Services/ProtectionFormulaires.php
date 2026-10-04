<?php

namespace App\Services;

use App\Auth\LoginThrottle;
use App\Models\TentativeBloquee;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * F81 : protection des formulaires contre les robots, sans CAPTCHA.
 *
 *  - champ piège `site_web`, invisible pour un humain, que les robots remplissent ;
 *  - jeton chiffré qui contient le formulaire et l'heure d'affichage : absent, falsifié ou expiré → rejet,
 *    envoyé trop vite après l'affichage → rejet ;
 *  - limites d'envois par compte et par IP.
 *
 * Chaque rejet est journalisé dans TentativeBloquee (visible par l'admin dans Filament).
 */
class ProtectionFormulaires
{
    public const CHAMP_PIEGE = 'site_web';

    public const CHAMP_JETON = '_jeton_formulaire';

    public function jeton(string $formulaire): string
    {
        return Crypt::encryptString(json_encode(['f' => $formulaire, 't' => now()->getTimestamp()], JSON_THROW_ON_ERROR));
    }

    /**
     * Motif de rejet (constante TentativeBloquee::MOTIF_*), ou null si l'envoi semble humain.
     */
    public function verifier(string $formulaire, mixed $champPiege, mixed $jeton): ?string
    {
        if (filled($champPiege)) {
            return TentativeBloquee::MOTIF_HONEYPOT;
        }

        $affiche = $this->heureAffichage($formulaire, $jeton);

        if ($affiche === null || $affiche < now()->subMinutes((int) config('security.formulaires.jeton_valide_minutes'))->getTimestamp()) {
            return TentativeBloquee::MOTIF_JETON_INVALIDE;
        }

        if (now()->getTimestamp() - $affiche < (int) config('security.formulaires.delai_minimal.'.$formulaire, 3)) {
            return TentativeBloquee::MOTIF_TROP_RAPIDE;
        }

        return null;
    }

    /**
     * Compte un envoi pour l'utilisateur connecté et pour l'IP. Retourne le délai d'attente en secondes si une
     * limite est déjà atteinte (l'envoi n'est alors pas compté), sinon null.
     */
    public function limiter(string $formulaire, int $parCompte, int $parIp, int $decaySecondes = 60): ?int
    {
        $cles = ['robots:'.$formulaire.':ip:'.request()->ip() => $parIp];

        if (Auth::id() !== null) {
            $cles['robots:'.$formulaire.':user:'.Auth::id()] = $parCompte;
        }

        $attente = 0;

        foreach ($cles as $cle => $max) {
            if (RateLimiter::tooManyAttempts($cle, $max)) {
                $attente = max($attente, RateLimiter::availableIn($cle));
            }
        }

        if ($attente > 0) {
            return $attente;
        }

        foreach (array_keys($cles) as $cle) {
            RateLimiter::hit($cle, $decaySecondes);
        }

        return null;
    }

    /**
     * Enregistre le rejet. Ne fait jamais échouer la requête.
     */
    public function journaliser(string $formulaire, string $motif): void
    {
        try {
            $tentative = new TentativeBloquee;
            $tentative->formulaire = Str::limit($formulaire, 30, '');
            $tentative->motif = $motif;
            $userId = Auth::id();
            $tentative->user_id = $userId === null ? null : (int) $userId;
            $tentative->ip = request()->ip();
            $tentative->user_agent = Str::limit((string) request()->userAgent(), 255, '') ?: null;
            $tentative->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Message affiché à la personne : générique, sans révéler le mécanisme (pas de mention du champ piège).
     */
    public function message(string $motif, int $secondes = 60): string
    {
        return match ($motif) {
            TentativeBloquee::MOTIF_TROP_RAPIDE => 'Envoi trop rapide. Vérifiez vos informations, puis renvoyez le formulaire.',
            TentativeBloquee::MOTIF_DEBIT => self::messageDebit($secondes),
            default => 'Le formulaire a expiré ou n’a pas pu être vérifié. Rechargez la page, puis réessayez.',
        };
    }

    public static function messageDebit(int $secondes): string
    {
        return 'Trop de tentatives, réessayez dans '.LoginThrottle::humanDelay($secondes).'.';
    }

    private function heureAffichage(string $formulaire, mixed $jeton): ?int
    {
        if (! is_string($jeton) || $jeton === '') {
            return null;
        }

        try {
            $donnees = json_decode(Crypt::decryptString($jeton), true, 2, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($donnees) || ($donnees['f'] ?? null) !== $formulaire || ! is_int($donnees['t'] ?? null)) {
            return null;
        }

        return $donnees['t'];
    }
}
