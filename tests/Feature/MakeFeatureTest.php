<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/*
 * Le générateur écrit dans le vrai projet : on sauvegarde les fichiers à marqueurs
 * et on supprime tout ce qui a été généré après chaque test.
 */

/** Modèles de test créés par ces tests : tout ce qui les concerne est supprimé après chaque test. */
const GEN_TEST_MODELS = ['GenTestLieu', 'GenTestNote', 'GenTestZone', 'GenTestFiche'];

beforeEach(function () {
    $this->markerFiles = collect([
        base_path('routes/features.php'),
        resource_path('views/layouts/app/sidebar.blade.php'),
        database_path('seeders/DatabaseSeeder.php'),
    ])->mapWithKeys(fn (string $path) => [$path => File::get($path)])->all();
});

afterEach(function () {
    foreach ($this->markerFiles as $path => $content) {
        File::put($path, $content);
    }

    foreach (GEN_TEST_MODELS as $model) {
        $slug = Str::kebab(Str::pluralStudly($model));
        $table = Str::snake(Str::pluralStudly($model));

        File::delete([
            app_path("Models/{$model}.php"),
            app_path("Policies/{$model}Policy.php"),
            database_path("factories/{$model}Factory.php"),
            base_path("tests/Feature/{$model}Test.php"),
            ...File::glob(database_path("migrations/*_create_{$table}_table.php")),
        ]);
        File::deleteDirectory(resource_path("views/pages/{$slug}"));
        File::deleteDirectory(app_path('Filament/Resources/'.Str::pluralStudly($model)));
    }

    // Garde-fou : un test ne doit jamais laisser de fichier généré dans le dépôt.
    foreach (GEN_TEST_MODELS as $model) {
        expect(File::exists(app_path("Models/{$model}.php")))->toBeFalse()
            ->and(File::isDirectory(app_path('Filament/Resources/'.Str::pluralStudly($model))))->toBeFalse();
    }
});

/**
 * Génère un modèle lié (GenTestZone) puis GenTestFiche avec les options demandées.
 *
 * @param  array<string, mixed>  $options
 */
function genererFiche(array $options): void
{
    test()->artisan('make:feature', ['name' => 'GenTestZone', '--fields' => 'nom:string'])->assertSuccessful();

    test()->artisan('make:feature', [
        'name' => 'GenTestFiche',
        '--fields' => 'titre:string,description:text?,niveau:enum(faible/moyen/critique)',
        ...$options,
    ])->assertSuccessful();
}

function lintPhp(string ...$files): void
{
    foreach ($files as $file) {
        exec('php -l '.escapeshellarg($file).' 2>&1', $output, $code);
        expect($code)->toBe(0, $file."\n".implode("\n", $output));
        $output = [];
    }
}

test('le générateur ajoute trait et cartes quand l\'entité a latitude et longitude', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestLieu',
        '--fields' => 'nom:string,latitude:decimal?,longitude:decimal?',
    ])->assertSuccessful();

    $pages = resource_path('views/pages/gen-test-lieus');

    expect(File::get(app_path('Models/GenTestLieu.php')))
        ->toContain('use App\Models\Concerns\HasCoordinates;')
        ->toContain('use HasCoordinates;');

    expect(File::get("{$pages}/form.blade.php"))
        ->toContain('<x-carte mode="choix"')
        ->toContain("'latitude' => ['nullable', 'numeric', 'between:-90,90']")
        ->toContain("'longitude' => ['nullable', 'numeric', 'between:-180,180']");

    expect(File::get("{$pages}/show.blade.php"))
        ->toContain('<x-carte :points="[$record->pointCarte((string) $record->nom)]"');

    expect(File::get("{$pages}/index.blade.php"))
        ->toContain('public function points(): array')
        ->toContain('->geolocalises()')
        ->toContain('<details wire:ignore.self')
        ->toContain('<x-carte :points="$this->points"');
});

test('le générateur n\'ajoute ni trait ni carte sans coordonnées', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestNote',
        '--fields' => 'titre:string,montant:decimal',
    ])->assertSuccessful();

    $pages = resource_path('views/pages/gen-test-notes');
    $generated = File::get(app_path('Models/GenTestNote.php'))
        .File::get("{$pages}/index.blade.php")
        .File::get("{$pages}/form.blade.php")
        .File::get("{$pages}/show.blade.php");

    expect($generated)
        ->not->toContain('HasCoordinates')
        ->not->toContain('x-carte')
        ->not->toContain('function points()')
        ->toContain('protected function filteredQuery(): Builder');
});

test('le générateur produit du PHP valide avec des coordonnées', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestLieu',
        '--fields' => 'nom:string,niveau:enum(bas/haut),photo:image?,latitude:decimal?,longitude:decimal?',
    ])->assertSuccessful();

    $files = [
        app_path('Models/GenTestLieu.php'),
        ...File::glob(resource_path('views/pages/gen-test-lieus/*.blade.php')),
    ];

    foreach ($files as $file) {
        exec('php -l '.escapeshellarg($file).' 2>&1', $output, $code);
        expect($code)->toBe(0, implode("\n", $output));
    }
});

test('--belongs-to génère clé étrangère, relation, select validé, filtre et chargement anticipé', function () {
    genererFiche(['--belongs-to' => ['GenTestZone']]);

    $pages = resource_path('views/pages/gen-test-fiches');
    $migration = File::get(File::glob(database_path('migrations/*_create_gen_test_fiches_table.php'))[0]);
    $model = File::get(app_path('Models/GenTestFiche.php'));

    expect($migration)->toContain("\$table->foreignId('gen_test_zone_id')->constrained()->cascadeOnDelete();");
    expect($model)
        ->toContain('public function genTestZone(): BelongsTo')
        ->toContain("'gen_test_zone_id'")
        ->not->toContain("'user_id'");
    expect(File::get(database_path('factories/GenTestFicheFactory.php')))->toContain('GenTestZone::factory()');
    expect(File::get("{$pages}/form.blade.php"))
        ->toContain("Rule::exists(GenTestZone::class, 'id')")
        ->toContain('<flux:select wire:model="gen_test_zone_id"')
        ->toContain('$this->genTestZoneOptions');
    expect(File::get("{$pages}/index.blade.php"))
        ->toContain("->with(['user', 'genTestZone'])")
        ->toContain('filterGenTestZoneId');
    expect(File::get("{$pages}/show.blade.php"))->toContain('$record->genTestZone?->nom');
    expect(File::get(base_path('tests/Feature/GenTestFicheTest.php')))->toContain('preventLazyLoading');

    lintPhp(app_path('Models/GenTestFiche.php'), ...File::glob("{$pages}/*.blade.php"));
});

test('--belongs-to=Zone? rend la relation facultative', function () {
    genererFiche(['--belongs-to' => ['GenTestZone?']]);

    $migration = File::get(File::glob(database_path('migrations/*_create_gen_test_fiches_table.php'))[0]);

    expect($migration)->toContain("foreignId('gen_test_zone_id')->nullable()->constrained()->cascadeOnDelete()");
    expect(File::get(resource_path('views/pages/gen-test-fiches/form.blade.php')))
        ->toContain("['nullable', Rule::exists(GenTestZone::class, 'id')]");
});

test('--belongs-to refuse un modèle inexistant, User ou un doublon, sans rien générer', function () {
    foreach (['GenTestInexistant', 'User'] as $model) {
        $this->artisan('make:feature', [
            'name' => 'GenTestFiche',
            '--fields' => 'titre:string',
            '--belongs-to' => [$model],
        ])->assertFailed();
    }

    $this->artisan('make:feature', ['name' => 'GenTestZone', '--fields' => 'nom:string'])->assertSuccessful();
    $this->artisan('make:feature', [
        'name' => 'GenTestFiche',
        '--fields' => 'titre:string',
        '--belongs-to' => ['GenTestZone', 'GenTestZone'],
    ])->assertFailed();

    expect(File::exists(app_path('Models/GenTestFiche.php')))->toBeFalse()
        ->and(File::glob(database_path('migrations/*_create_gen_test_fiches_table.php')))->toBe([])
        ->and(File::get(base_path('routes/features.php')))->not->toContain('gen-test-fiches');
});

test('la migration d\'une entité liée est postérieure à celle du modèle lié', function () {
    genererFiche(['--belongs-to' => ['GenTestZone']]);

    $zone = basename(File::glob(database_path('migrations/*_create_gen_test_zones_table.php'))[0]);
    $fiche = basename(File::glob(database_path('migrations/*_create_gen_test_fiches_table.php'))[0]);

    expect(strcmp($zone, $fiche))->toBeLessThan(0);
});

test('--statut génère colonne, constante, badge et transition réservée à l\'admin', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestFiche',
        '--fields' => 'titre:string',
        '--statut' => 'en_attente/valide/refuse',
    ])->assertSuccessful();

    $pages = resource_path('views/pages/gen-test-fiches');
    $model = File::get(app_path('Models/GenTestFiche.php'));

    expect(File::get(File::glob(database_path('migrations/*_create_gen_test_fiches_table.php'))[0]))
        ->toContain("\$table->string('statut', 50)->default('en_attente')->index();");
    expect($model)
        ->toContain("public const STATUT_OPTIONS = ['en_attente', 'valide', 'refuse'];")
        ->toContain('public function changerStatut(string $statut): void')
        ->toContain("#[Fillable(['titre'])]");
    expect(File::get(app_path('Policies/GenTestFichePolicy.php')))
        ->toContain('public function changerStatut(User $user, GenTestFiche $genTestFiche): bool')
        ->toContain('return $user->isAdmin();');
    expect(File::get("{$pages}/index.blade.php"))->toContain('filterStatut')->toContain('couleurStatut()');
    expect(File::get("{$pages}/show.blade.php"))->toContain("authorize('changerStatut', \$this->record)");
    expect(File::get("{$pages}/form.blade.php"))->not->toContain('statut');
    expect(File::get(base_path('tests/Feature/GenTestFicheTest.php')))
        ->toContain('un utilisateur ne peut pas changer le statut de sa propre fiche');

    lintPhp(app_path('Models/GenTestFiche.php'), ...File::glob("{$pages}/*.blade.php"));
});

test('--statut exige au moins deux valeurs et refuse un champ « statut »', function () {
    $this->artisan('make:feature', ['name' => 'GenTestFiche', '--fields' => 'titre:string', '--statut' => 'seul'])->assertFailed();
    $this->artisan('make:feature', ['name' => 'GenTestFiche', '--fields' => 'titre:string,statut:string', '--statut' => 'a/b'])->assertFailed();

    expect(File::exists(app_path('Models/GenTestFiche.php')))->toBeFalse();
});

test('--public ouvre la liste et le détail aux invités et garde le reste protégé', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestFiche',
        '--fields' => 'titre:string',
        '--public' => true,
    ])->assertSuccessful();

    $routes = File::get(base_path('routes/features.php'));
    expect($routes)->toContain("Route::livewire('gen-test-fiches', 'pages::gen-test-fiches.index')");
    expect(Str::before(Str::after($routes, "Route::middleware(['auth'])->group"), '// make:feature:routes'))
        ->toContain("'gen-test-fiches.create'")
        ->toContain("'gen-test-fiches.edit'")
        ->not->toContain("'gen-test-fiches.index'")
        ->not->toContain("'gen-test-fiches.show'");
    expect(Str::after($routes, 'Route::group([], function () {'))->toContain("'gen-test-fiches.show'")->toContain('whereNumber');
    expect(File::get(app_path('Policies/GenTestFichePolicy.php')))
        ->toContain('public function viewAny(?User $user): bool')
        ->toContain('public function create(User $user): bool');
    expect(File::get(resource_path('views/pages/gen-test-fiches/index.blade.php')))
        ->toContain("#[Layout('layouts::public'")
        ->toContain('@auth')
        ->toContain('@guest');
    expect(File::get(base_path('tests/Feature/GenTestFicheTest.php')))
        ->toContain('un invité voit la liste')
        ->toContain('un invité est redirigé vers la connexion sur le formulaire');
});

test('--filament génère la ressource sans champs sensibles éditables', function () {
    $this->artisan('make:feature', [
        'name' => 'GenTestFiche',
        '--fields' => 'titre:string,niveau:enum(bas/haut)',
        '--statut' => 'en_attente/valide',
        '--filament' => true,
    ])->assertSuccessful();

    $dir = app_path('Filament/Resources/GenTestFiches');

    foreach (['GenTestFicheResource', 'Schemas/GenTestFicheForm', 'Tables/GenTestFichesTable', 'Pages/ListGenTestFiches', 'Pages/CreateGenTestFiche', 'Pages/ViewGenTestFiche', 'Pages/EditGenTestFiche'] as $file) {
        expect(File::exists("{$dir}/{$file}.php"))->toBeTrue($file);
        lintPhp("{$dir}/{$file}.php");
    }

    expect(File::get("{$dir}/Schemas/GenTestFicheForm.php"))
        ->not->toContain("'user_id'")
        ->not->toContain("'statut'");
    expect(File::get("{$dir}/Pages/CreateGenTestFiche.php"))->toContain('$record->user_id = max(0, (int) auth()->id());');
    expect(File::get("{$dir}/Tables/GenTestFichesTable.php"))
        ->toContain("Action::make('valider')")
        ->toContain("Action::make('changerStatut')")
        ->toContain("->authorize('changerStatut')");
    expect(File::get(base_path('tests/Feature/GenTestFicheTest.php')))
        ->toContain('accède pas à la ressource Filament');
});

test('sans --filament, aucune ressource Filament n\'est générée', function () {
    $this->artisan('make:feature', ['name' => 'GenTestNote', '--fields' => 'titre:string'])->assertSuccessful();

    expect(File::isDirectory(app_path('Filament/Resources/GenTestNotes')))->toBeFalse();
});

test('les quatre options se combinent et leurs tests générés passent', function () {
    genererFiche([
        '--belongs-to' => ['GenTestZone'],
        '--statut' => 'en_attente/valide/refuse',
        '--public' => true,
        '--filament' => true,
    ]);

    lintPhp(
        app_path('Models/GenTestFiche.php'),
        app_path('Policies/GenTestFichePolicy.php'),
        database_path('factories/GenTestFicheFactory.php'),
        base_path('tests/Feature/GenTestFicheTest.php'),
        ...File::glob(resource_path('views/pages/gen-test-fiches/*.blade.php')),
        ...File::glob(app_path('Filament/Resources/GenTestFiches/{,*/}*.php'), GLOB_BRACE),
    );

    // Les marqueurs restent en place et les blocs sont insérés une seule fois.
    $routes = File::get(base_path('routes/features.php'));
    expect(substr_count($routes, '// make:feature:routes-public'))->toBe(1)
        ->and(substr_count($routes, "name('gen-test-fiches.index')"))->toBe(1)
        ->and(substr_count($routes, '// make:feature:routes'))->toBe(2);

    // Les tests générés tournent dans un processus à part (SQLite en mémoire, indépendant de la base de ce test).
    $result = Process::path(base_path())
        ->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''])
        ->timeout(600)
        ->run([PHP_BINARY, base_path('vendor/bin/pest'), '--compact', base_path('tests/Feature/GenTestFicheTest.php')]);

    expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
});
