<?php

use App\Concerns\ExportsCsv;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

function exporteur(): object
{
    return new class
    {
        use ExportsCsv;

        public function exporter(string $ability): StreamedResponse
        {
            return $this->streamCsv($ability, User::class, User::query(), [
                'Nom' => fn (User $u) => $u->name,
                'E-mail' => fn (User $u) => $u->email,
                'Admin' => fn (User $u) => $u->isAdmin(),
                'Identifiant' => fn (User $u) => $u->id,
            ], 'Utilisateurs du jury', chunkSize: 2);
        }
    };
}

function contenu(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

test('l\'export exige une autorisation', function () {
    Gate::define('exporter-test', fn (User $user) => $user->isAdmin());
    $this->actingAs(User::factory()->create());

    expect(fn () => exporteur()->exporter('exporter-test'))->toThrow(AuthorizationException::class);
});

test('l\'export refuse un visiteur non connecté', function () {
    Gate::define('exporter-test', fn (?User $user) => $user !== null);

    expect(fn () => exporteur()->exporter('exporter-test'))->toThrow(AuthorizationException::class);
});

test('l\'export produit un CSV avec BOM UTF-8, séparateur point-virgule et toutes les lignes', function () {
    Gate::define('exporter-test', fn (User $user) => $user->isAdmin());
    $admin = User::factory()->admin()->create(['name' => 'Rakoto Éloïse', 'email' => 'eloise@example.com']);
    User::factory()->count(4)->create();
    $this->actingAs($admin);

    $response = exporteur()->exporter('exporter-test');
    $csv = contenu($response);

    expect($csv)->toStartWith("\xEF\xBB\xBF".'Nom;E-mail;Admin;Identifiant'."\n")
        ->and($csv)->toContain('"Rakoto Éloïse";eloise@example.com;Oui;'.$admin->id)
        ->and(substr_count($csv, "\n"))->toBe(6) // en-tête + 5 lignes, malgré des paquets de 2
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('utilisateurs-du-jury-'.now()->format('Ymd').'.csv');
});

test('l\'export neutralise les formules (injection CSV) mais pas les nombres', function () {
    Gate::define('exporter-test', fn (User $user) => true);
    $this->actingAs(User::factory()->create(['name' => '=HYPERLINK("http://evil.test")', 'email' => '-cmd@example.com']));

    $csv = contenu(exporteur()->exporter('exporter-test'));

    expect($csv)->toContain("'=HYPERLINK")
        ->and($csv)->toContain("'-cmd@example.com")
        ->and($csv)->not->toContain(';=HYPERLINK')
        ->and($csv)->not->toContain("'1\n");
});
