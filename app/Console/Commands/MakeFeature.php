<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Génère une fonctionnalité CRUD complète et sécurisée :
 * migration, modèle, factory, policy, pages Livewire (liste, formulaire, détail),
 * routes, entrée de menu, seeder et tests.
 *
 * Exemple :
 *   php artisan make:feature Signalement --fields="titre:string,description:text?,niveau:enum(faible/moyen/critique),photo:image?"
 */
class MakeFeature extends Command
{
    protected $signature = 'make:feature
        {name : Nom du modèle au singulier, en PascalCase (ex. Signalement)}
        {--fields= : Champs "nom:type" séparés par des virgules. Types : string, text, integer, decimal, boolean, date, datetime, enum(a/b/c) (ou a|b|c hors Windows), image. Suffixe ? = facultatif}
        {--label= : Libellé singulier affiché (ex. "Point de regroupement")}
        {--plural= : Libellé pluriel affiché (ex. "Points de regroupement")}
        {--icon=squares-2x2 : Icône Heroicons du menu}
        {--force : Écrase les fichiers existants}';

    protected $description = 'Génère une fonctionnalité CRUD complète (back + front + sécurité + tests)';

    private const TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'image'];

    private const RESERVED = ['id', 'user_id', 'user', 'created_at', 'updated_at', 'record', 'search', 'mine', 'page'];

    /** @var array<int, array{name: string, type: string, nullable: bool, required: bool, options: array<int, string>, label: string}> */
    private array $fields = [];

    private string $model;

    private string $table;

    private string $slug;

    private string $var;

    private string $label;

    private string $plural;

    public function handle(): int
    {
        $this->model = Str::studly($this->argument('name'));
        $this->table = Str::snake(Str::pluralStudly($this->model));
        $this->slug = Str::kebab(Str::pluralStudly($this->model));
        $this->var = Str::camel($this->model);
        $this->label = $this->option('label') ?: Str::headline($this->model);
        $this->plural = $this->option('plural') ?: Str::headline(Str::pluralStudly($this->model));

        if (! $this->parseFields()) {
            return self::FAILURE;
        }

        $files = [
            database_path('migrations/'.date('Y_m_d_His').'_create_'.$this->table.'_table.php') => $this->migration(),
            app_path("Models/{$this->model}.php") => $this->modelClass(),
            database_path("factories/{$this->model}Factory.php") => $this->factory(),
            app_path("Policies/{$this->model}Policy.php") => $this->policy(),
            resource_path("views/pages/{$this->slug}/⚡index.blade.php") => $this->indexPage(),
            resource_path("views/pages/{$this->slug}/⚡form.blade.php") => $this->formPage(),
            resource_path("views/pages/{$this->slug}/⚡show.blade.php") => $this->showPage(),
            base_path("tests/Feature/{$this->model}Test.php") => $this->tests(),
        ];

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

        $this->insertAtMarker(base_path('routes/features.php'), '// make:feature:routes', $this->routes(), "'{$this->slug}.index'");
        $this->insertAtMarker(resource_path('views/layouts/app/sidebar.blade.php'), '{{-- make:feature:nav --}}', $this->navItem(), "'{$this->slug}.index'");
        $this->insertAtMarker(database_path('seeders/DatabaseSeeder.php'), '// make:feature:seeders', $this->seederLine(), "\\App\\Models\\{$this->model}::");

        $this->newLine();
        $this->components->info("Fonctionnalité « {$this->plural} » générée.");
        $this->line('  Étapes suivantes :');
        $this->line('  1. Relire la migration et le modèle (relations, index, valeurs par défaut)');
        $this->line('  2. php artisan migrate');
        $this->line("  3. (admin) php artisan make:filament-resource {$this->model} --generate");
        $this->line('  4. vendor/bin/pint ; php artisan test');
        $this->line("  5. Ouvrir /{$this->slug}");

        return self::SUCCESS;
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
                $options = array_values(array_filter(array_map('trim', preg_split('#[|/]#', $matches[1]))));
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
            };

            if ($f['type'] !== 'boolean' && $f['nullable']) {
                $col .= '->nullable()';
            }

            return "            {$col};";
        })->implode("\n");

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
        $fillable = collect($this->fields)->map(fn ($f) => "'{$f['name']}'")->implode(', ');

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

        return <<<PHP
        <?php

        namespace App\Models;

        use Database\Factories\\{$this->model}Factory;
        use Illuminate\Database\Eloquent\Attributes\Fillable;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\Relations\BelongsTo;

        /**
         * user_id n'est volontairement PAS remplissable : il est assigné dans le code.
         */
        #[Fillable([{$fillable}])]
        class {$this->model} extends Model
        {
            /** @use HasFactory<{$this->model}Factory> */
            use HasFactory;

        {$constants}
            /**
             * @return BelongsTo<User, \$this>
             */
            public function user(): BelongsTo
            {
                return \$this->belongsTo(User::class);
            }
        {$castsMethod}}

        PHP;
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
            };

            return "            '{$n}' => {$value},";
        })->implode("\n");

        return <<<PHP
        <?php

        namespace Database\Factories;

        use App\Models\\{$this->model};
        use App\Models\User;
        use Illuminate\Database\Eloquent\Factories\Factory;

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

        return <<<PHP
        <?php

        namespace App\Policies;

        use App\Models\\{$m};
        use App\Models\User;

        /**
         * Par défaut : tout utilisateur connecté peut lire et créer ;
         * seuls le propriétaire et les admins peuvent modifier ou supprimer.
         */
        class {$m}Policy
        {
            public function viewAny(User \$user): bool
            {
                return true;
            }

            public function view(User \$user, {$m} \${$v}): bool
            {
                return true;
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
        }

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

        $filterProps = $enums->map(fn ($f) => "    #[Url(except: '')]\n    public string \$".$this->filterProp($f['name'])." = '';\n")->implode("\n");

        $resetHooks = collect(['search', 'mine'])
            ->merge($enums->map(fn ($f) => $this->filterProp($f['name'])))
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

        $filterQuery = $enums->map(function ($f) {
            $prop = $this->filterProp($f['name']);

            return "\n            ->when(\$this->{$prop} !== '', fn (\$query) => \$query->where('{$f['name']}', \$this->{$prop}))";
        })->implode('');

        $filterInputs = $enums->map(function ($f) use ($m) {
            $prop = $this->filterProp($f['name']);
            $const = $this->enumConst($f['name']);

            return "        <flux:select wire:model.live=\"{$prop}\" class=\"sm:max-w-52\">\n"
                ."            <flux:select.option value=\"\">{$f['label']} : tous</flux:select.option>\n"
                ."            @foreach (\\App\\Models\\{$m}::{$const} as \$option)\n"
                ."                <flux:select.option :value=\"\$option\">{{ ucfirst(\$option) }}</flux:select.option>\n"
                ."            @endforeach\n"
                .'        </flux:select>';
        })->implode("\n");

        $searchInput = $searchable->isNotEmpty()
            ? '        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" class="sm:max-w-xs" />'
            : '';

        $headers = $columns->map(fn ($f) => "                <flux:table.column>{$f['label']}</flux:table.column>")->implode("\n");

        $cells = $columns->map(function ($f) use ($titleField, $s) {
            $value = $f['name'] === $titleField
                ? "<flux:link :href=\"route('{$s}.show', \$item)\" wire:navigate class=\"font-medium\">{{ \$item->{$f['name']} }}</flux:link>"
                : $this->displayValue($f, '$item');

            return "                        <flux:table.cell>{$value}</flux:table.cell>";
        })->implode("\n");

        $deleteImages = $this->deleteImagesCode('$record');
        $pluralPhp = $this->php($this->plural);
        $deletedPhp = $this->php($this->label.' supprimé(e).');

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        use Flux\Flux;
        use Illuminate\Contracts\Pagination\LengthAwarePaginator;
        use Illuminate\Support\Facades\Storage;
        use Livewire\Attributes\Computed;
        use Livewire\Attributes\Title;
        use Livewire\Attributes\Url;
        use Livewire\Component;
        use Livewire\WithPagination;

        new #[Title({$pluralPhp})] class extends Component {
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
            #[Computed]
            public function items(): LengthAwarePaginator
            {
                return {$m}::query()
                    ->with('user'){$searchQuery}
                    ->when(\$this->mine, fn (\$query) => \$query->whereBelongsTo(auth()->user())){$filterQuery}
                    ->latest()
                    ->paginate(10);
            }

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
                @endcan
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        {$searchInput}
        {$filterInputs}
                <flux:checkbox wire:model.live="mine" label="Mes éléments uniquement" />
            </div>

            @if (\$this->items->isEmpty())
                <flux:card class="py-12 text-center">
                    <flux:heading>Aucun élément pour le moment</flux:heading>
                    <flux:text class="mt-2">Modifie les filtres ou ajoute un premier élément.</flux:text>
                </flux:card>
            @else
                <flux:table :paginate="\$this->items">
                    <flux:table.columns>
        {$headers}
                        <flux:table.column>Auteur</flux:table.column>
                        <flux:table.column>Créé le</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach (\$this->items as \$item)
                            <flux:table.row wire:key="row-{{ \$item->id }}">
        {$cells}
                                <flux:table.cell>{{ \$item->user?->name }}</flux:table.cell>
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
        })->implode("\n");

        $fill = collect($this->fields)->reject(fn ($f) => $f['type'] === 'image')->map(function ($f) use ($v) {
            $n = $f['name'];

            return match ($f['type']) {
                'boolean' => "            \$this->{$n} = (bool) \${$v}->{$n};",
                'date' => "            \$this->{$n} = \${$v}->{$n}?->format('Y-m-d') ?? '';",
                'datetime' => "            \$this->{$n} = \${$v}->{$n}?->format('Y-m-d\\TH:i') ?? '';",
                default => "            \$this->{$n} = (string) (\${$v}->{$n} ?? '');",
            };
        })->implode("\n");

        $rules = collect($this->fields)->map(function ($f) use ($m) {
            $presence = $f['required'] ? "'required'" : "'nullable'";
            $rule = match ($f['type']) {
                'string' => "[{$presence}, 'string', 'max:255']",
                'text' => "[{$presence}, 'string', 'max:5000']",
                'integer' => "[{$presence}, 'integer']",
                'decimal' => $this->isCoordinate($f['name'])
                    ? "[{$presence}, 'numeric', 'between:-180,180']"
                    : "[{$presence}, 'numeric']",
                'boolean' => "['boolean']",
                'date', 'datetime' => "[{$presence}, 'date']",
                'enum' => "[{$presence}, Rule::in({$m}::".$this->enumConst($f['name']).')]',
                'image' => $f['required']
                    ? "[\$this->record ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']"
                    : "['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']",
            };

            return "            '{$f['name']}' => {$rule},";
        })->implode("\n");

        $nullables = collect($this->fields)
            ->reject(fn ($f) => in_array($f['type'], ['boolean', 'image'], true) || $f['required'])
            ->map(fn ($f) => "'{$f['name']}'")->implode(', ');

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

        $inputs = collect($this->fields)->map(fn ($f) => $this->formInput($f))->implode("\n\n");

        $uses = $hasImage ? "use Livewire\\WithFileUploads;\n" : '';
        $traits = $hasImage ? "    use WithFileUploads;\n\n" : '';
        $labelPhp = $this->php($this->label);
        $savedPhp = $this->php($this->label.' enregistré(e).');

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        use Flux\Flux;
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Validation\Rule;
        use Livewire\Attributes\Locked;
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

            return "            <div class=\"py-3 sm:grid sm:grid-cols-3 sm:gap-4\">\n"
                ."                <dt class=\"text-sm font-medium text-zinc-500 dark:text-zinc-400\">{$f['label']}</dt>\n"
                ."                <dd class=\"mt-1 text-sm sm:col-span-2 sm:mt-0\">{$value}</dd>\n"
                .'            </div>';
        })->implode("\n");

        $deleteImages = $this->deleteImagesCode('$this->record');
        $labelPhp = $this->php($this->label);
        $deletedPhp = $this->php($this->label.' supprimé(e).');

        return <<<BLADE
        <?php

        use App\Models\\{$m};
        use Flux\Flux;
        use Illuminate\Support\Facades\Storage;
        use Livewire\Attributes\Locked;
        use Livewire\Attributes\Title;
        use Livewire\Component;

        new #[Title({$labelPhp})] class extends Component {
            #[Locked]
            public {$m} \$record;

            public function mount({$m} \${$v}): void
            {
                \$this->authorize('view', \${$v});
                \$this->record = \${$v};
            }

            public function delete(): void
            {
                \$this->authorize('delete', \$this->record);
        {$deleteImages}
                \$this->record->delete();

                Flux::toast(variant: 'success', text: {$deletedPhp});

                \$this->redirectRoute('{$s}.index', navigate: true);
            }
        }; ?>

        <section class="w-full max-w-3xl space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:link :href="route('{$s}.index')" wire:navigate class="text-sm">&larr; {$this->plural}</flux:link>
                    <flux:heading size="xl" level="1" class="mt-2">{$heading}</flux:heading>
                    <flux:text class="mt-1">
                        Par {{ \$record->user?->name }} · {{ \$record->created_at->format('d/m/Y à H:i') }}
                    </flux:text>
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
            </flux:card>
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

        $createTest = $hasRequiredImage ? '' : "\ntest({$labelPhp}, function () {\n"
            ."    \$user = User::factory()->create();\n\n"
            ."    Livewire::actingAs(\$user)\n"
            ."        ->test('pages::{$s}.form')\n"
            .($sets === '' ? '' : $sets."\n")
            ."        ->call('save')\n"
            ."        ->assertHasNoErrors();\n\n"
            ."    expect({$m}::where('user_id', \$user->id)->count())->toBe(1);\n"
            ."});\n";

        return <<<PHP
        <?php

        use App\Models\\{$m};
        use App\Models\User;
        use Livewire\Livewire;

        test('un invité ne peut pas accéder aux {$s}', function () {
            \$this->get(route('{$s}.index'))->assertRedirect(route('login'));
        });

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

        PHP;
    }

    private function routes(): string
    {
        $s = $this->slug;
        $v = $this->var;

        return "    Route::livewire('{$s}', 'pages::{$s}.index')->name('{$s}.index');\n"
            ."    Route::livewire('{$s}/create', 'pages::{$s}.form')->name('{$s}.create');\n"
            ."    Route::livewire('{$s}/{".$v."}', 'pages::{$s}.show')->name('{$s}.show');\n"
            ."    Route::livewire('{$s}/{".$v."}/edit', 'pages::{$s}.form')->name('{$s}.edit');\n";
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
            default => "ucfirst(fake('fr_FR')->words(3, true))",
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

        $markerLine = collect(explode("\n", $source))->first(fn ($line) => str_contains($line, $marker));

        if ($markerLine === null) {
            $this->components->warn("Marqueur « {$marker} » absent de {$this->relative($path)} : ajout manuel nécessaire.");

            return;
        }

        File::put($path, str_replace($markerLine, $content."\n".$markerLine, $source));
        $this->components->task($this->relative($path).' (mis à jour)');
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/\\');
    }
}
