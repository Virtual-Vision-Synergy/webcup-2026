<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Remet la base dans l'état des données de test du jury, depuis l'admin.
 *
 * - Sauvegarde de la base d'abord (abandon si elle échoue).
 * - Toutes les tables sont vidées sauf les comptes, les rôles, les quartiers et les tables techniques
 *   (sessions, cache, files d'attente, migrations) : personne n'est déconnecté.
 * - DatabaseSeeder est relancé en mode « réinitialisation » : les comptes de démo sont remis dans leur
 *   état d'origine (sauf l'admin qui lance l'opération) et les données de démo sont recréées.
 * - Le tout dans une transaction : en cas d'erreur, la base reste telle qu'avant.
 */
class ReinitialisationDonnees
{
    /**
     * Tables jamais vidées.
     *
     * @var list<string>
     */
    public const TABLES_CONSERVEES = [
        'migrations',
        'users',
        'roles',
        'quartiers',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    public function __construct(private Sauvegardes $sauvegardes) {}

    public function lancer(User $admin): void
    {
        // Les factories utilisent Faker, installé en dépendance de développement uniquement.
        if (! class_exists(FakerFactory::class)) {
            throw new RuntimeException('Faker n’est pas installé sur ce serveur : impossible de générer les données de démo.');
        }

        $this->sauvegardes->lancer();

        DB::transaction(function () use ($admin): void {
            // Les comptes conservés gardent leur prise en main (sinon ils repasseraient par /bienvenue).
            $prisesEnMain = DB::table('onboardings')->get()
                ->map(fn (object $ligne): array => Arr::except((array) $ligne, ['id', 'service_id']));

            foreach ($this->tablesAVider() as $table) {
                DB::table($table)->delete();
            }

            $seeder = new DatabaseSeeder;
            $seeder->setContainer(app());
            $seeder->reinitialisation = true;
            $seeder->compteProtegeId = $admin->id;
            // Comme `php artisan db:seed`, qui lance les seeders sans protection d'assignation de masse.
            Model::unguarded(fn () => $seeder->__invoke());

            $dejaRecreees = DB::table('onboardings')->pluck('user_id')->all();
            DB::table('onboardings')->insert(
                $prisesEnMain->reject(fn (array $ligne): bool => in_array($ligne['user_id'], $dejaRecreees))->values()->all(),
            );
        });
    }

    /**
     * Tables à vider, les tables qui en référencent d'autres en premier (clés étrangères respectées).
     *
     * @return list<string>
     */
    public function tablesAVider(): array
    {
        $tables = array_values(array_diff(Schema::getTableListing(schemaQualified: false), self::TABLES_CONSERVEES));

        $references = [];
        foreach ($tables as $table) {
            $references[$table] = array_values(array_intersect(
                array_diff(array_column(Schema::getForeignKeys($table), 'foreign_table'), [$table]),
                $tables,
            ));
        }

        $ordre = [];
        while ($references !== []) {
            // Une table peut être vidée quand plus aucune table restante ne la référence.
            $libres = array_keys(array_filter(
                $references,
                fn (array $cibles, string $table): bool => ! collect($references)->contains(fn (array $autres): bool => in_array($table, $autres)),
                ARRAY_FILTER_USE_BOTH,
            ));

            // Références circulaires : on prend le reste tel quel.
            $libres = $libres === [] ? array_keys($references) : $libres;

            foreach ($libres as $table) {
                $ordre[] = $table;
                unset($references[$table]);
            }
        }

        return $ordre;
    }
}
