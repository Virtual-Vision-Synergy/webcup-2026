<?php

use Illuminate\Support\Facades\File;

/*
 * Le générateur écrit dans le vrai projet : on sauvegarde les fichiers à marqueurs
 * et on supprime tout ce qui a été généré après chaque test.
 */

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

    foreach (['GenTestLieu' => 'gen-test-lieus', 'GenTestNote' => 'gen-test-notes'] as $model => $slug) {
        File::delete([
            app_path("Models/{$model}.php"),
            app_path("Policies/{$model}Policy.php"),
            database_path("factories/{$model}Factory.php"),
            base_path("tests/Feature/{$model}Test.php"),
            ...File::glob(database_path('migrations/*_create_'.str_replace('-', '_', $slug).'_table.php')),
        ]);
        File::deleteDirectory(resource_path("views/pages/{$slug}"));
    }
});

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
