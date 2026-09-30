<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client générique de l'API de l'organisation (URL et jeton dans config/services.php → .env).
 *
 * Sans configuration, en cas d'erreur ou de délai dépassé : renvoie [] (la page affiche
 * « Données momentanément indisponibles »). Seules les réponses réussies sont mises en cache.
 */
class OrgaApi
{
    public const TIMEOUT_SECONDS = 10;

    public const RETRY_TIMES = 2;

    public const RETRY_SLEEP_MS = 500;

    public const DEFAULT_TTL_SECONDS = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.orga.url')) && filled(config('services.orga.token'));
    }

    /**
     * GET sur l'API. Renvoie le contenu de la clé `data` si elle existe, sinon le JSON complet.
     *
     * @param  array<string, scalar>  $query
     * @return array<int|string, mixed>
     */
    public function get(string $path, array $query = [], int $ttlSeconds = self::DEFAULT_TTL_SECONDS): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $url = (string) config('services.orga.url');
        $cacheKey = 'orga:'.md5($url.'|'.$path.'|'.json_encode($query));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $json = Http::baseUrl($url)
                ->withToken((string) config('services.orga.token'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
                ->get($path, $query)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        if (! is_array($json)) {
            return [];
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;

        Cache::put($cacheKey, $data, now()->addSeconds($ttlSeconds));

        return $data;
    }
}
