<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client OpenRouter. La clé reste côté serveur (config/services.php → .env).
 *
 * Sans clé ou sans modèle, en cas d'erreur ou de délai dépassé : renvoie null, jamais d'exception.
 * Réponses mises en cache 1 h (même système + même question = un seul appel facturé).
 */
class Ai
{
    public const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const TIMEOUT_SECONDS = 25;

    public const CACHE_TTL_MINUTES = 60;

    public function isConfigured(): bool
    {
        return filled(config('services.openrouter.key')) && filled(config('services.openrouter.model'));
    }

    public function ask(string $system, string $prompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $model = (string) config('services.openrouter.model');
        $cacheKey = 'ai:'.md5($model.'|'.$system.'|'.$prompt);

        $cached = Cache::get($cacheKey);

        if (is_string($cached)) {
            return $cached;
        }

        try {
            $response = Http::withToken((string) config('services.openrouter.key'))
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::ENDPOINT, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            $content = $response->successful() ? $response->json('choices.0.message.content') : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        Cache::put($cacheKey, $content, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $content;
    }
}
