<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Résultat d'une lecture de l'API Nova Terra.
 *
 * - stale = true : l'API n'a pas répondu, les données viennent de la dernière version connue.
 * - available = false : l'API n'a pas répondu et aucune donnée n'a jamais été reçue.
 */
final readonly class NovaTerraResult
{
    /**
     * @param  list<array<string, mixed>>  $requests
     * @param  array<string, mixed>  $session
     */
    public function __construct(
        public array $requests,
        public array $session,
        public ?CarbonImmutable $fetchedAt,
        public bool $stale,
        public bool $available,
    ) {}

    public static function unavailable(): self
    {
        return new self([], [], null, true, false);
    }
}
