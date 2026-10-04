<?php

namespace App\Filament\Pages;

use App\Models\ActionLog;
use App\Models\User;
use App\Services\RapportActivite as Rapport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F103 : rapport synthétique de l'activité de la plateforme (synthèse en phrases, points d'attention par règles,
 * version imprimable / PDF, export CSV). Réservé aux admins (Gate voirRapportActivite).
 *
 * @property-read Rapport $rapport
 */
class RapportActivite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Rapport d’activité';

    protected static ?string $title = 'Rapport d’activité de la plateforme';

    protected static ?string $slug = 'rapport-activite';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.rapport-activite';

    /** Filtres (texte : ils viennent du navigateur, revalidés par le service). */
    public string $periode = '30';

    public string $du = '';

    public string $au = '';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('voirRapportActivite');
    }

    public function updated(): void
    {
        abort_unless(static::canAccess(), 403);

        unset($this->rapport);
    }

    #[Computed]
    public function rapport(): Rapport
    {
        return new Rapport($this->periode, $this->du, $this->au);
    }

    /**
     * Adresse de la version imprimable, sur la même période.
     */
    public function lienImprimable(): string
    {
        return route('rapport-activite.imprimable', array_filter([
            'periode' => $this->rapport->periode,
            'du' => $this->rapport->periode === 'perso' ? $this->du : null,
            'au' => $this->rapport->periode === 'perso' ? $this->au : null,
        ]));
    }

    public function exporter(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $csv = $this->rapport->csv();

        ActionLog::record('export_rapport_activite');

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'rapport-activite-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
