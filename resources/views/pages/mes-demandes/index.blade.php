<?php

use App\Models\Signalement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Mes demandes')] class extends Component {
    use WithPagination;

    /** Onglets : '' = toutes, 'en_cours' = encore ouvertes, 'terminees' = résolues ou rejetées. */
    public const ONGLETS = ['' => 'Toutes', 'en_cours' => 'En cours', 'terminees' => 'Terminées'];

    #[Url(except: '')]
    public string $onglet = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Signalement::class);
    }

    public function updatedOnglet(): void
    {
        if (! array_key_exists($this->onglet, self::ONGLETS)) {
            $this->onglet = '';
        }

        $this->resetPage();
    }

    /**
     * Uniquement les demandes de l'utilisateur connecté : le filtre par propriétaire est fait dans la requête.
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Signalement::duCitoyen(auth()->user())
            ->with('etapes')
            ->when($this->onglet === 'en_cours', fn ($query) => $query->whereNotIn('statut', Signalement::STATUTS_TERMINES))
            ->when($this->onglet === 'terminees', fn ($query) => $query->whereIn('statut', Signalement::STATUTS_TERMINES))
            ->latest()
            ->latest('id')
            ->paginate(10);
    }

    /**
     * L'habitant a-t-il déposé au moins une demande (tous onglets confondus) ?
     */
    #[Computed]
    public function aDesDemandes(): bool
    {
        return Signalement::duCitoyen(auth()->user())->exists();
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Mon espace"
        title="Mes demandes"
        subtitle="Retrouvez toutes vos demandes, leur état actuel et les étapes déjà réalisées par la mairie."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes demandes' => null]"
    >
        <x-slot:actions>
            <x-tn.version-simple />
            @can('create', Signalement::class)
                <flux:button variant="primary" icon="plus" :href="route('signalements.create')" class="tn-cta" wire:navigate>
                    Signaler un problème
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @if (! $this->aDesDemandes)
        <x-tn.empty icon="clipboard-document-list" title="Vous n'avez encore déposé aucune demande." text="Un lampadaire cassé, un nid-de-poule, un dépôt sauvage ? Signalez-le : vous suivrez ici chaque étape de son traitement.">
            <flux:button variant="primary" icon="plus" :href="route('signalements.create')" wire:navigate>Signaler un problème</flux:button>
        </x-tn.empty>
    @else
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrer mes demandes">
            @foreach ($this::ONGLETS as $valeur => $libelle)
                <flux:button size="sm" wire:click="$set('onglet', '{{ $valeur }}')" :variant="$onglet === $valeur ? 'primary' : 'outline'" :aria-pressed="$onglet === $valeur ? 'true' : 'false'">
                    {{ $libelle }}
                </flux:button>
            @endforeach
            <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
        </div>

        <p class="text-sm text-ink-2" aria-live="polite">{{ $this->items->total() }} demande(s)</p>

        @if ($this->items->isEmpty())
            <x-tn.empty icon="clipboard-document-list" title="Aucune demande dans cet onglet" text="Choisissez un autre onglet pour retrouver vos demandes." />
        @else
            <ul class="space-y-4" wire:loading.class="opacity-60">
                @foreach ($this->items as $item)
                    @php
                        $chronologie = $item->chronologie();
                        $categorie = Signalement::libelleCategorie($item->categorie);
                    @endphp
                    <li wire:key="demande-{{ $item->id }}">
                        <x-tn.surface>
                            <article aria-labelledby="demande-{{ $item->id }}-titre" class="flex flex-col gap-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 space-y-1">
                                        <h2 id="demande-{{ $item->id }}-titre" class="font-medium text-ink">
                                            <a href="{{ route('mes-demandes.show', $item) }}" wire:navigate class="hover:text-cyan">{{ $categorie }}</a>
                                        </h2>
                                        <p class="text-sm text-ink-2">{{ Str::limit($item->description, 140) }}</p>
                                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-2">
                                            <span class="inline-flex items-center gap-1"><flux:icon.map-pin variant="micro" aria-hidden="true" /> {{ $item->lieu ?: 'Lieu non précisé' }}</span>
                                            <span class="font-mono">Déposée le {{ $item->created_at->copy()->setTimezone(\App\Models\AuditLog::FUSEAU)->format('d.m.Y') }}</span>
                                        </p>
                                    </div>
                                    <div class="shrink-0">
                                        <span class="sr-only">État actuel :</span>
                                        <flux:badge :color="$item->statutCouleur()" size="sm">{{ $item->statutLibelle() }}</flux:badge>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="mb-3 font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">Dernières étapes</h3>
                                    <x-tn.timeline :items="array_slice($chronologie, -3)" />
                                    @if ($item->prochaineEtape())
                                        <p class="mt-3 text-sm text-ink-2">Prochaine étape : {{ $item->prochaineEtape() }}</p>
                                    @endif
                                </div>

                                <div>
                                    <flux:button size="sm" variant="outline" icon="arrow-right" :href="route('mes-demandes.show', $item)" wire:navigate>
                                        Voir tout le suivi<span class="sr-only"> : {{ $categorie }}, {{ $item->lieu }}</span>
                                    </flux:button>
                                </div>
                            </article>
                        </x-tn.surface>
                    </li>
                @endforeach
            </ul>

            <div wire:loading.class="pointer-events-none opacity-60">{{ $this->items->links() }}</div>
        @endif
    @endif
</section>
