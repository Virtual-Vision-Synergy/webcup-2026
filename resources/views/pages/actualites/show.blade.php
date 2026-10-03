<?php

use App\Models\Actualite;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Actualite')] class extends Component {
    #[Locked]
    public Actualite $record;

    public function mount(Actualite $actualite): void
    {
        $this->authorize('view', $actualite);
        $this->record = $actualite;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);
        if ($this->record->image) {
            Storage::disk('public')->delete($this->record->image);
        }
        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Actualite supprimé(e).');

        $this->redirectRoute('actualites.index', navigate: true);
    }
}; ?>

<article class="mx-auto w-full max-w-3xl space-y-8">
    <x-tn.page-header
        label="Fil du Haut Conseil"
        :title="$record->titre"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Actualités' => route('actualites.index'), ($record->titre ?: 'Annonce') => null]"
    >
        <x-slot:meta>
            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-2">
                @if ($record->date)
                    <time datetime="{{ $record->date->toDateString() }}" class="font-mono">{{ $record->date->translatedFormat('l j F Y') }}</time>
                    <span aria-hidden="true">·</span>
                @endif
                <span>Publiée par {{ $record->user?->name }}</span>
            </p>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('actualites.edit', $record)" wire:navigate>Modifier</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="Supprimer définitivement cette annonce ?">Supprimer</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @if ($record->image)
        <img src="{{ Storage::url($record->image) }}" alt="Illustration de l'annonce : {{ $record->titre }}" class="max-h-[420px] w-full rounded-md border border-line object-cover" />
    @endif

    <div class="text-[17px] leading-[1.7] text-ink">
        <p class="whitespace-pre-line">{{ $record->contenu ?? '—' }}</p>
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
        <span class="font-mono text-[11px] uppercase tracking-[.06em] text-ink-2">Mise en ligne · {{ $record->created_at->format('d.m.Y · H:i') }}</span>
        <a href="{{ route('actualites.index') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-cyan hover:underline">← Toutes les actualités</a>
    </footer>
</article>
