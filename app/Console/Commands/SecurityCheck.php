<?php

namespace App\Console\Commands;

use App\Services\SecurityChecker;
use Illuminate\Console\Command;

/**
 * F69 : vérifie la configuration de sécurité de l'environnement (à lancer sur le serveur : cd ~/app && php84 artisan security:check).
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Vérifie la configuration de sécurité (APP_DEBUG, HTTPS, cookies, en-têtes) et affiche OK / KO';

    public function handle(SecurityChecker $checker): int
    {
        $lignes = $checker->verifier();

        $this->table(
            ['État', 'Vérification', 'Détail'],
            array_map(fn (array $ligne): array => [$ligne['ok'] ? 'OK' : 'KO', $ligne['libelle'], $ligne['detail']], $lignes),
        );

        $echecs = count(array_filter($lignes, fn (array $ligne): bool => ! $ligne['ok']));

        if ($echecs > 0) {
            $this->error("{$echecs} vérification(s) en échec : à corriger avant la mise en ligne (variables Hodifly).");

            return self::FAILURE;
        }

        $this->info('Toutes les vérifications de sécurité sont OK.');

        return self::SUCCESS;
    }
}
