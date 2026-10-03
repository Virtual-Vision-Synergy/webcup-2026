<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * Client de l'API Nova Terra (demandes du concours), appelé côté serveur uniquement.
 *
 * URL et clé dans config/services.php → .env (WEB_CUP_API_URL, WEB_CUP_API_KEY).
 * La clé part dans l'en-tête X-Webcup-Api-Key : elle n'apparaît jamais dans une URL ni dans la page.
 *
 * Cache court (5 min) pour ne pas appeler l'API à chaque page, et « dernière version connue »
 * sans expiration : si l'API ne répond pas, on affiche cette version au lieu de planter.
 */
class NovaTerraApiClient
{
    public const TIMEOUT_SECONDS = 5;

    public const CONNECT_TIMEOUT_SECONDS = 3;

    public const RETRY_TIMES = 2;

    public const RETRY_SLEEP_MS = 200;

    public const CACHE_TTL_SECONDS = 300;

    public const FRESH_CACHE_KEY = 'novaterra:fresh';

    public const LAST_KNOWN_CACHE_KEY = 'novaterra:last_known';

    public function isConfigured(): bool
    {
        return filled(config('services.novaterra.url')) && filled(config('services.novaterra.key'));
    }

    public function requests(): NovaTerraResult
    {
        $fresh = Cache::get(self::FRESH_CACHE_KEY);

        if ($this->isValidSnapshot($fresh)) {
            return $this->toResult($fresh, stale: false);
        }

        try {
            $payload = $this->fetch();
        } catch (ConnectionException|RequestException|UnexpectedValueException $e) {
            Log::warning('API Nova Terra indisponible : '.$e->getMessage(), ['exception' => $e::class]);

            $lastKnown = Cache::get(self::LAST_KNOWN_CACHE_KEY);

            return $this->isValidSnapshot($lastKnown)
                ? $this->toResult($lastKnown, stale: true)
                : NovaTerraResult::unavailable();
        }

        $snapshot = [
            'requests' => array_values(array_filter($payload['requests'], 'is_array')),
            'session' => is_array($payload['session'] ?? null) ? $payload['session'] : [],
            'fetched_at' => now()->toIso8601String(),
        ];

        Cache::put(self::FRESH_CACHE_KEY, $snapshot, now()->addSeconds(self::CACHE_TTL_SECONDS));
        Cache::forever(self::LAST_KNOWN_CACHE_KEY, $snapshot);

        return $this->toResult($snapshot, stale: false);
    }

    /**
     * @return array{requests: array<mixed>, session?: mixed}
     *
     * @throws ConnectionException|RequestException|UnexpectedValueException
     */
    private function fetch(): array
    {
        if (! $this->isConfigured()) {
            throw new UnexpectedValueException('URL ou clé API non configurée.');
        }

        $json = Http::baseUrl((string) config('services.novaterra.url'))
            ->withHeaders(['X-Webcup-Api-Key' => (string) config('services.novaterra.key')])
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRY_TIMES, self::RETRY_SLEEP_MS)
            ->get('requests')
            ->throw()
            ->json();

        if (! is_array($json) || ! is_array($json['requests'] ?? null)) {
            throw new UnexpectedValueException('Réponse JSON invalide (bloc « requests » absent).');
        }

        return $json;
    }

    /**
     * @phpstan-assert-if-true array{requests: list<array<string, mixed>>, session: array<string, mixed>, fetched_at: string} $snapshot
     */
    private function isValidSnapshot(mixed $snapshot): bool
    {
        return is_array($snapshot)
            && is_array($snapshot['requests'] ?? null)
            && is_array($snapshot['session'] ?? null)
            && is_string($snapshot['fetched_at'] ?? null);
    }

    /**
     * @param  array{requests: list<array<string, mixed>>, session: array<string, mixed>, fetched_at: string}  $snapshot
     */
    private function toResult(array $snapshot, bool $stale): NovaTerraResult
    {
        return new NovaTerraResult(
            requests: $snapshot['requests'],
            session: $snapshot['session'],
            fetchedAt: CarbonImmutable::parse($snapshot['fetched_at']),
            stale: $stale,
            available: true,
        );
    }
}
