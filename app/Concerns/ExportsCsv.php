<?php

namespace App\Concerns;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Stringable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV réutilisable pour un composant Livewire (Excel : BOM UTF-8, séparateur « ; »).
 * L'autorisation est obligatoire : pas de Policy qui accepte, pas de fichier.
 *
 *   public function export(): StreamedResponse
 *   {
 *       return $this->streamCsv('viewAny', Incident::class, Incident::with('user'), [
 *           'Titre' => fn (Incident $s) => $s->titre,
 *           'Auteur' => fn (Incident $s) => $s->user?->name,
 *       ], 'incidents');
 *   }
 *
 * La requête est lue par paquets (chunkById) : ne pas y mettre d'orderBy.
 */
trait ExportsCsv
{
    /**
     * @param  Model|class-string<Model>  $subject  objet ou classe passé à la Policy
     * @param  Builder<Model>  $query
     * @param  array<string, Closure(Model): mixed>  $columns  en-tête => valeur de la colonne pour une ligne
     */
    protected function streamCsv(string $ability, Model|string $subject, Builder $query, array $columns, string $filename, int $chunkSize = 200): StreamedResponse
    {
        Gate::authorize($ability, $subject);

        $name = Str::slug($filename) ?: 'export';

        return response()->streamDownload(function () use ($query, $columns, $chunkSize): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            $this->writeCsvRow($out, array_keys($columns));

            (clone $query)->chunkById($chunkSize, function ($rows) use ($out, $columns): void {
                foreach ($rows as $row) {
                    $this->writeCsvRow($out, array_map(fn (Closure $value): mixed => $value($row), array_values($columns)));
                }
            });

            fclose($out);
        }, $name.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $out
     * @param  array<int, mixed>  $values
     */
    private function writeCsvRow($out, array $values): void
    {
        fputcsv($out, array_map(fn (mixed $value): string => $this->csvCell($value), $values), ';', '"', '', "\n");
    }

    /**
     * Texte d'une cellule. Les textes (pas les nombres) qui commencent par = + - @ sont neutralisés
     * (sinon Excel les exécute comme une formule : injection CSV).
     */
    private function csvCell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Oui' : 'Non',
            $value instanceof DateTimeInterface => $value->format('d/m/Y H:i'),
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };

        $isText = is_string($value) || $value instanceof Stringable;

        return $isText && $text !== '' && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }
}
