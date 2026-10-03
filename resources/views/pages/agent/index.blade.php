<?php

use App\Services\NovaTerraApiClient;
use App\Services\NovaTerraResult;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Espace agent — Demandes Nova Terra')] class extends Component {
    /** Libellé et couleur de badge par niveau de difficulté. */
    public const DIFFICULTES = [
        1 => ['label' => 'Facile', 'color' => 'green'],
        2 => ['label' => 'Moyenne', 'color' => 'yellow'],
        3 => ['label' => 'Difficile', 'color' => 'orange'],
        4 => ['label' => 'Expert', 'color' => 'red'],
    ];

    /** Heure affichée aux agents (l'application stocke en UTC). */
    public const FUSEAU_AFFICHAGE = 'Indian/Antananarivo';

    #[Url(except: '')]
    public string $difficulte = '';

    #[Url(except: '')]
    public string $arrivee = '';

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    public function resetFilters(): void
    {
        Gate::authorize('viewAgentSpace');

        $this->reset('difficulte', 'arrivee');
    }

    #[Computed]
    public function result(): NovaTerraResult
    {
        Gate::authorize('viewAgentSpace');

        return app(NovaTerraApiClient::class)->requests();
    }

    /**
     * Demandes prêtes à afficher (seuls les champs utiles sont envoyés à la vue).
     *
     * @return list<array{code: string, demandeur: string, type: string, message: string, niveau: int, xp_total: int, xp_disponible: int, arrivee: string, arrivee_libelle: string, ia: bool, groupe: string, ordre: int}>
     */
    #[Computed]
    public function allRows(): array
    {
        $rows = array_map(fn (array $demande): array => $this->toRow($demande), $this->result->requests);

        usort($rows, fn (array $a, array $b): int => [$a['ordre'], $a['code']] <=> [$b['ordre'], $b['code']]);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        return array_values(array_filter($this->allRows, fn (array $row): bool => ($this->difficulte === '' || (string) $row['niveau'] === $this->difficulte)
            && ($this->arrivee === '' || $row['arrivee'] === $this->arrivee)));
    }

    /**
     * Options du filtre « arrivée » construites à partir des données reçues.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function arriveeOptions(): array
    {
        $options = [];

        foreach ($this->allRows as $row) {
            $options[$row['arrivee']] = $row['arrivee'] === 'debut' ? 'Dès le début' : 'Vague '.substr($row['arrivee'], 6);
        }

        uksort($options, fn (string $a, string $b): int => $this->arriveeSortKey($a) <=> $this->arriveeSortKey($b));

        return $options;
    }

    /**
     * @param  array<string, mixed>  $demande
     * @return array{code: string, demandeur: string, type: string, message: string, niveau: int, xp_total: int, xp_disponible: int, arrivee: string, arrivee_libelle: string, ia: bool, groupe: string, ordre: int}
     */
    protected function toRow(array $demande): array
    {
        $niveau = (int) ($demande['difficulty_level'] ?? 0);

        if (! isset(self::DIFFICULTES[$niveau])) {
            $niveau = 0;

            foreach (self::DIFFICULTES as $n => $difficulte) {
                if ($difficulte['label'] === ($demande['difficulty'] ?? null)) {
                    $niveau = $n;
                }
            }
        }

        $vague = (int) ($demande['wave_number'] ?? 0);
        $estDebut = ($demande['arrival_type'] ?? '') !== 'vague' || $vague === 0;
        $delai = $this->formatDelai((string) ($demande['arrival_time'] ?? ''));

        $xpBase = (int) ($demande['xp_base'] ?? 0);
        $xpTotal = (int) ($demande['xp_total'] ?? $xpBase + (int) ($demande['xp_time_bonus'] ?? 0));

        return [
            'code' => (string) ($demande['request_code'] ?? '—'),
            'demandeur' => (string) ($demande['requester_name'] ?? ''),
            'type' => (string) ($demande['requester_type'] ?? ''),
            'message' => (string) ($demande['message_public'] ?? ''),
            'niveau' => $niveau,
            'xp_total' => $xpTotal,
            'xp_disponible' => (int) ($demande['xp_available'] ?? $xpTotal),
            'arrivee' => $estDebut ? 'debut' : 'vague-'.$vague,
            'arrivee_libelle' => $estDebut ? 'Dès le début' : 'Vague '.$vague.($delai !== '' ? ' · '.$delai : ''),
            'ia' => (bool) ($demande['is_ai_request'] ?? false) || (bool) ($demande['is_ai_related'] ?? false),
            'groupe' => (string) ($demande['group_name'] ?? ''),
            'ordre' => (int) ($demande['sort_order'] ?? PHP_INT_MAX),
        ];
    }

    /**
     * « 02:30:00 » = 2 h 30 après le début du concours → « H+2:30 » (pas une heure du jour).
     */
    protected function formatDelai(string $arrivalTime): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $arrivalTime, $m)) {
            return '';
        }

        return 'H+'.(int) $m[1].($m[2] !== '00' ? ':'.$m[2] : '');
    }

    protected function arriveeSortKey(string $arrivee): int
    {
        return $arrivee === 'debut' ? 0 : (int) substr($arrivee, 6);
    }
}; ?>

@php
    $result = $this->result;
    $session = $result->session;
    $majLe = $result->fetchedAt?->timezone($this::FUSEAU_AFFICHAGE)->format('d/m/Y à H:i');
@endphp

<section class="w-full space-y-6">
    <x-tn.breadcrumb :items="['Espace agent' => null]" />

    {{-- D17 : charge de travail en un coup d'œil --}}
    <livewire:compteur-demandes-attente />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Demandes Nova Terra</flux:heading>
            <flux:text class="mt-1">Les demandes reçues de la plateforme de la Ville de Nova Terra : difficulté, XP et arrivée.</flux:text>
        </div>

        @if ($majLe && ! $result->stale)
            <flux:text size="sm">Mis à jour le {{ $majLe }}</flux:text>
        @endif
    </div>

    @if (! $result->available)
        <flux:callout variant="danger" icon="exclamation-triangle" data-test="api-unavailable">
            <flux:callout.heading>L'API Nova Terra ne répond pas.</flux:callout.heading>
            <flux:callout.text>Aucune donnée n'a encore été reçue. Réessayez dans quelques minutes.</flux:callout.text>
        </flux:callout>
    @elseif ($result->stale)
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="api-stale">
            <flux:callout.text>L'API Nova Terra ne répond pas. Données affichées du {{ $majLe }}.</flux:callout.text>
        </flux:callout>
    @endif

    @if ($result->available && $session !== [])
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <flux:card class="space-y-1">
                <flux:text size="sm">Concours</flux:text>
                <flux:heading size="lg">
                    @if (($session['status'] ?? 'none') === 'none')
                        Pas encore démarré
                    @elseif (! empty($session['is_running']))
                        En cours
                    @else
                        Terminé
                    @endif
                </flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm">Vague actuelle</flux:text>
                <flux:heading size="lg">{{ (int) ($session['current_wave'] ?? 0) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm">Demandes visibles</flux:text>
                <flux:heading size="lg">{{ (int) ($session['visible_requests_count'] ?? count($result->requests)) }}</flux:heading>
            </flux:card>
            <flux:card class="space-y-1">
                <flux:text size="sm">Prochaine vague</flux:text>
                <flux:heading size="lg">
                    @if ((int) ($session['next_wave_number'] ?? 0) === 0)
                        Plus de vague prévue
                    @else
                        Vague {{ (int) $session['next_wave_number'] }} dans {{ (int) ($session['minutes_until_next_wave'] ?? 0) }} min
                    @endif
                </flux:heading>
            </flux:card>
        </div>
    @endif

    @if ($result->available)
        <div class="flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="difficulte" label="Difficulté" class="max-w-48">
                <flux:select.option value="">Toutes</flux:select.option>
                @foreach ($this::DIFFICULTES as $niveau => $option)
                    <flux:select.option value="{{ $niveau }}">{{ $option['label'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="arrivee" label="Arrivée" class="max-w-48">
                <flux:select.option value="">Toutes</flux:select.option>
                @foreach ($this->arriveeOptions as $valeur => $libelle)
                    <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($difficulte !== '' || $arrivee !== '')
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
            @endif

            <div wire:loading class="pb-2">
                <flux:icon.loading class="size-5" />
            </div>
        </div>

        @if ($this->rows === [])
            <flux:card class="py-10 text-center">
                <flux:icon.inbox class="mx-auto size-10 text-zinc-400" />
                <flux:heading class="mt-3">Aucune demande à afficher</flux:heading>
                <flux:text class="mt-1">
                    @if ($difficulte !== '' || $arrivee !== '')
                        Aucune demande ne correspond aux filtres choisis.
                    @else
                        L'API n'a encore publié aucune demande.
                    @endif
                </flux:text>
            </flux:card>
        @else
            <flux:card class="overflow-x-auto p-0">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Code</flux:table.column>
                        <flux:table.column>Demandeur</flux:table.column>
                        <flux:table.column class="min-w-72">Besoin</flux:table.column>
                        <flux:table.column>Difficulté</flux:table.column>
                        <flux:table.column>XP</flux:table.column>
                        <flux:table.column>Arrivée</flux:table.column>
                        <flux:table.column>Groupe</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->rows as $row)
                            <flux:table.row :key="$row['code']">
                                <flux:table.cell class="font-mono font-semibold">{{ $row['code'] }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="font-medium">{{ $row['demandeur'] }}</div>
                                    @if ($row['type'] !== '')
                                        <flux:text size="sm">{{ $row['type'] }}</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="!whitespace-normal">
                                    <span title="{{ $row['message'] }}">{{ Str::limit($row['message'], 160) }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if (isset($this::DIFFICULTES[$row['niveau']]))
                                        <flux:badge size="sm" :color="$this::DIFFICULTES[$row['niveau']]['color']">{{ $this::DIFFICULTES[$row['niveau']]['label'] }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">Inconnue</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">
                                    <span class="font-semibold">{{ $row['xp_total'] }}</span>
                                    @if ($row['xp_disponible'] !== $row['xp_total'])
                                        <flux:text size="sm" class="inline">({{ $row['xp_disponible'] }} dispo.)</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        <flux:badge size="sm" :color="$row['arrivee'] === 'debut' ? 'sky' : 'violet'">{{ $row['arrivee_libelle'] }}</flux:badge>
                                        @if ($row['ia'])
                                            <flux:badge size="sm" color="fuchsia" icon="sparkles">IA</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $row['groupe'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    @endif
</section>
