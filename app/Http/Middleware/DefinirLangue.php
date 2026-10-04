<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DefinirLangue
{
    /** Langue de référence : contenus saisis dans cette langue, repli quand une traduction manque (F27). */
    public const REFERENCE = 'fr';

    /** Cookie qui garde la langue choisie sur l'écran de connexion, même après déconnexion (F71). */
    public const COOKIE = 'langue';

    /**
     * D14 : langues proposées (code => nom dans sa propre langue), définies dans config/app.php (« langues »).
     *
     * @return array<string, string>
     */
    public static function langues(): array
    {
        /** @var array<string, string> $langues */
        $langues = config('app.langues', [self::REFERENCE => 'Français']);

        return $langues;
    }

    /**
     * Codes des langues proposées, pour les règles de validation (Rule::in).
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::langues());
    }

    /**
     * Langues dans lesquelles un contenu peut être traduit (toutes sauf la langue de référence).
     *
     * @return array<string, string>
     */
    public static function languesDeTraduction(): array
    {
        return array_diff_key(self::langues(), [self::REFERENCE => true]);
    }

    public static function estProposee(mixed $code): bool
    {
        return is_string($code) && array_key_exists($code, self::langues());
    }

    /**
     * D14 : préférence du compte connecté, sinon choix de la session ou du cookie (visiteur), sinon le français.
     * Aucune détection imposée par le navigateur. Un compte sans préférence reprend la langue choisie avant la
     * connexion (F71).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $choixVisiteur = $request->session()->get('langue') ?? $request->cookie(self::COOKIE);

        // Sans choix : la langue de config/app.php (« locale », le français) reste en place.
        $langue = match (true) {
            $user instanceof User && self::estProposee($user->langue) => $user->langue,
            self::estProposee($choixVisiteur) => $choixVisiteur,
            default => null,
        };

        if ($langue !== null) {
            app()->setLocale($langue);
        }

        if ($user instanceof User && $user->langue === null && self::estProposee($choixVisiteur)) {
            $user->forceFill(['langue' => $langue])->saveQuietly();
        }

        return $next($request);
    }
}
