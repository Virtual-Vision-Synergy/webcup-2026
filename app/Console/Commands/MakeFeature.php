<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Génère une fonctionnalité CRUD complète et sécurisée :
 * migration, modèle, factory, policy, pages Livewire (liste, formulaire, détail),
 * routes, entrée de menu, seeder et tests.
 *
 * Options (toutes combinables, la sortie sans option reste inchangée) :
 *   --fields="nom:type,..."  Champs. Types : string, text, integer, decimal, boolean, date, datetime,
 *                            enum(a/b/c), image. Suffixe ? = facultatif.
 *   --belongs-to=Zone        Relation vers un modèle existant (répétable ; Zone? = facultative) :
 *                            clé étrangère zone_id, select du formulaire, filtre de liste, with('zone').
 *   --statut=a/b/c           Colonne statut (la première valeur est la valeur par défaut) : badge, filtre,
 *                            transition réservée à l'admin (policy changerStatut). Jamais dans #[Fillable].
 *   --public                 Liste et détail accessibles aux invités (lecture seule) ; le reste reste protégé.
 *   --filament               Ressource Filament dans /admin (liste, création, modification, vue).
 *   --label, --plural, --icon, --force
 *
 * Exemple :
 *   php artisan make:feature Signalement --fields="titre:string,description:text?,niveau:enum(faible/moyen/critique),photo:image?"
 *   php artisan make:feature Incident --fields="titre:string,description:text?" --belongs-to=Zone --statut=en_attente/valide/refuse --public --filament
 */
class MakeFeature extends Command
{
    protected $signature = 'make:feature
        {name : Nom du modèle au singulier, en PascalCase (ex. Signalement)}
        {--fields= : Champs "nom:type" séparés par des virgules. Types : string, text, integer, decimal, boolean, date, datetime, enum(a/b/c) (ou a|b|c hors Windows), image. Suffixe ? = facultatif}
        {--label= : Libellé singulier affiché (ex. "Point de regroupement")}
        {--plural= : Libellé pluriel affiché (ex. "Points de regroupement")}
        {--icon=squares-2x2 : Icône Heroicons du menu}
        {--belongs-to=* : Relation vers un modèle existant (ex. Zone ; Zone? = facultative). Répétable}
        {--statut= : Valeurs du statut séparées par / (ex. en_attente/valide/refuse). La première est la valeur par défaut}
        {--public : Liste et détail accessibles sans connexion (lecture seule)}
        {--filament : Génère aussi la ressource Filament (/admin)}
        {--force : Écrase les fichiers existants}';

    protected $description = 'Génère une fonctionnalité CRUD complète (back + front + sécurité + tests)';

    private const TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'image'];

    private const RESERVED = ['id', 'user_id', 'user', 'created_at', 'updated_at', 'record', 'search', 'mine', 'page'];

    /** Noms de modèles qu'on ne peut pas lier : user_id est géré par le générateur. */
    private const PUBLIC_MARKER = '// make:feature:routes-public';

    private const BELONGS_TO_FORBIDDEN = ['User'];

    /** Attributs affichés pour représenter un modèle lié, par ordre de préférence. */
    private const DISPLAY_ATTRIBUTES = ['nom', 'titre', 'name', 'libelle', 'label'];

    /** @var array<int, array{name: string, type: string, nullable: bool, required: bool, options: array<int, string>, label: string}> */
    private array $fields = [];

    private string $model;

    private string $table;

    private string $slug;

    private string $var;

    private string $label;

    private string $plural;

    private string $pluralStudly = '';

    /** @var array<int, array{model: string, relation: string, fk: string, nullable: bool, label: string, table: string, display: string|null, factory: bool}> */
    private array $relations = [];

    /** @var array<int, string> */
    private array $statuts = [];

    private bool $public = false;

    private bool $filament = false;

    public function handle(): int
    {
        // La commande peut être appelée plusieurs fois dans le même processus (tests) : on repart de zéro.
        $this->fields = [];
        $this->relations = [];
        $this->statuts = [];

        $this->model = Str::studly($this->argument('name'));
        $this->table = Str::snake(Str::pluralStudly($this->model));
        $this->slug = Str::kebab(Str::pluralStudly($this->model));
        $this->var = Str::camel($this->model);
        $this->label = $this->option('label') ?: Str::headline($this->model);
        $this->plural = $this->option('plural') ?: Str::headline(Str::pluralStudly($this->model));
        $this->pluralStudly = Str::pluralStudly($this->model);

        $this->public = (bool) $this->option('public');
        $this->filament = (bool) $this->option('filament');

        if (! $this->parseFields() || ! $this->parseRelations() || ! $this->parseStatuts() || ! $this->checkOptions()) {
            return self::FAILURE;
        }

        $files = [
            database_path('migrations/'.$this->migrationStamp().'_create_'.$this->table.'_table.php') => $this->migration(),
            app_path("Models/{$this->model}.php") => $this->modelClass(),
            database_path("factories/{$this->model}Factory.php") => $this->factory(),
            app_path("Policies/{$this->model}Policy.php") => $this->policy(),
            resource_path("views/pages/{$this->slug}/⚡index.blade.php") => $this->indexPage(),
            resource_path("views/pages/{$this->slug}/⚡form.blade.php") => $this->formPage(),
            resource_path("views/pages/{$this->slug}/⚡show.blade.php") => $this->showPage(),
            base_path("tests/Feature/{$this->model}Test.php") => $this->tests(),
        ];

        if ($this->filament) {
            $files += $this->filamentFiles();
        }

        if (! $this->option('force')) {
            foreach (array_keys($files) as $path) {
                if (File::exists($path)) {
                    $this->components->error("Existe déjà : {$this->relative($path)} (--force pour écraser)");

                    return self::FAILURE;
                }
            }

            if (File::glob(database_path("migrations/*_create_{$this->table}_table.php")) !== []) {
                $this->components->error("Une migration create_{$this->table}_table existe déjà.");

                return self::FAILURE;
            }
        }

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $content);
            $this->components->task($this->relative($path));
        }

        $this->insertAtMarker(base_path('routes/features.php'), '// make:feature:routes', $this->routes(), "'{$this->slug}.create'");

        if ($this->public) {
            $this->insertAtMarker(base_path('routes/features.php'), self::PUBLIC_MARKER, $this->publicRoutes(), "'{$this->slug}.index'");
        }

        $this->insertAtMarker(resource_path('views/layouts/app/sidebar.blade.php'), '{{-- make:feature:nav --}}', $this->navItem(), "'{$this->slug}.index'");
        $this->insertAtMarker(database_path('seeders/DatabaseSeeder.php'), '// make:feature:seeders', $this->seederLine(), "\\App\\Models\\{$this->model}::");

        $this->newLine();
        $this->components->info("Fonctionnalité « {$this->plural} » générée.");
        $this->line('  Étapes suivantes :');
        $this->line('  1. Relire la migration, le modèle (relations, index, valeurs par défaut) et la policy');
        $this->line('  2. php artisan migrate');
        $this->line($this->filament
            ? "  3. Relire la ressource Filament (app/Filament/Resources/{$this->pluralStudly}) puis ouvrir /admin/{$this->slug}"
            : "  3. (admin) php artisan make:filament-resource {$this->model} --generate, ou relancer avec --filament");
        $this->line('  4. vendor/bin/pint ; php artisan test');
        $this->line($this->public
            ? "  5. Ouvrir /{$this->slug} (public : tester aussi sans être connecté)"
            : "  5. Ouvrir /{$this->slug}");

        return self::SUCCESS;
    }

    /**
     * Horodatage de la migration : toujours postérieur aux migrations existantes, pour que
     * la table d'un modèle lié (--belongs-to) soit créée avant celle-ci, même dans la même seconde.
     */
    private function migrationStamp(): string
    {
        $now = Carbon::now();

        $latest = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => substr(basename($path), 0, 17))
            ->filter(fn (string $stamp) => (bool) preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}$/', $stamp))
            ->max();

        if (is_string($latest) && $latest >= $now->format('Y_m_d_His')) {
            $parsed = Carbon::createFromFormat('Y_m_d_His', $latest);

            if ($parsed instanceof Carbon) {
                $now = $parsed->addSecond();
            }
        }

        return $now->format('Y_m_d_His');
    }

    private function parseFields(): bool
    {
        $raw = trim((string) $this->option('fields'));

        if ($raw === '') {
            $this->components->error('Précise au moins un champ : --fields="titre:string,..."');

            return false;
        }

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $definition) {
            if (! str_contains($definition, ':')) {
                $this->components->error("Champ invalide : « {$definition} » (format attendu nom:type)");

                return false;
            }

            [$name, $type] = array_map('trim', explode(':', $definition, 2));
            $optional = str_ends_with($type, '?');
            $type = rtrim($type, '?');
            $options = [];

            if (preg_match('/^enum\((.+)\)$/', $type, $matches)) {
                $options = array_values(array_filter(array_map('trim', preg_split('#[|/]#', $matches[1]) ?: [])));
                $type = 'enum';
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                $this->components->error("Nom de champ invalide : « {$name} » (snake_case attendu)");

                return false;
            }

            if (in_array($name, self::RESERVED, true)) {
                $this->components->error("Nom de champ réservé : « {$name} »");

                return false;
            }

            if (! in_array($type, self::TYPES, true)) {
                $this->components->error("Type inconnu : « {$type} ». Types : ".implode(', ', self::TYPES).', enum(a/b)');

                return false;
            }

            if ($type === 'enum' && $options === []) {
                $this->components->error("Le champ enum « {$name} » n'a pas de valeurs : enum(a/b/c)");

                return false;
            }

            if ($type === 'enum') {
                foreach ($options as $option) {
                    if (! preg_match('/^[\p{L}0-9_ -]+$/u', $option)) {
                        $this->components->error("Valeur enum invalide : « {$option} » (lettres, chiffres, espaces, - et _)");

                        return false;
                    }
                }
            }

            $this->fields[] = [
                'name' => $name,
                'type' => $type,
                'nullable' => $optional || $type === 'image',
                'required' => ! $optional && $type !== 'boolean',
                'options' => $options,
                'label' => Str::ucfirst(str_replace('_', ' ', $name)),
            ];
        }

        return true;
    }

    /**
     * Options --belongs-to : le modèle lié doit exister, sinon rien n'est généré.
     */
    private function parseRelations(): bool
    {
        $seen = [];

        foreach ((array) $this->option('belongs-to') as $raw) {
            $raw = trim((string) $raw);
            $nullable = str_ends_with($raw, '?');
            $name = rtrim($raw, '?');

            if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                $this->components->error("--belongs-to : « {$raw} » n'est pas un nom de modèle valide (PascalCase attendu, ex. Zone ou Zone?).");

                return false;
            }

            if (in_array($name, self::BELONGS_TO_FORBIDDEN, true)) {
                $this->components->error("--belongs-to={$name} est interdit : user_id (le propriétaire de la fiche) est déjà géré par le générateur.");

                return false;
            }

            if ($name === $this->model) {
                $this->components->error("--belongs-to={$name} : une fonctionnalité ne peut pas dépendre d'elle-même.");

                return false;
            }

            if (isset($seen[$name])) {
                $this->components->error("--belongs-to={$name} est indiqué deux fois.");

                return false;
            }

            $seen[$name] = true;
            $path = app_path("Models/{$name}.php");

            if (! File::exists($path)) {
                $this->components->error("Modèle lié introuvable : App\\Models\\{$name} (app/Models/{$name}.php n'existe pas). Génère-le d'abord (php artisan make:feature {$name} --fields=\"nom:string\") puis relance. Rien n'a été généré.");

                return false;
            }

            $source = File::get($path);
            $display = collect(self::DISPLAY_ATTRIBUTES)->first(fn (string $attribute) => (bool) preg_match("/['\"]{$attribute}['\"]/", $source));
            $factory = str_contains($source, 'HasFactory');

            if (! $factory) {
                $this->components->warn("{$name} n'utilise pas HasFactory : la factory et les tests générés appellent {$name}::factory(), à ajouter.");
            }

            $this->relations[] = [
                'model' => $name,
                'relation' => Str::camel($name),
                'fk' => Str::snake($name).'_id',
                'nullable' => $nullable,
                'label' => Str::headline($name),
                'table' => preg_match('/\$table\s*=\s*[\'"]([a-z0-9_]+)[\'"]/', $source, $matches) ? $matches[1] : Str::snake(Str::pluralStudly($name)),
                'display' => $display,
                'factory' => $factory,
            ];
        }

        return true;
    }

    /**
     * Option --statut=a/b/c : au moins deux valeurs, la première est la valeur par défaut.
     */
    private function parseStatuts(): bool
    {
        $raw = trim((string) $this->option('statut'));

        if ($raw === '') {
            return true;
        }

        $values = array_values(array_filter(array_map('trim', preg_split('#[|/]#', $raw) ?: []), fn (string $value) => $value !== ''));

        if (count($values) < 2) {
            $this->components->error('--statut demande au moins deux valeurs séparées par / (ex. --statut=en_attente/valide/refuse).');

            return false;
        }

        if (count($values) !== count(array_unique($values))) {
            $this->components->error('--statut : une valeur est indiquée deux fois.');

            return false;
        }

        foreach ($values as $value) {
            if (! preg_match('/^[\p{L}0-9_ -]+$/u', $value) || mb_strlen($value) > 50) {
                $this->components->error("--statut : valeur invalide « {$value} » (lettres, chiffres, espaces, - et _, 50 caractères au maximum).");

                return false;
            }
        }

        $this->statuts = $values;

        return true;
    }

    /**
     * Vérifications qui croisent plusieurs options, avant d'écrire le moindre fichier.
     */
    private function checkOptions(): bool
    {
        foreach ($this->fields as $field) {
            if ($this->statuts !== [] && $field['name'] === 'statut') {
                $this->components->error('Le champ « statut » est réservé : il est géré par --statut (jamais dans #[Fillable]).');

                return false;
            }

            foreach ($this->relations as $relation) {
                if (in_array($field['name'], [$relation['relation'], $relation['fk']], true)) {
                    $this->components->error("Le champ « {$field['name']} » entre en conflit avec --belongs-to={$relation['model']} (relation « {$relation['relation']} », colonne {$relation['fk']}).");

                    return false;
                }
            }
        }

        if ($this->public) {
            $features = base_path('routes/features.php');

            if (! File::exists($features) || ! str_contains(File::get($features), self::PUBLIC_MARKER)) {
                $this->components->error('Marqueur « '.self::PUBLIC_MARKER.' » absent de routes/features.php : --public ne peut pas placer les routes publiques. Rien n\'a été généré.');

                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Back-end */
    /* ------------------------------------------------------------------ */

    private function migration(): string
    {
        $columns = collect($this->fields)->map(function (array $f): string {
            $n = $f['name'];
            $col = match ($f['type']) {
                'string', 'image' => "\$table->string('{$n}')",
                'text' => "\$table->text('{$n}')",
                'integer' => "\$table->integer('{$n}')",
                'decimal' => $this->isCoordinate($n) ? "\$table->decimal('{$n}', 10, 7)" : "\$table->decimal('{$n}', 12, 2)",
                'boolean' => "\$table->boolean('{$n}')->default(false)",
                'date' => "\$table->date('{$n}')",
                'datetime' => "\$table->dateTime('{$n}')",
                'enum' => "\$table->string('{$n}', 50)->index()",
                default => throw new \InvalidArgumentException("Type de champ inconnu : {$f['type']}"),
            };

            if ($f['type'] !== 'boolean' && $f['nullable']) {
                $col .= '->nullable()';
            }

            return "            {$col};";
        });

        $relationColumns = collect($this->relations)->map(function (array $r): string {
            $inferred = $r['table'] === Str::plural(Str::beforeLast($r['fk'], '_id'));

            return "            \$table->foreignId('{$r['fk']}')".($r['nullable'] ? '->nullable()' : '')
                .'->constrained('.($inferred ? '' : "'{$r['table']}'").')->cascadeOnDelete();';
        });

        $statutColumn = $this->statuts === [] ? collect() : collect([
            "            \$table->string('statut', 50)->default(".$this->php($this->statuts[0]).')->index();',
        ]);

        $columns = $relationColumns->concat($columns)->concat($statutColumn)->implode("\n");

        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$this->table}', function (Blueprint \$table) {
                    \$table->id();
                    \$table->foreignId('user_id')->constrained()->cascadeOnDelete();
        {$columns}
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$this->table}');
            }
        };

        PHP;
    }

    private function modelClass(): string
    {
        $fillable = collect($this->fields)->pluck('name')
            ->merge(collect($this->relations)->pluck('fk'))
            ->map(fn ($name) => "'{$name}'")->implode(', ');

        $casts = collect($this->fields)->map(fn ($f) => match ($f['type']) {
            'integer' => "            '{$f['name']}' => 'integer',",
            'decimal' => "            '{$f['name']}' => 'decimal:".($this->isCoordinate($f['name']) ? '7' : '2')."',",
            'boolean' => "            '{$f['name']}' => 'boolean',",
            'date' => "            '{$f['name']}' => 'date',",
            'datetime' => "            '{$f['name']}' => 'datetime',",
            default => null,
        })->filter()->implode("\n");

        $constants = collect($this->fields)->where('type', 'enum')->map(function ($f) {
            $values = collect($f['options'])->map(fn ($o) => $this->php($o))->implode(', ');

            return '    public const '.$this->enumConst($f['name'])." = [{$values}];\n";
        })->implode("\n");

        $statutMembers = '';
        if ($this->statuts !== []) {
            $values = collect($this->statuts)->map(fn ($o) => $this->php($o))->implode(', ');
            $colors = collect($this->statuts)->map(fn ($o, $i) => $this->php($o).' => '.$this->php($this->statutColors($o, $i)[0]))->implode(', ');
            $constants .= "\n    public const STATUT_OPTIONS = [{$values}];\n\n"
                ."    /** Couleurs Flux des badges. */\n"
                ."    public const STATUT_COLORS = [{$colors}];\n\n"
                ."    /**\n     * Valeur par défaut du statut : la première de STATUT_OPTIONS.\n     *\n     * @var array<string, mixed>\n     */\n"
                ."    protected \$attributes = ['statut' => self::STATUT_OPTIONS[0]];\n";

            $statutMembers = <<<'PHP'

                public static function libelleStatut(string $statut): string
                {
                    return ucfirst(str_replace('_', ' ', $statut));
                }

                public function couleurStatut(): string
                {
                    return self::STATUT_COLORS[$this->statut] ?? 'zinc';
                }

                /**
                 * Seul point de passage pour modifier le statut (réservé à l'admin : policy changerStatut).
                 */
                public function changerStatut(string $statut): void
                {
                    if (! in_array($statut, self::STATUT_OPTIONS, true)) {
                        throw new \InvalidArgumentException("Statut inconnu : {$statut}");
                    }

                    $this->statut = $statut;
                    $this->save();
                }

            PHP;
        }

        $relationMembers = collect($this->relations)->map(fn (array $r) => "\n    /**\n"
            ."     * @return BelongsTo<{$r['model']}, \$this>\n"
            ."     */\n"
            ."    public function {$r['relation']}(): BelongsTo\n"
            ."    {\n"
            ."        return \$this->belongsTo({$r['model']}::class);\n"
            ."    }\n")->implode('');
        $extraMembers = $relationMembers.$statutMembers;

        $castsMethod = '';
        if ($casts !== '') {
            $castsMethod = <<<PHP

                /**
                 * @return array<string, string>
                 */
                protected function casts(): array
                {
                    return [
            {$casts}
                    ];
                }

            PHP;
        }

        $fillableNote = $this->statuts === []
            ? "user_id n'est volontairement PAS remplissable : il est assigné dans le code."
            : 'user_id et statut ne sont volontairement PAS remplissables : ils sont assignés dans le code.';

        $coordinatesImport = $this->hasCoordinates() ? "use App\\Models\\Concerns\\HasCoordinates;\n" : '';
        $coordinatesTrait = $this->hasCoordinates() ? "    /** Scopes geolocalises() et proches(), pointCarte() pour <x-carte>. */\n    use HasCoordinates;\n\n" : '';

        $source = <<<PHP
        <?php

        namespace App\Models;

        {$coordinatesImport}use Database\Factories\\{$this->model}Factory;
        use Illuminate\Database\Eloquent\Attributes\Fillable;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\Relations\BelongsTo;

        /**
         * {$fillableNote}
         */
        #[Fillable([{$fillable}])]
        class {$this->model} extends Model
        {
        {$coordinatesTrait}    /** @use HasFactory<{$this->model}Factory> */
            use HasFactory;

        {$constants}
            /**
             * @return BelongsTo<User, \$this>
             */
            public function user(): BelongsTo
            {
                return \$this->belongsTo(User::class);
            }
        {$extraMembers}{$castsMethod}}

        PHP;

        // Sans constante d'options, le gabarit laisse deux lignes vides d'affilée.
        return (string) preg_replace("/\n{3,}/", "\n\n", $source);
    }

    private function factory(): string
    {
        $lines = collect($this->fields)->map(function ($f) {
            $n = $f['name'];
            $value = match ($f['type']) {
                'string' => $this->fakeString($n),
                'text' => "fake('fr_FR')->paragraphs(2, true)",
                'integer' => 'fake()->numberBetween(1, 100)',
                'decimal' => match (true) {
                    str_contains($n, 'lat') => 'fake()->randomFloat(7, -18.95, -18.85)',
                    str_contains($n, 'lng') || str_contains($n, 'lon') => 'fake()->randomFloat(7, 47.48, 47.56)',
                    default => 'fake()->randomFloat(2, 1, 1000)',
                },
                'boolean' => 'fake()->boolean()',
                'date' => "fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d')",
                'datetime' => "fake()->dateTimeBetween('-1 month', 'now')",
                'enum' => "fake()->randomElement({$this->model}::".$this->enumConst($n).')',
                'image' => 'null',
                default => throw new \InvalidArgumentException("Type de champ inconnu : {$f['type']}"),
            };

            return "            '{$n}' => {$value},";
        });

        $relationLines = collect($this->relations)->map(fn (array $r) => "            '{$r['fk']}' => {$r['model']}::factory(),");
        $statutLines = $this->statuts === [] ? collect() : collect(["            'statut' => fake()->randomElement({$this->model}::STATUT_OPTIONS),"]);
        $lines = $relationLines->concat($lines)->concat($statutLines)->implode("\n");

        $relationImports = collect($this->relations)->pluck('model')->sort()
            ->map(fn (string $model) => 'use App\\Models\\'.$model.";\n")->implode('');

        return <<<PHP
        <?php

        namespace Database\Factories;

        use App\Models\\{$this->model};
        use App\Models\User;
        {$relationImports}use Illuminate\Database\Eloquent\Factories\Factory;

        /**
         * @extends Factory<{$this->model}>
         */
        class {$this->model}Factory extends Factory
        {
            /**
             * @return array<string, mixed>
             */
            public function definition(): array
            {
                return [
                    'user_id' => User::factory(),
        {$lines}
                ];
            }
        }

        PHP;
    }

    private function policy(): string
    {
        $m = $this->model;
        $v = $this->var;

        $docNote = $this->public
            ? "Lecture ouverte aux invités (--public) ; création réservée aux utilisateurs connectés ;\n * modification et suppression réservées au propriétaire et aux admins."
            : "Par défaut : tout utilisateur connecté peut lire et créer ;\n * seuls le propriétaire et les admins peuvent modifier ou supprimer.";
        if ($this->statuts !== []) {
            $docNote .= $this->public
                ? "\n * Un invité ne voit pas les fiches au statut par défaut (en attente de validation).\n * Seul un admin peut changer le statut (changerStatut)."
                : "\n * Seul un admin peut changer le statut (changerStatut).";
        }

        $viewUser = $this->public ? '?User' : 'User';
        $viewBody = $this->public && $this->statuts !== []
            ? "\$user !== null || \${$v}->statut !== {$m}::STATUT_OPTIONS[0]"
            : 'true';

        $changerStatut = $this->statuts === [] ? '' : <<<PHP

            public function changerStatut(User \$user, {$m} \${$v}): bool
            {
                return \$user->isAdmin();
            }

        PHP;

        return <<<PHP
        <?php

        namespace App\Policies;

        use App\Models\\{$m};
        use App\Models\User;

        /**
         * {$docNote}
         */
        class {$m}Policy
        {
            public function viewAny({$viewUser} \$user): bool
            {
                return true;
            }

            public function view({$viewUser} \$user, {$m} \${$v}): bool
            {
                return {$viewBody};
            }

            public function create(User \$user): bool
            {
                return true;
            }

            public function update(User \$user, {$m} \${$v}): bool
            {
                return \$user->isAdmin() || \${$v}->user_id === \$user->id;
            }

            public function delete(User \$user, {$m} \${$v}): bool
            {
                return \$user->isAdmin() || \${$v}->user_id === \$user->id;
            }
        {$changerStatut}}

        PHP;
    }

    /* ------------------------------------------------------------------ */
    /* Front (Livewire 4 single-file + Flux) */
    /* ------------------------------------------------------------------ */

    private function indexPage(): string
    {
        $m = $this->model;
        $s = $this->slug;
        $searchable = collect($this->fields)->whereIn('type', ['string', 'text'])->pluck('name')->values();
        $enums = collect($this->fields)->where('type', 'enum')->values();
        $columns = collect($this->fields)->reject(fn ($f) => $f['type'] === 'text')->take(4)->values();
        $titleField = collect($this->fields)->firstWhere('type', 'string')['name'] ?? null;

        $filterDefs = $enums->map(fn ($f) => ['prop' => $this->filterProp($f['name']), 'column' => $f['name']])
            ->concat(collect($this->relations)->map(fn (array $r) => ['prop' => $this->filterProp($r['fk']), 'column' => $r['fk']]))
            ->concat($this->statuts === [] ? [] : [['prop' => $this->filterProp('statut'), 'column' => 'statut']]);

        $filterProps = $filterDefs->map(fn ($d) => "    #[Url(except: '')]\n    public string \$".$d['prop']." = '';\n")->implode("\n");

        $resetHooks = collect(['search', 'mine'])
            ->merge($filterDefs->pluck('prop'))
            ->map(fn ($p) => '    public function updated'.Str::ucfirst($p)."(): void\n    {\n        \$this->resetPage();\n    }\n")
            ->implode("\n");

        $searchQuery = '';
        if ($searchable->isNotEmpty()) {
            $clauses = $searchable->map(fn ($name, $i) => ($i === 0 ? '$q->where' : '->orWhere')."('{$name}', 'like', \$term)")->implode('');
            $searchQuery = "\n            ->when(\$this->search !== '', function (\$query) {\n"
                ."                \$term = '%'.\$this->search.'%';\n"
                ."                \$query->where(fn (\$q) => {$clauses});\n"
                .'            })';
        }

        $filterQuery = $filterDefs->map(fn ($d) => "\n            ->when(\$this->{$d['prop']} !== '', fn (\$query) => \$query->where('{$d['column']}', \$this->{$d['prop']}))")->implode('');

        $mineQuery = $this->public
            ? '->when($this->mine && auth()->check(), fn ($query) => $query->whereBelongsTo(auth()->user()))'
            : '->when($this->mine, fn ($query) => $query->whereBelongsTo(auth()->user()))';

        $guestQuery = $this->public && $this->statuts !== []
            ? "\n            ->when(auth()->guest(), fn (\$query) => \$query->where('statut', '!=', {$m}::STATUT_OPTIONS[0]))"
            : '';

        $enumInputs = $enums->map(function ($f) use ($m) {
            $prop = $this->filterProp($f['name']);
            $const = $this->enumConst($f['name']);

            return "        <flux:select wire:model.live=\"{$prop}\" class=\"sm:max-w-52\">\n"
                ."            <flux:select.option value=\"\">{$f['label']} : tous</flux:select.option>\n"
                ."            @foreach (\\App\\Models\\{$m}::{$const} as \$option)\n"
                ."                <flux:select.option :value=\"\$option\">{{ ucfirst(\$option) }}</flux:select.option>\n"
                ."            @endforeach\n"
                .'        </flux:select>';
        });

        $relationInputs = collect($this->relations)->map(fn (array $r) => '        <flux:select wire:model.live="'.$this->filterProp($r['fk'])."\" class=\"sm:max-w-52\">\n"
            ."            <flux:select.option value=\"\">{$r['label']} : tous</flux:select.option>\n"
            ."            @foreach (\$this->{$r['relation']}Options as \$option)\n"
            .'                <flux:select.option :value="$option->id">{{ '.$this->optionLabel($r, '$option')." }}</flux:select.option>\n"
            ."            @endforeach\n"
            .'        </flux:select>');

        $statutInput = $this->statuts === [] ? collect() : collect([
            '        <flux:select wire:model.live="'.$this->filterProp('statut')."\" class=\"sm:max-w-52\">\n"
            ."            <flux:select.option value=\"\">Statut : tous</flux:select.option>\n"
            ."            @foreach (\\App\\Models\\{$m}::STATUT_OPTIONS as \$option)\n"
            ."                <flux:select.option :value=\"\$option\">{{ \\App\\Models\\{$m}::libelleStatut(\$option) }}</flux:select.option>\n"
            ."            @endforeach\n"
            .'        </flux:select>',
        ]);

        $filterInputs = $enumInputs->concat($relationInputs)->concat($statutInput)->implode("\n");

        $searchInput = $searchable->isNotEmpty()
            ? '        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" class="sm:max-w-xs" />'
            : '';

        $headers = $columns->map(fn ($f) => "                <flux:table.column>{$f['label']}</flux:table.column>")
            ->concat(collect($this->relations)->map(fn (array $r) => "                <flux:table.column>{$r['label']}</flux:table.column>"))
            ->concat($this->statuts === [] ? [] : ['                <flux:table.column>Statut</flux:table.column>'])
            ->implode("\n");

        $cells = $columns->map(function ($f) use ($titleField, $s) {
            $value = $f['name'] === $titleField
                ? "<flux:link :href=\"route('{$s}.show', \$item)\" wire:navigate class=\"font-medium\">{{ \$item->{$f['name']} }}</flux:link>"
                : $this->displayValue($f, '$item');

            return "                        <flux:table.cell>{$value}</flux:table.cell>";
        })->concat(collect($this->relations)->map(fn (array $r) => '                        <flux:table.cell>{{ '.$this->relationValue($r, '$item').' }}</flux:table.cell>'))
            ->concat($this->statuts === [] ? [] : ['                        <flux:table.cell>'.$this->statutBadge('$item').'</flux:table.cell>'])
            ->implode("\n");

        $deleteImages = $this->deleteImagesCode('$record');
        $pluralPhp = $this->php($this->plural);
        $deletedPhp = $this->php($this->label.' supprimé(e).');

        $with = $this->relations === []
            ? "'user'"
            : '[\'user\', '.collect($this->relations)->map(fn (array $r) => "'{$r['relation']}'")->implode(', ').']';
        $optionMethods = $this->optionMethods();
        $relationUses = $this->relationUses();
        $collectionUse = $this->relations === [] ? '' : "use Illuminate\\Database\\Eloquent\\Collection;\n";
        $layoutUse = $this->public ? "use Livewire\\Attributes\\Layout;\n" : '';
        $pageAttributes = $this->public ? "#[Layout('layouts::public'), Title({$pluralPhp})]" : "#[Title({$pluralPhp})]";

        $mineCheckbox = '        <flux:checkbox wire:model.live="mine" label="Mes éléments uniquement" />';
        $authorHeader = '                <flux:table.column>Auteur</flux:table.column>';
        $authorCell = '                        <flux:table.cell>{{ $item->user?->name }}</flux:table.cell>';
        $guestButton = '';
        if ($this->public) {
            $mineCheckbox = "        @auth\n    {$mineCheckbox}\n        @endauth";
            $authorHeader = "                @auth\n    {$authorHeader}\n                @endauth";
            $authorCell = "                        @auth\n    {$authorCell}\n                        @endauth";
            $guestButton = "\n\n        @guest\n"
                ."            <flux:button variant=\"primary\" icon=\"arrow-right-end-on-rectangle\" :href=\"route('login')\">\n"
                ."                Se connecter pour participer\n"
                ."            </flux:button>\n"
                .'        @endguest';
        }

        $pointsMethod = '';
        $mapBlock = '';
        if ($this->hasCoordinates()) {
            $pointTitle = $titleField ? "(string) \$item->{$titleField}" : $this->php($this->label.' #').'.$item->id';
            $pointsMethod = "\n    /**\n"
                ."     * Points de la carte : mêmes filtres que la liste, 200 au maximum.\n"
                ."     *\n"
                ."     * @return array<int, array{lat: float, lng: float, titre: string, url: string|null}>\n"
                ."     */\n"
                ."    #[Computed]\n"
                ."    public function points(): array\n"
                ."    {\n"
                ."        return \$this->filteredQuery()\n"
                ."            ->geolocalises()\n"
                ."            ->latest()\n"
                ."            ->limit(200)\n"
                ."            ->get()\n"
                ."            ->map(fn ({$m} \$item) => \$item->pointCarte({$pointTitle}, route('{$s}.show', \$item)))\n"
                ."            ->filter()\n"
                ."            ->values()\n"
                ."            ->all();\n"
                ."    }\n";
            $mapTitle = $this->php(str_replace('"', '', "Carte : {$this->plural}"));
            $mapBlock = "\n    <details wire:ignore.self class=\"rounded-xl border border-zinc-200 dark:border-zinc-700\">\n"
                ."        <summary class=\"flex cursor-pointer items-center gap-2 px-4 py-3 text-sm font-medium\">\n"
                ."            <flux:icon.map class=\"size-4\" />\n"
                ."            Voir la carte ({{ count(\$this->points) }} emplacement(s))\n"
                ."        </summary>\n"
                ."        <div class=\"px-3 pb-3\">\n"
                ."            @if (\$this->points === [])\n"
                ."                <flux:text>Aucun emplacement à afficher avec ces filtres.</flux:text>\n"
                ."            @else\n"
                ."                <x-carte :points=\"\$this->points\" :label=\"{$mapTitle}\" />\n"
                ."            @endif\n"
                ."        </div>\n"
                ."    </details>\n";
        }

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        {$relationUses}use Flux\Flux;
        use Illuminate\Contracts\Pagination\LengthAwarePaginator;
        use Illuminate\Database\Eloquent\Builder;
        {$collectionUse}use Illuminate\Support\Facades\Storage;
        use Livewire\Attributes\Computed;
        {$layoutUse}use Livewire\Attributes\Title;
        use Livewire\Attributes\Url;
        use Livewire\Component;
        use Livewire\WithPagination;

        new {$pageAttributes} class extends Component {
            use WithPagination;

            #[Url(except: '')]
            public string \$search = '';

            #[Url(except: false)]
            public bool \$mine = false;

        {$filterProps}
            public function mount(): void
            {
                \$this->authorize('viewAny', {$m}::class);
            }

        {$resetHooks}
            /**
             * Recherche et filtres, partagés par la liste (et la carte si l'entité a des coordonnées).
             *
             * @return Builder<{$m}>
             */
            protected function filteredQuery(): Builder
            {
                return {$m}::query(){$searchQuery}
                    {$mineQuery}{$filterQuery}{$guestQuery};
            }

            #[Computed]
            public function items(): LengthAwarePaginator
            {
                return \$this->filteredQuery()
                    ->with({$with})
                    ->latest()
                    ->paginate(10);
            }
        {$optionMethods}{$pointsMethod}
            public function delete(int \$id): void
            {
                \$record = {$m}::findOrFail(\$id);
                \$this->authorize('delete', \$record);
        {$deleteImages}
                \$record->delete();

                Flux::toast(variant: 'success', text: {$deletedPhp});
            }
        }; ?>

        <section class="w-full space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <flux:heading size="xl" level="1">{$this->plural}</flux:heading>
                    <flux:text class="mt-1">{{ \$this->items->total() }} élément(s)</flux:text>
                </div>

                @can('create', \\App\\Models\\{$m}::class)
                    <flux:button variant="primary" icon="plus" :href="route('{$s}.create')" wire:navigate>
                        Ajouter
                    </flux:button>
                @endcan{$guestButton}
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        {$searchInput}
        {$filterInputs}
        {$mineCheckbox}
            </div>
        {$mapBlock}
            @if (\$this->items->isEmpty())
                <flux:card class="py-12 text-center">
                    <flux:heading>Aucun élément pour le moment</flux:heading>
                    <flux:text class="mt-2">Modifie les filtres ou ajoute un premier élément.</flux:text>
                </flux:card>
            @else
                <flux:table :paginate="\$this->items">
                    <flux:table.columns>
        {$headers}
        {$authorHeader}
                        <flux:table.column>Créé le</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach (\$this->items as \$item)
                            <flux:table.row wire:key="row-{{ \$item->id }}">
        {$cells}
        {$authorCell}
                                <flux:table.cell>{{ \$item->created_at->format('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end gap-1">
                                        <flux:button size="sm" variant="ghost" icon="eye" :href="route('{$s}.show', \$item)" wire:navigate />
                                        @can('update', \$item)
                                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('{$s}.edit', \$item)" wire:navigate />
                                        @endcan
                                        @can('delete', \$item)
                                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ \$item->id }})" wire:confirm="Supprimer cet élément ?" />
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>

        BLADE;
    }

    private function formPage(): string
    {
        $m = $this->model;
        $s = $this->slug;
        $v = $this->var;
        $hasImage = collect($this->fields)->contains('type', 'image');

        $props = collect($this->fields)->map(fn ($f) => match ($f['type']) {
            'boolean' => "    public bool \${$f['name']} = false;",
            'image' => "    public \${$f['name']} = null;",
            'enum' => "    public string \${$f['name']} = ".$this->php($f['options'][0]).';',
            default => "    public string \${$f['name']} = '';",
        })->concat(collect($this->relations)->map(fn (array $r) => "    public string \${$r['fk']} = '';"))->implode("\n");

        $fill = collect($this->fields)->reject(fn ($f) => $f['type'] === 'image')->map(function ($f) use ($v) {
            $n = $f['name'];

            return match ($f['type']) {
                'boolean' => "            \$this->{$n} = (bool) \${$v}->{$n};",
                'date' => "            \$this->{$n} = \${$v}->{$n}?->format('Y-m-d') ?? '';",
                'datetime' => "            \$this->{$n} = \${$v}->{$n}?->format('Y-m-d\\TH:i') ?? '';",
                default => "            \$this->{$n} = (string) (\${$v}->{$n} ?? '');",
            };
        })->concat(collect($this->relations)->map(fn (array $r) => "            \$this->{$r['fk']} = (string) (\${$v}->{$r['fk']} ?? '');"))->implode("\n");

        $rules = collect($this->fields)->map(function ($f) use ($m) {
            $presence = $f['required'] ? "'required'" : "'nullable'";
            $rule = match ($f['type']) {
                'string' => "[{$presence}, 'string', 'max:255']",
                'text' => "[{$presence}, 'string', 'max:5000']",
                'integer' => "[{$presence}, 'integer']",
                'decimal' => match (true) {
                    in_array($f['name'], ['lat', 'latitude'], true) => "[{$presence}, 'numeric', 'between:-90,90']",
                    $this->isCoordinate($f['name']) => "[{$presence}, 'numeric', 'between:-180,180']",
                    default => "[{$presence}, 'numeric']",
                },
                'boolean' => "['boolean']",
                'date', 'datetime' => "[{$presence}, 'date']",
                'enum' => "[{$presence}, Rule::in({$m}::".$this->enumConst($f['name']).')]',
                'image' => $f['required']
                    ? "[\$this->record ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']"
                    : "['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']",
                default => throw new \InvalidArgumentException("Type de champ inconnu : {$f['type']}"),
            };

            return "            '{$f['name']}' => {$rule},";
        })->concat(collect($this->relations)->map(fn (array $r) => "            '{$r['fk']}' => [".($r['nullable'] ? "'nullable'" : "'required'").", Rule::exists({$r['model']}::class, 'id')],"))->implode("\n");

        $nullables = collect($this->fields)
            ->reject(fn ($f) => in_array($f['type'], ['boolean', 'image'], true) || $f['required'])
            ->pluck('name')
            ->concat(collect($this->relations)->where('nullable', true)->pluck('fk'))
            ->map(fn ($name) => "'{$name}'")->implode(', ');

        $nullableCode = $nullables === '' ? '' : "\n        foreach ([{$nullables}] as \$field) {\n"
            ."            if ((\$validated[\$field] ?? null) === '') {\n"
            ."                \$validated[\$field] = null;\n"
            ."            }\n"
            ."        }\n";

        $imageHandling = collect($this->fields)->where('type', 'image')->map(function ($f) use ($s) {
            $n = $f['name'];

            return "\n        if (\$this->{$n}) {\n"
                ."            if (\$this->record?->{$n}) {\n"
                ."                Storage::disk('public')->delete(\$this->record->{$n});\n"
                ."            }\n"
                ."            \$validated['{$n}'] = \$this->{$n}->store('{$s}', 'public');\n"
                ."        } else {\n"
                ."            unset(\$validated['{$n}']);\n"
                ."        }\n";
        })->implode('');

        $inputs = collect($this->fields)->map(fn ($f) => $this->formInput($f))
            ->concat(collect($this->relations)->map(fn (array $r) => $this->relationInput($r)))
            ->implode("\n\n");

        if ($this->hasCoordinates()) {
            $mapLabel = $this->php(str_replace('"', '', "Emplacement : {$this->label}"));
            $inputs .= "\n\n        <div class=\"space-y-2\">\n"
                ."            <flux:heading size=\"sm\">Emplacement sur la carte</flux:heading>\n"
                ."            <x-carte mode=\"choix\" :label=\"{$mapLabel}\" />\n"
                .'        </div>';
        }

        $uses = $hasImage ? "use Livewire\\WithFileUploads;\n" : '';
        $traits = $hasImage ? "    use WithFileUploads;\n\n" : '';
        $labelPhp = $this->php($this->label);
        $savedPhp = $this->php($this->label.' enregistré(e).');
        $optionMethods = $this->optionMethods();
        $relationUses = $this->relationUses();
        $collectionUse = $this->relations === [] ? '' : "use Illuminate\\Database\\Eloquent\\Collection;\n";
        $computedUse = $this->relations === [] ? '' : "use Livewire\\Attributes\\Computed;\n";

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        {$relationUses}use Flux\Flux;
        {$collectionUse}use Illuminate\Support\Facades\Storage;
        use Illuminate\Validation\Rule;
        {$computedUse}use Livewire\Attributes\Locked;
        use Livewire\Attributes\Title;
        use Livewire\Component;
        {$uses}
        new #[Title({$labelPhp})] class extends Component {
        {$traits}    #[Locked]
            public ?{$m} \$record = null;

        {$props}

            public function mount(?{$m} \${$v} = null): void
            {
                if (\${$v}?->exists) {
                    \$this->authorize('update', \${$v});
                    \$this->record = \${$v};
        {$fill}
                } else {
                    \$this->authorize('create', {$m}::class);
                }
            }

            /**
             * @return array<string, mixed>
             */
            protected function rules(): array
            {
                return [
        {$rules}
                ];
            }
        {$optionMethods}
            public function save(): void
            {
                \$this->record
                    ? \$this->authorize('update', \$this->record)
                    : \$this->authorize('create', {$m}::class);

                \$validated = \$this->validate();
        {$nullableCode}{$imageHandling}
                if (\$this->record) {
                    \$this->record->update(\$validated);
                    \$record = \$this->record;
                } else {
                    \$record = new {$m}(\$validated);
                    \$record->user()->associate(auth()->user());
                    \$record->save();
                }

                Flux::toast(variant: 'success', text: {$savedPhp});

                \$this->redirectRoute('{$s}.show', \$record, navigate: true);
            }
        }; ?>

        <section class="w-full max-w-2xl space-y-6">
            <div>
                <flux:link :href="route('{$s}.index')" wire:navigate class="text-sm">&larr; {$this->plural}</flux:link>
                <flux:heading size="xl" level="1" class="mt-2">
                    {{ \$record ? 'Modifier' : 'Ajouter' }} : {$this->label}
                </flux:heading>
            </div>

            <form wire:submit="save" class="space-y-6">
        {$inputs}

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">Enregistrer</flux:button>
                    <flux:button :href="route('{$s}.index')" wire:navigate variant="ghost">Annuler</flux:button>
                </div>
            </form>
        </section>

        BLADE;
    }

    private function showPage(): string
    {
        $m = $this->model;
        $s = $this->slug;
        $v = $this->var;
        $titleField = collect($this->fields)->firstWhere('type', 'string')['name'] ?? null;
        $heading = $titleField ? "{{ \$record->{$titleField} }}" : $this->label.' #{{ $record->id }}';

        $images = collect($this->fields)->where('type', 'image')->map(fn ($f) => "    @if (\$record->{$f['name']})\n"
            ."        <img src=\"{{ Storage::url(\$record->{$f['name']}) }}\" alt=\"{$f['label']}\" class=\"max-h-96 w-full rounded-xl object-cover\" />\n"
            .'    @endif')->implode("\n");

        $rows = collect($this->fields)->reject(fn ($f) => $f['type'] === 'image')->map(function ($f) {
            $value = $f['type'] === 'text'
                ? "<p class=\"whitespace-pre-line\">{{ \$record->{$f['name']} ?? '—' }}</p>"
                : $this->displayValue($f, '$record');

            return $this->detailRow($f['label'], $value);
        })
            ->concat(collect($this->relations)->map(fn (array $r) => $this->detailRow($r['label'], '{{ '.$this->relationValue($r, '$record').' }}')))
            ->concat($this->statuts === [] ? [] : [$this->detailRow('Statut', $this->statutBadge('$record'))])
            ->implode("\n");

        $deleteImages = $this->deleteImagesCode('$this->record');
        $labelPhp = $this->php($this->label);
        $deletedPhp = $this->php($this->label.' supprimé(e).');

        $relationNames = collect($this->relations)->map(fn (array $r) => "'{$r['relation']}'")->implode(', ');
        $recordAssignment = $this->relations === [] ? "\$this->record = \${$v};" : "\$this->record = \${$v}->loadMissing([{$relationNames}]);";
        $layoutUse = $this->public ? "use Livewire\\Attributes\\Layout;\n" : '';
        $pageAttributes = $this->public ? "#[Layout('layouts::public'), Title({$labelPhp})]" : "#[Title({$labelPhp})]";
        $authorLine = $this->public ? '@auth Par {{ $record->user?->name }} · @endauth' : 'Par {{ $record->user?->name }} · ';

        $statutMethod = '';
        $statutHeader = '';
        $statutCard = '';
        if ($this->statuts !== []) {
            $statutMethod = "\n    public function changerStatut(string \$statut): void\n"
                ."    {\n"
                ."        \$this->authorize('changerStatut', \$this->record);\n"
                ."        abort_unless(in_array(\$statut, {$m}::STATUT_OPTIONS, true), 422);\n\n"
                ."        \$this->record->changerStatut(\$statut);\n\n"
                ."        Flux::toast(variant: 'success', text: 'Statut mis à jour.');\n"
                ."    }\n";
            $statutHeader = "\n            <div class=\"mt-2\">".$this->statutBadge('$record').'</div>';
            $statutCard = "\n\n    @can('changerStatut', \$record)\n"
                ."        <flux:card class=\"space-y-3\">\n"
                ."            <flux:heading size=\"sm\">Changer le statut</flux:heading>\n"
                ."            <div class=\"flex flex-wrap gap-2\">\n"
                ."                @foreach (\\App\\Models\\{$m}::STATUT_OPTIONS as \$option)\n"
                ."                    <flux:button size=\"sm\" wire:click=\"changerStatut('{{ \$option }}')\" :disabled=\"\$option === \$record->statut\">{{ \\App\\Models\\{$m}::libelleStatut(\$option) }}</flux:button>\n"
                ."                @endforeach\n"
                ."            </div>\n"
                ."        </flux:card>\n"
                .'    @endcan';
        }

        $map = '';
        if ($this->hasCoordinates()) {
            $pointTitle = $titleField ? "(string) \$record->{$titleField}" : $this->php($this->label);
            $map = "\n\n    @if (\$record->latitude !== null && \$record->longitude !== null)\n"
                ."        <x-carte :points=\"[\$record->pointCarte({$pointTitle})]\" hauteur=\"18rem\" label=\"Emplacement sur la carte\" />\n"
                .'    @endif';
        }

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        use Flux\Flux;
        use Illuminate\Support\Facades\Storage;
        {$layoutUse}use Livewire\Attributes\Locked;
        use Livewire\Attributes\Title;
        use Livewire\Component;

        new {$pageAttributes} class extends Component {
            #[Locked]
            public {$m} \$record;

            public function mount({$m} \${$v}): void
            {
                \$this->authorize('view', \${$v});
                {$recordAssignment}
            }

            public function delete(): void
            {
                \$this->authorize('delete', \$this->record);
        {$deleteImages}
                \$this->record->delete();

                Flux::toast(variant: 'success', text: {$deletedPhp});

                \$this->redirectRoute('{$s}.index', navigate: true);
            }
        {$statutMethod}}; ?>

        <section class="w-full max-w-3xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:link :href="route('{$s}.index')" wire:navigate class="text-sm">&larr; {$this->plural}</flux:link>
                    <flux:heading size="xl" level="1" class="mt-2">{$heading}</flux:heading>
                    <flux:text class="mt-1">
                        {$authorLine}{{ \$record->created_at->format('d/m/Y à H:i') }}
                    </flux:text>{$statutHeader}
                </div>

                <div class="flex gap-2">
                    @can('update', \$record)
                        <flux:button icon="pencil-square" :href="route('{$s}.edit', \$record)" wire:navigate>Modifier</flux:button>
                    @endcan
                    @can('delete', \$record)
                        <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cet élément ?">Supprimer</flux:button>
                    @endcan
                </div>
            </div>

        {$images}

            <flux:card>
                <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
        {$rows}
                </dl>
            </flux:card>{$statutCard}{$map}
        </section>

        BLADE;
    }

    /* ------------------------------------------------------------------ */
    /* Tests, routes, menu, seeder */
    /* ------------------------------------------------------------------ */

    private function tests(): string
    {
        $m = $this->model;
        $s = $this->slug;
        $v = $this->var;
        $labelPhp = $this->php('un utilisateur peut créer : '.$this->label);
        $hasRequiredImage = collect($this->fields)->contains(fn ($f) => $f['type'] === 'image' && $f['required']);

        $sets = collect($this->fields)->filter(fn ($f) => $f['required'])->map(function ($f) use ($m) {
            $value = match ($f['type']) {
                'string', 'text' => "'Valeur de test'",
                'integer' => "'5'",
                'decimal' => $this->isCoordinate($f['name']) ? "'-18.9'" : "'12.50'",
                'date' => "'2026-10-03'",
                'datetime' => "'2026-10-03T09:00'",
                'enum' => "{$m}::".$this->enumConst($f['name']).'[0]',
                default => "''",
            };

            return "        ->set('{$f['name']}', {$value})";
        })->implode("\n");

        $relationSetup = collect($this->relations)->map(fn (array $r) => "    \${$r['relation']} = {$r['model']}::factory()->create();\n")->implode('');
        $sets = collect($sets === '' ? [] : [$sets])
            ->concat(collect($this->relations)->map(fn (array $r) => "        ->set('{$r['fk']}', (string) \${$r['relation']}->id)"))
            ->implode("\n");
        $statutExpect = $this->statuts === [] ? '' : "    expect({$m}::where('user_id', \$user->id)->value('statut'))->toBe({$m}::STATUT_OPTIONS[0]);\n";

        $createTest = $hasRequiredImage ? '' : "\ntest({$labelPhp}, function () {\n"
            ."    \$user = User::factory()->create();\n"
            .($relationSetup === '' ? "\n" : $relationSetup."\n")
            ."    Livewire::actingAs(\$user)\n"
            ."        ->test('pages::{$s}.form')\n"
            .($sets === '' ? '' : $sets."\n")
            ."        ->call('save')\n"
            ."        ->assertHasNoErrors();\n\n"
            ."    expect({$m}::where('user_id', \$user->id)->count())->toBe(1);\n"
            .$statutExpect
            ."});\n";

        [$guestTests, $extraTests, $extraUses] = $this->optionTests();

        return <<<PHP
        <?php

        use App\Models\\{$m};
        use App\Models\User;
        {$extraUses}use Livewire\Livewire;

        {$guestTests}

        test('un utilisateur connecté voit la liste des {$s}', function () {
            {$m}::factory()->count(3)->create();

            \$this->actingAs(User::factory()->create())
                ->get(route('{$s}.index'))
                ->assertOk();
        });
        {$createTest}
        test('le propriétaire peut ouvrir la modification', function () {
            \$record = {$m}::factory()->create();

            \$this->actingAs(\$record->user)
                ->get(route('{$s}.edit', \$record))
                ->assertOk();
        });

        test('un autre utilisateur ne peut pas modifier', function () {
            \$record = {$m}::factory()->create();

            \$this->actingAs(User::factory()->create())
                ->get(route('{$s}.edit', \$record))
                ->assertForbidden();
        });

        test('un autre utilisateur ne peut pas supprimer', function () {
            \$record = {$m}::factory()->create();

            Livewire::actingAs(User::factory()->create())
                ->test('pages::{$s}.show', ['{$v}' => \$record])
                ->call('delete')
                ->assertForbidden();

            expect({$m}::find(\$record->id))->not->toBeNull();
        });

        test('un admin peut supprimer', function () {
            \$record = {$m}::factory()->create();

            Livewire::actingAs(User::factory()->admin()->create())
                ->test('pages::{$s}.show', ['{$v}' => \$record])
                ->call('delete');

            expect({$m}::find(\$record->id))->toBeNull();
        });
        {$extraTests}
        PHP;
    }

    private function routes(): string
    {
        $s = $this->slug;
        $v = $this->var;

        if ($this->public) {
            return "    Route::livewire('{$s}/create', 'pages::{$s}.form')->name('{$s}.create');\n"
                ."    Route::livewire('{$s}/{".$v."}/edit', 'pages::{$s}.form')->name('{$s}.edit');\n";
        }

        return "    Route::livewire('{$s}', 'pages::{$s}.index')->name('{$s}.index');\n"
            ."    Route::livewire('{$s}/create', 'pages::{$s}.form')->name('{$s}.create');\n"
            ."    Route::livewire('{$s}/{".$v."}', 'pages::{$s}.show')->name('{$s}.show');\n"
            ."    Route::livewire('{$s}/{".$v."}/edit', 'pages::{$s}.form')->name('{$s}.edit');\n";
    }

    /**
     * Routes en lecture seule, hors middleware auth (--public). Le paramètre numérique évite
     * qu'une URL comme /{$slug}/create soit prise pour un identifiant.
     */
    private function publicRoutes(): string
    {
        $s = $this->slug;
        $v = $this->var;

        return "    Route::livewire('{$s}', 'pages::{$s}.index')->name('{$s}.index');\n"
            ."    Route::livewire('{$s}/{".$v."}', 'pages::{$s}.show')->name('{$s}.show')->whereNumber('{$v}');\n";
    }

    private function navItem(): string
    {
        $icon = $this->option('icon');

        return "                    <flux:sidebar.item icon=\"{$icon}\" :href=\"route('{$this->slug}.index')\" :current=\"request()->routeIs('{$this->slug}.*')\" wire:navigate>\n"
            ."                        {$this->plural}\n"
            ."                    </flux:sidebar.item>\n";
    }

    private function seederLine(): string
    {
        return "        \\App\\Models\\{$this->model}::factory(20)->recycle(\$users)->create();\n";
    }

    /* ------------------------------------------------------------------ */
    /* Tests des options */
    /* ------------------------------------------------------------------ */

    /**
     * Tests propres aux options : [tests pour invité (remplace le test « invité redirigé »), tests supplémentaires, imports].
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function optionTests(): array
    {
        $m = $this->model;
        $s = $this->slug;
        $v = $this->var;
        $visible = $this->statuts === [] ? '' : "['statut' => {$m}::STATUT_OPTIONS[1]]";

        $guestTests = "test('un invité ne peut pas accéder aux {$s}', function () {\n"
            ."    \$this->get(route('{$s}.index'))->assertRedirect(route('login'));\n"
            .'});';

        if ($this->public) {
            $guestTests = implode("\n\n", [
                "test('un invité voit la liste des {$s}', function () {\n"
                ."    {$m}::factory()->count(3)->create({$visible});\n\n"
                ."    \$this->get(route('{$s}.index'))->assertOk();\n"
                .'});',
                "test('un invité voit le détail d\\'une fiche', function () {\n"
                ."    \$record = {$m}::factory()->create({$visible});\n\n"
                ."    \$this->get(route('{$s}.show', \$record))->assertOk();\n"
                .'});',
                "test('un invité ne voit ni les liens de modification ni l\\'auteur', function () {\n"
                ."    \$auteur = User::factory()->create(['name' => 'Auteur Confidentiel']);\n"
                ."    \$record = {$m}::factory()->for(\$auteur)->create({$visible});\n\n"
                ."    \$this->get(route('{$s}.index'))\n"
                ."        ->assertOk()\n"
                ."        ->assertDontSee(route('{$s}.create'))\n"
                ."        ->assertDontSee(route('{$s}.edit', \$record))\n"
                ."        ->assertDontSee('Auteur Confidentiel');\n\n"
                ."    \$this->get(route('{$s}.show', \$record))\n"
                ."        ->assertOk()\n"
                ."        ->assertDontSee(route('{$s}.edit', \$record))\n"
                ."        ->assertDontSee('Auteur Confidentiel');\n"
                .'});',
                "test('un invité est redirigé vers la connexion sur le formulaire', function () {\n"
                ."    \$record = {$m}::factory()->create({$visible});\n\n"
                ."    \$this->get(route('{$s}.create'))->assertRedirect(route('login'));\n"
                ."    \$this->get(route('{$s}.edit', \$record))->assertRedirect(route('login'));\n"
                .'});',
                "test('un invité ne peut ni créer, ni modifier, ni supprimer', function () {\n"
                ."    \$record = {$m}::factory()->create({$visible});\n\n"
                ."    expect(Gate::allows('create', {$m}::class))->toBeFalse()\n"
                ."        ->and(Gate::allows('update', \$record))->toBeFalse()\n"
                ."        ->and(Gate::allows('delete', \$record))->toBeFalse();\n\n"
                ."    Livewire::test('pages::{$s}.index')->call('delete', \$record->id)->assertForbidden();\n\n"
                ."    expect({$m}::find(\$record->id))->not->toBeNull();\n"
                .'});',
            ]);
        }

        $extra = [];

        if ($this->relations !== []) {
            $extra[] = "test('la liste charge les relations sans requêtes en trop (pas de N+1)', function () {\n"
                ."    {$m}::factory()->count(3)->create();\n\n"
                ."    Model::preventLazyLoading();\n\n"
                ."    try {\n"
                ."        \$this->actingAs(User::factory()->create())\n"
                ."            ->get(route('{$s}.index'))\n"
                ."            ->assertOk();\n"
                ."    } finally {\n"
                ."        Model::preventLazyLoading(false);\n"
                ."    }\n"
                .'});';
        }

        foreach ($this->relations as $r) {
            $required = $r['nullable'] ? '' : "\n    Livewire::actingAs(User::factory()->create())\n"
                ."        ->test('pages::{$s}.form')\n"
                ."        ->set('{$r['fk']}', '')\n"
                ."        ->call('save')\n"
                ."        ->assertHasErrors(['{$r['fk']}' => 'required']);\n";

            $extra[] = "test('la relation {$r['relation']} doit exister', function () {\n"
                ."    Livewire::actingAs(User::factory()->create())\n"
                ."        ->test('pages::{$s}.form')\n"
                ."        ->set('{$r['fk']}', '999999')\n"
                ."        ->call('save')\n"
                ."        ->assertHasErrors(['{$r['fk']}']);\n"
                .$required
                .'});';

            $extra[] = "test('la liste se filtre par {$r['relation']}', function () {\n"
                ."    \$a = {$r['model']}::factory()->create();\n"
                ."    \$b = {$r['model']}::factory()->create();\n"
                ."    \$dansA = {$m}::factory()->create(['{$r['fk']}' => \$a->id]);\n"
                ."    {$m}::factory()->create(['{$r['fk']}' => \$b->id]);\n\n"
                ."    \$ids = Livewire::actingAs(User::factory()->create())\n"
                ."        ->test('pages::{$s}.index')\n"
                ."        ->set('".$this->filterProp($r['fk'])."', (string) \$a->id)\n"
                ."        ->instance()->items->pluck('id')->all();\n\n"
                ."    expect(\$ids)->toBe([\$dansA->id]);\n"
                .'});';
        }

        if ($this->statuts !== []) {
            $extra[] = "test('le statut par défaut est la première valeur et n\\'est pas remplissable', function () {\n"
                ."    expect((new {$m})->statut)->toBe({$m}::STATUT_OPTIONS[0]);\n\n"
                ."    \$record = new {$m}(['statut' => {$m}::STATUT_OPTIONS[1]]);\n\n"
                ."    expect(\$record->statut)->toBe({$m}::STATUT_OPTIONS[0]);\n"
                .'});';
            $extra[] = "test('le formulaire n\\'expose pas le statut', function () {\n"
                ."    Livewire::actingAs(User::factory()->create())\n"
                ."        ->test('pages::{$s}.form')\n"
                ."        ->assertDontSeeHtml('wire:model=\"statut\"');\n"
                .'});';
            $extra[] = "test('un utilisateur ne peut pas changer le statut de sa propre fiche', function () {\n"
                ."    \$record = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[0]]);\n\n"
                ."    Livewire::actingAs(\$record->user)\n"
                ."        ->test('pages::{$s}.show', ['{$v}' => \$record])\n"
                ."        ->call('changerStatut', {$m}::STATUT_OPTIONS[1])\n"
                ."        ->assertForbidden();\n\n"
                ."    expect(\$record->refresh()->statut)->toBe({$m}::STATUT_OPTIONS[0]);\n"
                .'});';
            $extra[] = "test('un admin peut changer le statut', function () {\n"
                ."    \$record = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[0]]);\n\n"
                ."    Livewire::actingAs(User::factory()->admin()->create())\n"
                ."        ->test('pages::{$s}.show', ['{$v}' => \$record])\n"
                ."        ->call('changerStatut', {$m}::STATUT_OPTIONS[1]);\n\n"
                ."    expect(\$record->refresh()->statut)->toBe({$m}::STATUT_OPTIONS[1]);\n"
                .'});';
            $extra[] = "test('un statut inconnu est refusé', function () {\n"
                ."    \$record = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[0]]);\n\n"
                ."    Livewire::actingAs(User::factory()->admin()->create())\n"
                ."        ->test('pages::{$s}.show', ['{$v}' => \$record])\n"
                ."        ->call('changerStatut', 'valeur-inconnue')\n"
                ."        ->assertStatus(422);\n\n"
                ."    expect(\$record->refresh()->statut)->toBe({$m}::STATUT_OPTIONS[0]);\n"
                .'});';

            if ($this->public) {
                $extra[] = "test('un invité ne voit que les fiches déjà validées', function () {\n"
                    ."    \$enAttente = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[0]]);\n"
                    ."    \$validee = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[1]]);\n\n"
                    ."    \$this->get(route('{$s}.show', \$validee))->assertOk();\n"
                    ."    \$this->get(route('{$s}.show', \$enAttente))->assertForbidden();\n\n"
                    ."    \$ids = Livewire::test('pages::{$s}.index')->instance()->items->pluck('id')->all();\n\n"
                    ."    expect(\$ids)->toBe([\$validee->id]);\n"
                    .'});';
            }
        }

        if ($this->filament) {
            $extra[] = $this->filamentTests();
        }

        $uses = collect($this->relations)->pluck('model')->sort()->map(fn (string $model) => 'use App\\Models\\'.$model.";\n")->implode('');
        if ($this->relations !== []) {
            $uses .= "use Illuminate\\Database\\Eloquent\\Model;\n";
        }
        if ($this->filament && $this->statuts !== []) {
            $uses .= "use Filament\\Actions\\Testing\\TestAction;\n";
        }
        if ($this->public) {
            $uses .= "use Illuminate\\Support\\Facades\\Gate;\n";
        }

        return [$guestTests, $extra === [] ? '' : "\n".implode("\n\n", $extra)."\n", $uses];
    }

    /**
     * Tests de la ressource Filament (dans le même fichier de tests que le reste).
     */
    private function filamentTests(): string
    {
        $m = $this->model;
        $s = $this->slug;

        $tests = "test('un non-admin n\\'accède pas à la ressource Filament', function () {\n"
            ."    \$record = {$m}::factory()->create();\n\n"
            ."    \$this->actingAs(User::factory()->create());\n\n"
            ."    \$this->get('/admin/{$s}')->assertForbidden();\n"
            ."    \$this->get('/admin/{$s}/create')->assertForbidden();\n"
            ."    \$this->get('/admin/{$s}/'.\$record->getKey().'/edit')->assertForbidden();\n"
            .'});';

        $tests .= "\n\ntest('un admin ouvre la liste, la création et la vue Filament', function () {\n"
            ."    \$record = {$m}::factory()->create();\n\n"
            ."    \$this->actingAs(User::factory()->admin()->create());\n\n"
            ."    \$this->get('/admin/{$s}')->assertOk();\n"
            ."    \$this->get('/admin/{$s}/create')->assertOk();\n"
            ."    \$this->get('/admin/{$s}/'.\$record->getKey())->assertOk();\n"
            ."    \$this->get('/admin/{$s}/'.\$record->getKey().'/edit')->assertOk();\n"
            .'});';

        if ($this->statuts !== []) {
            $tests .= "\n\ntest('un admin change le statut depuis Filament', function () {\n"
                ."    \$record = {$m}::factory()->create(['statut' => {$m}::STATUT_OPTIONS[0]]);\n\n"
                ."    \$this->actingAs(User::factory()->admin()->create());\n\n"
                ."    Livewire::test(\\App\\Filament\\Resources\\{$this->pluralStudly}\\Pages\\List{$this->pluralStudly}::class)\n"
                ."        ->callAction(TestAction::make('changerStatut')->table(\$record), ['statut' => {$m}::STATUT_OPTIONS[1]])\n"
                ."        ->assertHasNoFormErrors();\n\n"
                ."    expect(\$record->refresh()->statut)->toBe({$m}::STATUT_OPTIONS[1]);\n"
                .'});';
        }

        return $tests;
    }

    /* ------------------------------------------------------------------ */
    /* Ressource Filament (--filament) */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, string>
     */
    private function filamentFiles(): array
    {
        $dir = app_path("Filament/Resources/{$this->pluralStudly}");
        $m = $this->model;
        $p = $this->pluralStudly;

        return [
            "{$dir}/{$m}Resource.php" => $this->filamentResource(),
            "{$dir}/Schemas/{$m}Form.php" => $this->filamentForm(),
            "{$dir}/Tables/{$p}Table.php" => $this->filamentTable(),
            "{$dir}/Pages/List{$p}.php" => $this->filamentPage('List', 'ListRecords', 'CreateAction::make(),', ['Filament\Actions\CreateAction']),
            "{$dir}/Pages/Create{$m}.php" => $this->filamentCreatePage(),
            "{$dir}/Pages/View{$m}.php" => $this->filamentPage('View', 'ViewRecord', 'EditAction::make(),', ['Filament\Actions\EditAction'], $m),
            "{$dir}/Pages/Edit{$m}.php" => $this->filamentPage('Edit', 'EditRecord', "ViewAction::make(),\n            DeleteAction::make(),", ['Filament\Actions\DeleteAction', 'Filament\Actions\ViewAction'], $m),
        ];
    }

    private function filamentResource(): string
    {
        $m = $this->model;
        $p = $this->pluralStudly;
        $s = $this->slug;

        $case = 'Outlined'.Str::studly((string) $this->option('icon'));
        $icon = defined('Filament\Support\Icons\Heroicon::'.$case) ? $case : 'OutlinedRectangleStack';

        $titleField = collect($this->fields)->firstWhere('type', 'string')['name'] ?? null;
        $recordTitle = $titleField === null ? '' : "\n    protected static ?string \$recordTitleAttribute = '{$titleField}';\n";
        $modelLabel = $this->php(Str::lower($this->label));
        $pluralLabel = $this->php(Str::lower($this->plural));

        return <<<PHP
        <?php

        namespace App\Filament\Resources\\{$p};

        use App\Filament\Resources\\{$p}\Pages\Create{$m};
        use App\Filament\Resources\\{$p}\Pages\Edit{$m};
        use App\Filament\Resources\\{$p}\Pages\List{$p};
        use App\Filament\Resources\\{$p}\Pages\View{$m};
        use App\Filament\Resources\\{$p}\Schemas\\{$m}Form;
        use App\Filament\Resources\\{$p}\Tables\\{$p}Table;
        use App\Models\\{$m};
        use BackedEnum;
        use Filament\Resources\Resource;
        use Filament\Schemas\Schema;
        use Filament\Support\Icons\Heroicon;
        use Filament\Tables\Table;

        /**
         * Accès réservé aux admins (User::canAccessPanel) ; les droits fins viennent de {$m}Policy.
         */
        class {$m}Resource extends Resource
        {
            protected static ?string \$model = {$m}::class;

            protected static ?string \$slug = '{$s}';

            protected static string|BackedEnum|null \$navigationIcon = Heroicon::{$icon};

            protected static ?string \$modelLabel = {$modelLabel};

            protected static ?string \$pluralModelLabel = {$pluralLabel};
        {$recordTitle}
            public static function form(Schema \$schema): Schema
            {
                return {$m}Form::configure(\$schema);
            }

            public static function table(Table \$table): Table
            {
                return {$p}Table::configure(\$table);
            }

            public static function getRelations(): array
            {
                return [
                    //
                ];
            }

            public static function getPages(): array
            {
                return [
                    'index' => List{$p}::route('/'),
                    'create' => Create{$m}::route('/create'),
                    'view' => View{$m}::route('/{record}'),
                    'edit' => Edit{$m}::route('/{record}/edit'),
                ];
            }
        }

        PHP;
    }

    private function filamentForm(): string
    {
        $m = $this->model;
        $s = $this->slug;
        $imports = [];

        $components = collect($this->fields)->map(function (array $f) use ($m, $s, &$imports): string {
            $n = $f['name'];
            $req = $f['required'] ? "\n                    ->required()" : '';
            $label = "\n                    ->label(".$this->php($f['label']).')';

            switch ($f['type']) {
                case 'string':
                    $imports[] = 'TextInput';

                    return "                TextInput::make('{$n}'){$label}{$req}\n                    ->maxLength(255),";
                case 'text':
                    $imports[] = 'Textarea';

                    return "                Textarea::make('{$n}'){$label}{$req}\n                    ->maxLength(5000)\n                    ->columnSpanFull(),";
                case 'integer':
                    $imports[] = 'TextInput';

                    return "                TextInput::make('{$n}'){$label}{$req}\n                    ->numeric()\n                    ->integer(),";
                case 'decimal':
                    $imports[] = 'TextInput';
                    $bounds = match (true) {
                        in_array($n, ['lat', 'latitude'], true) => "\n                    ->minValue(-90)\n                    ->maxValue(90)",
                        $this->isCoordinate($n) => "\n                    ->minValue(-180)\n                    ->maxValue(180)",
                        default => '',
                    };

                    return "                TextInput::make('{$n}'){$label}{$req}\n                    ->numeric(){$bounds},";
                case 'boolean':
                    $imports[] = 'Toggle';

                    return "                Toggle::make('{$n}'){$label},";
                case 'date':
                    $imports[] = 'DatePicker';

                    return "                DatePicker::make('{$n}'){$label}{$req},";
                case 'datetime':
                    $imports[] = 'DateTimePicker';

                    return "                DateTimePicker::make('{$n}'){$label}{$req},";
                case 'enum':
                    $imports[] = 'Select';
                    $const = $this->enumConst($n);

                    return "                Select::make('{$n}'){$label}{$req}\n                    ->options(array_combine({$m}::{$const}, array_map('ucfirst', {$m}::{$const}))),";
                default:
                    $imports[] = 'FileUpload';

                    return "                FileUpload::make('{$n}'){$label}\n                    ->image()\n                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])\n                    ->disk('public')\n                    ->directory('{$s}')\n                    ->maxSize(2048),";
            }
        });

        $relations = collect($this->relations)->map(function (array $r) use (&$imports): string {
            $imports[] = 'Select';
            $req = $r['nullable'] ? '' : "\n                    ->required()";

            return "                Select::make('{$r['fk']}')\n                    ->label(".$this->php($r['label']).")\n"
                ."                    ->relationship('{$r['relation']}', '".($r['display'] ?? 'id')."')\n"
                ."                    ->searchable()\n                    ->preload(){$req},";
        });

        $uses = collect($imports)->unique()->sort()->map(fn (string $c) => "use Filament\\Forms\\Components\\{$c};\n")->implode('');
        $body = $components->concat($relations)->implode("\n");
        $needsModel = collect($this->fields)->contains('type', 'enum') ? "use App\\Models\\{$m};\n" : '';

        return <<<PHP
        <?php

        namespace App\Filament\Resources\\{$this->pluralStudly}\Schemas;

        {$needsModel}{$uses}use Filament\Schemas\Schema;

        /**
         * user_id et statut n'apparaissent pas ici : ils ne sont jamais saisis librement.
         * user_id est assigné à la création (Pages/Create{$m}.php), le statut passe par l'action « changerStatut ».
         */
        class {$m}Form
        {
            public static function configure(Schema \$schema): Schema
            {
                return \$schema
                    ->components([
        {$body}
                    ]);
            }
        }

        PHP;
    }

    private function filamentTable(): string
    {
        $m = $this->model;
        $p = $this->pluralStudly;
        $columnImports = ['TextColumn'];
        $filterLines = [];
        $needsSelect = false;

        $columns = collect($this->fields)->map(function (array $f) use (&$columnImports): string {
            $n = $f['name'];
            $label = '->label('.$this->php($f['label']).')';

            switch ($f['type']) {
                case 'image':
                    $columnImports[] = 'ImageColumn';

                    return "                ImageColumn::make('{$n}'){$label}->disk('public')->circular(),";
                case 'boolean':
                    $columnImports[] = 'IconColumn';

                    return "                IconColumn::make('{$n}'){$label}->boolean(),";
                case 'text':
                    return "                TextColumn::make('{$n}'){$label}->limit(60)->toggleable(isToggledHiddenByDefault: true),";
                case 'date':
                    return "                TextColumn::make('{$n}'){$label}->date('d/m/Y')->sortable(),";
                case 'datetime':
                    return "                TextColumn::make('{$n}'){$label}->dateTime('d/m/Y H:i')->sortable(),";
                case 'enum':
                    return "                TextColumn::make('{$n}'){$label}->badge()->formatStateUsing(fn (?string \$state): string => ucfirst((string) \$state))->sortable(),";
                case 'integer':
                case 'decimal':
                    $hidden = $this->isCoordinate($n) ? '->toggleable(isToggledHiddenByDefault: true)' : '';

                    return "                TextColumn::make('{$n}'){$label}->numeric()->sortable(){$hidden},";
                default:
                    return "                TextColumn::make('{$n}'){$label}->searchable()->limit(40)->sortable(),";
            }
        });

        $relationColumns = collect($this->relations)->map(fn (array $r) => "                TextColumn::make('{$r['relation']}.".($r['display'] ?? 'id')."')->label(".$this->php($r['label']).')->searchable()->sortable(),');

        $statutColumn = collect();
        if ($this->statuts !== []) {
            $arms = collect($this->statuts)->map(fn (string $o, int $i) => '                        '.$this->php($o).' => '.$this->php($this->statutColors($o, $i)[1]).',')->implode("\n");
            $statutColumn = collect(["                TextColumn::make('statut')->label('Statut')->badge()\n"
                ."                    ->formatStateUsing(fn (string \$state): string => {$m}::libelleStatut(\$state))\n"
                ."                    ->color(fn (string \$state): string => match (\$state) {\n{$arms}\n                        default => 'gray',\n                    })\n"
                .'                    ->sortable(),']);
        }

        $author = "                TextColumn::make('user.name')->label('Auteur')->searchable(),";
        $created = "                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),";

        foreach (collect($this->fields)->where('type', 'enum') as $f) {
            $const = $this->enumConst($f['name']);
            $filterLines[] = "                SelectFilter::make('{$f['name']}')->label(".$this->php($f['label']).")->options(array_combine({$m}::{$const}, array_map('ucfirst', {$m}::{$const}))),";
        }
        foreach ($this->relations as $r) {
            $filterLines[] = "                SelectFilter::make('{$r['fk']}')->label(".$this->php($r['label']).")->relationship('{$r['relation']}', '".($r['display'] ?? 'id')."'),";
        }
        if ($this->statuts !== []) {
            $filterLines[] = "                SelectFilter::make('statut')->label('Statut')->options(array_combine({$m}::STATUT_OPTIONS, array_map({$m}::libelleStatut(...), {$m}::STATUT_OPTIONS))),";
        }

        $actions = ['                ViewAction::make(),', '                EditAction::make(),'];
        $actionImports = ['BulkActionGroup', 'DeleteBulkAction', 'EditAction', 'ViewAction'];
        if ($this->statuts !== []) {
            $needsSelect = true;
            $actionImports[] = 'Action';
            $actions[] = "                Action::make('valider')\n"
                ."                    ->label('Valider')\n"
                ."                    ->icon('heroicon-o-check')\n"
                ."                    ->color('success')\n"
                ."                    ->requiresConfirmation()\n"
                ."                    ->authorize('changerStatut')\n"
                ."                    ->visible(fn ({$m} \$record): bool => \$record->statut === {$m}::STATUT_OPTIONS[0])\n"
                ."                    ->action(fn ({$m} \$record) => \$record->changerStatut({$m}::STATUT_OPTIONS[1])),";
            $actions[] = "                Action::make('changerStatut')\n"
                ."                    ->label('Changer le statut')\n"
                ."                    ->icon('heroicon-o-arrow-path')\n"
                ."                    ->authorize('changerStatut')\n"
                ."                    ->fillForm(fn ({$m} \$record): array => ['statut' => \$record->statut])\n"
                ."                    ->schema([\n"
                ."                        Select::make('statut')\n"
                ."                            ->label('Statut')\n"
                ."                            ->options(array_combine({$m}::STATUT_OPTIONS, array_map({$m}::libelleStatut(...), {$m}::STATUT_OPTIONS)))\n"
                ."                            ->required(),\n"
                ."                    ])\n"
                ."                    ->action(fn ({$m} \$record, array \$data) => \$record->changerStatut(\$data['statut'])),";
        }

        $imports = collect($actionImports)->map(fn ($c) => "Filament\\Actions\\{$c}")
            ->concat(collect($columnImports)->unique()->map(fn ($c) => "Filament\\Tables\\Columns\\{$c}"))
            ->concat($needsSelect ? ['Filament\\Forms\\Components\\Select'] : [])
            ->concat($filterLines === [] ? [] : ['Filament\\Tables\\Filters\\SelectFilter'])
            ->concat(['Filament\\Tables\\Table'])
            ->sort()->map(fn ($c) => "use {$c};\n")->implode('');

        $columnsBody = $columns->concat($relationColumns)->concat($statutColumn)->concat([$author, $created])->implode("\n");
        $filtersBody = $filterLines === [] ? '                //' : implode("\n", $filterLines);
        $actionsBody = implode("\n", $actions);

        return <<<PHP
        <?php

        namespace App\Filament\Resources\\{$p}\Tables;

        use App\Models\\{$m};
        {$imports}
        class {$p}Table
        {
            public static function configure(Table \$table): Table
            {
                return \$table
                    ->columns([
        {$columnsBody}
                    ])
                    ->defaultSort('created_at', 'desc')
                    ->filters([
        {$filtersBody}
                    ])
                    ->recordActions([
        {$actionsBody}
                    ])
                    ->toolbarActions([
                        BulkActionGroup::make([
                            DeleteBulkAction::make(),
                        ]),
                    ]);
            }
        }

        PHP;
    }

    /**
     * @param  array<int, string>  $imports
     */
    private function filamentPage(string $kind, string $base, string $actions, array $imports, ?string $name = null): string
    {
        $m = $this->model;
        $p = $this->pluralStudly;
        $class = $kind.($name ?? $p);
        $uses = collect($imports)->concat(["Filament\\Resources\\Pages\\{$base}"])->sort()->map(fn ($c) => "use {$c};\n")->implode('');

        return <<<PHP
        <?php

        namespace App\Filament\Resources\\{$p}\Pages;

        use App\Filament\Resources\\{$p}\\{$m}Resource;
        {$uses}
        class {$class} extends {$base}
        {
            protected static string \$resource = {$m}Resource::class;

            protected function getHeaderActions(): array
            {
                return [
                    {$actions}
                ];
            }
        }

        PHP;
    }

    private function filamentCreatePage(): string
    {
        $m = $this->model;
        $p = $this->pluralStudly;

        return <<<PHP
        <?php

        namespace App\Filament\Resources\\{$p}\Pages;

        use App\Filament\Resources\\{$p}\\{$m}Resource;
        use App\Models\\{$m};
        use Filament\Resources\Pages\CreateRecord;
        use Illuminate\Database\Eloquent\Model;

        class Create{$m} extends CreateRecord
        {
            protected static string \$resource = {$m}Resource::class;

            /**
             * user_id n'est pas "fillable" : l'admin crée la fiche à son nom, assigné ici.
             *
             * @param  array<string, mixed>  \$data
             */
            protected function handleRecordCreation(array \$data): Model
            {
                \$record = new {$m}(\$data);
                \$record->user_id = max(0, (int) auth()->id());
                \$record->save();

                return \$record;
            }
        }

        PHP;
    }

    /* ------------------------------------------------------------------ */
    /* Briques communes aux options */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array{model: string, relation: string, fk: string, nullable: bool, label: string, table: string, display: string|null, factory: bool}  $r
     */
    private function optionLabel(array $r, string $var): string
    {
        return $r['display'] !== null ? "{$var}->{$r['display']}" : "'#'.{$var}->id";
    }

    /**
     * @param  array{model: string, relation: string, fk: string, nullable: bool, label: string, table: string, display: string|null, factory: bool}  $r
     */
    private function relationValue(array $r, string $var): string
    {
        return $r['display'] !== null
            ? "{$var}->{$r['relation']}?->{$r['display']} ?? '—'"
            : "{$var}->{$r['fk']} ? '#'.{$var}->{$r['fk']} : '—'";
    }

    private function statutBadge(string $var): string
    {
        return "<flux:badge size=\"sm\" :color=\"{$var}->couleurStatut()\">{{ \\App\\Models\\{$this->model}::libelleStatut({$var}->statut) }}</flux:badge>";
    }

    private function detailRow(string $label, string $value): string
    {
        return "            <div class=\"py-3 sm:grid sm:grid-cols-3 sm:gap-4\">\n"
            ."                <dt class=\"text-sm font-medium text-zinc-500 dark:text-zinc-400\">{$label}</dt>\n"
            ."                <dd class=\"mt-1 text-sm sm:col-span-2 sm:mt-0\">{$value}</dd>\n"
            .'            </div>';
    }

    private function relationUses(): string
    {
        return collect($this->relations)->pluck('model')->sort()
            ->map(fn (string $model) => 'use App\\Models\\'.$model.";\n")->implode('');
    }

    /**
     * Listes déroulantes alimentées par les modèles liés (seules les colonnes utiles sont lues).
     */
    private function optionMethods(): string
    {
        return collect($this->relations)->map(function (array $r): string {
            $columns = $r['display'] !== null ? "['id', '{$r['display']}']" : "['id']";
            $order = $r['display'] ?? 'id';

            return "\n    /**\n"
                ."     * Valeurs proposées dans la liste déroulante « {$r['label']} ».\n"
                ."     *\n"
                ."     * @return Collection<int, {$r['model']}>\n"
                ."     */\n"
                ."    #[Computed]\n"
                ."    public function {$r['relation']}Options(): Collection\n"
                ."    {\n"
                ."        return {$r['model']}::query()->orderBy('{$order}')->get({$columns});\n"
                ."    }\n";
        })->implode('');
    }

    /**
     * @param  array{model: string, relation: string, fk: string, nullable: bool, label: string, table: string, display: string|null, factory: bool}  $r
     */
    private function relationInput(array $r): string
    {
        $required = $r['nullable'] ? '' : ' placeholder="Choisir…" required';
        $empty = $r['nullable'] ? "            <flux:select.option value=\"\">— Aucune —</flux:select.option>\n" : '';

        return "        <flux:select wire:model=\"{$r['fk']}\" label=\"{$r['label']}\"{$required}>\n"
            .$empty
            ."            @foreach (\$this->{$r['relation']}Options as \$option)\n"
            .'                <flux:select.option :value="$option->id">{{ '.$this->optionLabel($r, '$option')." }}</flux:select.option>\n"
            ."            @endforeach\n"
            .'        </flux:select>';
    }

    /**
     * Couleur du badge d'un statut : [couleur Flux, couleur Filament], d'après le sens du mot, sinon par rang.
     *
     * @return array{0: string, 1: string}
     */
    private function statutColors(string $value, int $index): array
    {
        $key = Str::lower(Str::ascii($value));

        return match (true) {
            (bool) preg_match('/valid|approuv|accept|resolu|termin|publi|actif|ouvert/', $key) => ['green', 'success'],
            (bool) preg_match('/refus|rejet|ferm|annul|bloq|archiv|expir/', $key) => ['red', 'danger'],
            (bool) preg_match('/attente|nouveau|brouillon|soumis|propos/', $key) => ['amber', 'warning'],
            (bool) preg_match('/cours|traitement/', $key) => ['blue', 'info'],
            default => [['amber', 'warning'], ['green', 'success'], ['red', 'danger'], ['blue', 'info'], ['zinc', 'gray']][$index % 5],
        };
    }

    /* ------------------------------------------------------------------ */
    /* Utilitaires */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array{name: string, type: string, nullable: bool, required: bool, options: array<int, string>, label: string}  $f
     */
    private function formInput(array $f): string
    {
        $n = $f['name'];
        $label = $f['label'];
        $required = $f['required'] ? ' required' : '';

        return match ($f['type']) {
            'string' => "        <flux:input wire:model=\"{$n}\" label=\"{$label}\"{$required} />",
            'text' => "        <flux:textarea wire:model=\"{$n}\" label=\"{$label}\" rows=\"5\"{$required} />",
            'integer' => "        <flux:input wire:model=\"{$n}\" label=\"{$label}\" type=\"number\" step=\"1\"{$required} />",
            'decimal' => "        <flux:input wire:model=\"{$n}\" label=\"{$label}\" type=\"number\" step=\"any\"{$required} />",
            'boolean' => "        <flux:checkbox wire:model=\"{$n}\" label=\"{$label}\" />",
            'date' => "        <flux:input wire:model=\"{$n}\" label=\"{$label}\" type=\"date\"{$required} />",
            'datetime' => "        <flux:input wire:model=\"{$n}\" label=\"{$label}\" type=\"datetime-local\"{$required} />",
            'enum' => "        <flux:select wire:model=\"{$n}\" label=\"{$label}\">\n"
                ."            @foreach (\\App\\Models\\{$this->model}::".$this->enumConst($n)." as \$option)\n"
                ."                <flux:select.option :value=\"\$option\">{{ ucfirst(\$option) }}</flux:select.option>\n"
                ."            @endforeach\n"
                .'        </flux:select>',
            'image' => "        <div class=\"space-y-3\">\n"
                ."            <flux:input type=\"file\" wire:model=\"{$n}\" label=\"{$label}\" accept=\"image/jpeg,image/png,image/webp\" />\n"
                ."            <div wire:loading wire:target=\"{$n}\"><flux:text>Envoi en cours…</flux:text></div>\n"
                ."            @if (\${$n})\n"
                ."                <img src=\"{{ \${$n}->temporaryUrl() }}\" alt=\"Aperçu\" class=\"h-40 rounded-lg object-cover\" />\n"
                ."            @elseif (\$record?->{$n})\n"
                ."                <img src=\"{{ Storage::url(\$record->{$n}) }}\" alt=\"{$label}\" class=\"h-40 rounded-lg object-cover\" />\n"
                ."            @endif\n"
                .'        </div>',
            default => throw new \InvalidArgumentException("Type de champ inconnu : {$f['type']}"),
        };
    }

    /**
     * @param  array{name: string, type: string, nullable: bool, required: bool, options: array<int, string>, label: string}  $f
     */
    private function displayValue(array $f, string $var): string
    {
        $n = $f['name'];

        return match ($f['type']) {
            'enum' => "<flux:badge size=\"sm\">{{ ucfirst({$var}->{$n} ?? '—') }}</flux:badge>",
            'boolean' => "<flux:badge size=\"sm\" :color=\"{$var}->{$n} ? 'green' : 'zinc'\">{{ {$var}->{$n} ? 'Oui' : 'Non' }}</flux:badge>",
            'date' => "{{ {$var}->{$n}?->format('d/m/Y') ?? '—' }}",
            'datetime' => "{{ {$var}->{$n}?->format('d/m/Y H:i') ?? '—' }}",
            'image' => "@if ({$var}->{$n})<img src=\"{{ Storage::url({$var}->{$n}) }}\" alt=\"\" class=\"size-10 rounded object-cover\" />@endif",
            default => "{{ {$var}->{$n} ?? '—' }}",
        };
    }

    private function deleteImagesCode(string $var): string
    {
        return collect($this->fields)->where('type', 'image')->map(fn ($f) => "        if ({$var}->{$f['name']}) {\n"
            ."            Storage::disk('public')->delete({$var}->{$f['name']});\n"
            .'        }')->implode("\n");
    }

    private function fakeString(string $name): string
    {
        return match (true) {
            str_contains($name, 'email') => "fake('fr_FR')->safeEmail()",
            str_contains($name, 'tel') || str_contains($name, 'phone') => "fake('fr_FR')->phoneNumber()",
            str_contains($name, 'ville') || str_contains($name, 'city') => "fake('fr_FR')->city()",
            str_contains($name, 'adresse') || str_contains($name, 'address') => "fake('fr_FR')->streetAddress()",
            str_contains($name, 'zone') || str_contains($name, 'quartier') => "fake()->randomElement(['Analakely', 'Isoraka', 'Ankorondrano', 'Ivandry', 'Ambohijatovo', 'Behoririka', 'Andohalo'])",
            in_array($name, ['nom', 'name', 'prenom'], true) => "fake('fr_FR')->name()",
            default => "rtrim(fake('fr_FR')->sentence(3), '.')",
        };
    }

    private function filterProp(string $name): string
    {
        return 'filter'.Str::studly($name);
    }

    private function enumConst(string $name): string
    {
        return Str::upper($name).'_OPTIONS';
    }

    /**
     * L'entité a une position (champs latitude et longitude) : trait HasCoordinates et cartes.
     */
    private function hasCoordinates(): bool
    {
        $names = collect($this->fields)->where('type', 'decimal')->pluck('name');

        return $names->contains('latitude') && $names->contains('longitude');
    }

    private function isCoordinate(string $name): bool
    {
        return in_array($name, ['lat', 'lng', 'lon', 'latitude', 'longitude'], true);
    }

    /**
     * Chaîne PHP entre apostrophes, échappée.
     */
    private function php(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function insertAtMarker(string $path, string $marker, string $content, string $guard): void
    {
        if (! File::exists($path)) {
            $this->components->warn("Fichier introuvable : {$this->relative($path)}");

            return;
        }

        $source = File::get($path);

        if (str_contains($source, $guard)) {
            $this->components->twoColumnDetail($this->relative($path), 'déjà présent');

            return;
        }

        // Ligne du marqueur : égalité stricte (le marqueur « routes » est un préfixe de « routes-public »).
        $lines = explode("\n", $source);
        $index = collect($lines)->search(fn (string $line) => trim($line) === $marker);

        if ($index === false) {
            $this->components->warn("Marqueur « {$marker} » absent de {$this->relative($path)} : ajout manuel nécessaire.");

            return;
        }

        $lines[$index] = $content."\n".$lines[$index];

        File::put($path, implode("\n", $lines));
        $this->components->task($this->relative($path).' (mis à jour)');
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/\\');
    }
}
