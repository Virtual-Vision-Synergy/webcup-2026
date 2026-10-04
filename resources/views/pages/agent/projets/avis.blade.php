<?php

use App\Models\AvisProjet;
use App\Models\Projet;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F66 : synthèse des avis des habitants sur un projet (répartition + commentaires), agents et admins (ProjetPolicy::voirAvis).
 * Les noms des habitants ne sont pas affichés : seule l'opinion compte pour la synthèse.
 */
new #[Layout('layouts::agent'), Title('Espace agent — Avis sur un projet')] class extends Component {
    use WithPagination;

    #[Locked]
    public Projet $record;

    #[Url(except: '')]
    public string $filtrePosition = '';

    public function mount(Projet $projet): void
    {
        Gate::authorize('viewAgentSpace');
        $this->authorize('voirAvis', $projet);
        $this->record = $projet;
    }

    public function updatedFiltrePosition(): void
    {
        $this->resetPage();
    }

    public function basculerConsultation(): void
    {
        $this->authorize('update', $this->record);

        $this->record->consultation_ouverte = ! $this->record->consultation_ouverte;
        $this->record->save();

        Flux::toast(variant: 'success', text: $this->record->consultation_ouverte ? __('Consultation ouverte.') : __('Consultation close.'));
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function repartition(): array
    {
        $this->authorize('voirAvis', $this->record);

        $parPosition = $this->record->avis()->selectRaw('position, count(*) as total')->groupBy('position')->pluck('total', 'position');

        return collect(AvisProjet::POSITION_OPTIONS)->mapWithKeys(fn (string $position): array => [$position => (int) ($parPosition[$position] ?? 0)])->all();
    }

    #[Computed]
    public function commentaires(): LengthAwarePaginator
    {
        $this->authorize('voirAvis', $this->record);

        return $this->record->avis()
            ->whereNotNull('commentaire')
            ->when(in_array($this->filtrePosition, AvisProjet::POSITION_OPTIONS, true), fn ($query) => $query->where('position', $this->filtrePosition))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(15);
    }
}; ?>

@php
    $total = array_sum($this->repartition);
    $couleurs = ['pour' => 'bg-green', 'contre' => 'bg-magenta', 'sans_avis' => 'bg-cyan'];
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Projets de la ville' => route('projets.index'), $record->titre => route('projets.show', $record), 'Avis' => null]"
        title="Avis des habitants"
        :subtitle="$record->titre.' · '.$total.' avis'"
    >
        <x-slot:meta>
            <div class="mt-3">
                <x-tn.status-badge :etat="$record->consultation_ouverte ? 'normal' : 'info'">{{ $record->consultation_ouverte ? __('Consultation ouverte') : __('Consultation close') }}</x-tn.status-badge>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button wire:click="basculerConsultation" :icon="$record->consultation_ouverte ? 'lock-closed' : 'lock-open'" wire:confirm="{{ $record->consultation_ouverte ? __('Clore la consultation ? Les habitants ne pourront plus donner ni modifier leur avis.') : __('Ouvrir la consultation aux habitants ?') }}">
                    {{ $record->consultation_ouverte ? __('Clore la consultation') : __('Ouvrir la consultation') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    {{-- Répartition --}}
    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-4">{{ __('Répartition') }}</x-tn.section-label>
        @if ($total === 0)
            <p class="text-ink-2">{{ __('Aucun avis pour le moment.') }}</p>
        @else
            <div class="mb-4 flex h-3 w-full overflow-hidden rounded-full bg-line" role="img" aria-label="{{ collect($this->repartition)->map(fn ($n, $p) => AvisProjet::POSITION_LABELS[$p].' : '.$n)->implode(', ') }}">
                @foreach ($this->repartition as $position => $nombre)
                    @if ($nombre > 0)
                        <div class="h-full {{ $couleurs[$position] }}" style="width: {{ round($nombre * 100 / $total, 1) }}%"></div>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="grid grid-cols-3 gap-3">
            @foreach ($this->repartition as $position => $nombre)
                <button
                    type="button"
                    wire:click="$set('filtrePosition', '{{ $filtrePosition === $position ? '' : $position }}')"
                    aria-pressed="{{ $filtrePosition === $position ? 'true' : 'false' }}"
                    @class(['rounded-md border p-4 text-start transition hover:border-cyan', 'border-cyan ring-2 ring-cyan' => $filtrePosition === $position, 'border-line' => $filtrePosition !== $position])
                >
                    <x-tn.status-badge :etat="AvisProjet::POSITION_BADGES[$position]">{{ AvisProjet::POSITION_LABELS[$position] }}</x-tn.status-badge>
                    <span class="tn-display mt-2 block text-2xl font-semibold text-ink">{{ $nombre }}</span>
                    <span class="font-mono text-xs text-ink-2">{{ $total > 0 ? round($nombre * 100 / $total) : 0 }} %</span>
                </button>
            @endforeach
        </div>
    </x-tn.surface>

    {{-- Commentaires --}}
    <x-tn.surface>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-tn.section-label as="h2">{{ __('Commentaires') }}</x-tn.section-label>
            <div class="flex items-center gap-2">
                <flux:select wire:model.live="filtrePosition" aria-label="{{ __('Filtrer par réponse') }}" class="sm:max-w-52">
                    <flux:select.option value="">{{ __('Réponse : toutes') }}</flux:select.option>
                    @foreach (AvisProjet::POSITION_LABELS as $valeur => $libelle)
                        <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                    @endforeach
                </flux:select>
                <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
            </div>
        </div>

        @if ($this->commentaires->isEmpty())
            <x-tn.empty icon="chat-bubble-bottom-center-text" title="Aucun commentaire" :text="$filtrePosition !== '' ? 'Aucun commentaire pour cette réponse.' : 'Les habitants n’ont pas encore laissé de commentaire.'" />
        @else
            <ul class="space-y-3">
                @foreach ($this->commentaires as $avis)
                    <li wire:key="avis-{{ $avis->id }}" class="rounded-md border border-line p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tn.status-badge :etat="$avis->positionBadge()">{{ $avis->positionLabel() }}</x-tn.status-badge>
                            <span class="font-mono text-xs text-ink-2">{{ $avis->enregistreLe() }}</span>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-ink">{{ $avis->commentaire }}</p>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $this->commentaires->links() }}</div>
        @endif
    </x-tn.surface>
</section>
