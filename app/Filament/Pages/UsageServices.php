<?php

namespace App\Filament\Pages;

use App\Models\ActionLog;
use App\Models\Quartier;
use App\Models\Service;
use App\Models\User;
use App\Services\UsageServices as StatistiquesUsage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F98 : tableau de bord « Usage des services » (classement, évolution, analyses par règles, export CSV).
 * Réservé aux admins ; les consultations sont comptées de façon anonyme (table vues_services, sans user_id).
 *
 * @property-read StatistiquesUsage $statistiques
 * @property-read Collection<int, LigneUsage> $classement
 *
 * @phpstan-import-type LigneUsage from StatistiquesUsage
 */
class UsageServices extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Usage des services';

    protected static ?string $title = 'Usage des services par les habitants';

    protected static ?string $slug = 'usage-services';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.usage-services';

    /** Filtres (texte : ils viennent du navigateur, revalidés en liste blanche par le service). */
    public string $periode = '30';

    public string $quartier = '';

    public string $categorie = '';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isAdmin();
    }

    public function updated(): void
    {
        abort_unless(static::canAccess(), 403);

        unset($this->statistiques, $this->classement);
    }

    #[Computed]
    public function statistiques(): StatistiquesUsage
    {
        $quartierId = ctype_digit($this->quartier) && Quartier::query()->whereKey((int) $this->quartier)->exists() ? (int) $this->quartier : null;

        return new StatistiquesUsage((int) $this->periode, $quartierId, $this->categorie !== '' ? $this->categorie : null);
    }

    /**
     * @return Collection<int, LigneUsage>
     */
    #[Computed]
    public function classement(): Collection
    {
        return $this->statistiques->classement();
    }

    /**
     * @return array<int, string>
     */
    public function quartiers(): array
    {
        return Quartier::query()->orderBy('nom')->pluck('nom', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function categories(): array
    {
        return Service::CATEGORIE_LABELS;
    }

    /**
     * Courbe SVG (points « x,y ») d'une série de l'évolution, dans un repère 600 × 160.
     *
     * @param  list<array<string, mixed>>  $points
     */
    public function polyligne(array $points, string $serie, int $maximum): string
    {
        $nombre = max(count($points) - 1, 1);

        return collect($points)
            ->map(fn (array $p, int $i): string => round($i * 600 / $nombre, 1).','.round(160 - ($p[$serie] * 150 / max($maximum, 1)), 1))
            ->implode(' ');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exporter')
                ->label('Exporter en CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exporter()),
        ];
    }

    public function exporter(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $stats = $this->statistiques;
        $lignes = $this->classement;

        ActionLog::record('export_usage_services');

        $colonnes = [
            'Rang', 'Service', 'Catégorie', 'Consultations', 'Démarches lancées', 'Démarches terminées',
            'Démarches abandonnées ou refusées', 'Taux d’abandon (%)', 'Évolution vs période précédente (%)',
        ];

        $csv = "\xEF\xBB\xBF"
            .self::ligneCsv(['Période du '.$stats->debut->format('d/m/Y').' au '.$stats->fin->format('d/m/Y')])
            .self::ligneCsv($colonnes);

        foreach ($lignes->values() as $rang => $l) {
            $csv .= self::ligneCsv([
                $rang + 1, $l['nom'], Service::labelCategorie($l['categorie']), $l['consultations'], $l['lancees'],
                $l['terminees'], $l['abandonnees'], $l['taux_abandon'], $l['evolution'],
            ]);
        }

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'usage-services-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Ligne CSV pour Excel (séparateur « ; »). Un texte commençant par = + - @ est neutralisé (injection CSV).
     *
     * @param  array<int, mixed>  $valeurs
     */
    private static function ligneCsv(array $valeurs): string
    {
        return collect($valeurs)
            ->map(function (mixed $valeur): string {
                $texte = is_scalar($valeur) ? (string) $valeur : '';

                if (is_string($valeur) && $texte !== '' && str_contains("=+-@\t\r", $texte[0])) {
                    $texte = "'".$texte;
                }

                return '"'.str_replace('"', '""', $texte).'"';
            })
            ->implode(';')."\n";
    }
}
