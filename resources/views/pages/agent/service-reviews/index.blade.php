<?php

use App\Models\Service;
use App\Models\ServiceReview;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F76 : avis des habitants sur les services couverts par l'agent (F70 ; admin : tous).
 * Répondre, masquer avec motif, réafficher : ServiceReviewPolicy dans chaque action (agent d'un autre service → 403).
 */
new #[Layout('layouts::agent'), Title('Espace agent — Avis des habitants')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $filterService = '';

    #[Url(except: '')]
    public string $filterRating = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    /** Avis en cours de traitement dans la modale (réponse ou masquage). */
    #[Locked]
    public ?int $selectedId = null;

    public string $response = '';

    public string $hiddenReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
        $this->authorize('moderate', ServiceReview::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filterService', 'filterRating', 'filterStatus'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        $user = auth()->user();

        return Service::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereKey($user->serviceIds()))
            ->orderBy('nom')
            ->get(['id', 'nom']);
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('moderate', ServiceReview::class);

        return ServiceReview::query()
            ->traitablesPar(auth()->user())
            ->with(['service:id,nom,slug', 'user:id,name'])
            ->when($this->filterService !== '', fn ($query) => $query->where('service_id', (int) $this->filterService))
            ->when(in_array($this->filterRating, ['1', '2', '3', '4', '5'], true), fn ($query) => $query->where('rating', (int) $this->filterRating))
            ->when($this->filterStatus === 'publie', fn ($query) => $query->whereNull('hidden_at'))
            ->when($this->filterStatus === 'masque', fn ($query) => $query->whereNotNull('hidden_at'))
            ->when($this->filterStatus === 'sans_reponse', fn ($query) => $query->whereNull('response'))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(15);
    }

    public function ouvrirReponse(int $id): void
    {
        $review = $this->trouver($id);
        $this->authorize('respond', $review);

        $this->resetValidation();
        $this->selectedId = $review->id;
        $this->response = (string) $review->response;
        $this->modal('repondre-avis')->show();
    }

    public function repondre(): void
    {
        $review = $this->trouver((int) $this->selectedId);
        $this->authorize('respond', $review);

        $this->validate(
            ['response' => ['required', 'string', 'min:5', 'max:2000']],
            [
                'response.required' => 'Rédigez la réponse du service.',
                'response.min' => 'La réponse doit faire au moins :min caractères.',
                'response.max' => 'La réponse ne doit pas dépasser :max caractères.',
            ],
        );

        $review->repondre(auth()->user(), $this->response);
        $this->fermer('repondre-avis');

        Flux::toast(variant: 'success', text: 'Réponse publiée sous l’avis. L’habitant est prévenu.');
    }

    public function ouvrirMasquage(int $id): void
    {
        $review = $this->trouver($id);
        $this->authorize('hide', $review);

        $this->resetValidation();
        $this->selectedId = $review->id;
        $this->hiddenReason = '';
        $this->modal('masquer-avis')->show();
    }

    public function masquer(): void
    {
        $review = $this->trouver((int) $this->selectedId);
        $this->authorize('hide', $review);

        $this->validate(
            ['hiddenReason' => ['required', Rule::in(ServiceReview::HIDDEN_REASON_OPTIONS)]],
            ['hiddenReason.required' => 'Choisissez le motif du masquage.', 'hiddenReason.in' => 'Choisissez un motif dans la liste.'],
        );

        $review->masquer(auth()->user(), $this->hiddenReason);
        $this->fermer('masquer-avis');

        Flux::toast(text: 'Avis masqué : il n’apparaît plus sur la fiche du service. L’auteur est prévenu.');
    }

    public function reafficher(int $id): void
    {
        $review = $this->trouver($id);
        $this->authorize('unhide', $review);

        $review->reafficher();
        unset($this->items);

        Flux::toast(variant: 'success', text: 'Avis de nouveau visible sur la fiche du service.');
    }

    private function trouver(int $id): ServiceReview
    {
        return ServiceReview::query()->with('service:id,nom,slug')->findOrFail($id);
    }

    private function fermer(string $modale): void
    {
        $this->modal($modale)->close();
        $this->reset('selectedId', 'response', 'hiddenReason');
        unset($this->items);
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Avis des habitants' => null]"
        title="Avis des habitants"
        subtitle="Avis laissés sur les services que vous couvrez : répondez, ou masquez un commentaire abusif."
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:select wire:model.live="filterService" aria-label="Filtrer par service" class="sm:max-w-64">
            <flux:select.option value="">Service : tous</flux:select.option>
            @foreach ($this->services as $service)
                <flux:select.option :value="$service->id">{{ $service->nom }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterRating" aria-label="Filtrer par note" class="sm:max-w-44">
            <flux:select.option value="">Note : toutes</flux:select.option>
            @foreach (ServiceReview::RATING_LABELS as $note => $libelle)
                <flux:select.option :value="$note">{{ $note }} / 5 — {{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filterStatus" aria-label="Filtrer par statut" class="sm:max-w-48">
            <flux:select.option value="">Statut : tous</flux:select.option>
            <flux:select.option value="publie">Publiés</flux:select.option>
            <flux:select.option value="masque">Masqués</flux:select.option>
            <flux:select.option value="sans_reponse">Sans réponse</flux:select.option>
        </flux:select>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="star" title="Aucun avis à afficher" text="Aucun avis ne correspond à ces filtres pour les services que vous couvrez." />
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $item)
                <li wire:key="avis-agent-{{ $item->id }}" class="space-y-2 rounded-md border border-line p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-ink">{{ $item->service->nom }}</span>
                        <span class="text-sm font-semibold text-ink">· {{ $item->libelleNote() }}</span>
                        @if ($item->verified_usage)
                            <x-tn.status-badge etat="normal" icon="check-badge">A utilisé ce service</x-tn.status-badge>
                        @endif
                        @if ($item->estMasque())
                            <x-tn.status-badge etat="alerte" icon="eye-slash">Masqué · {{ $item->libelleMotifMasquage() }}</x-tn.status-badge>
                        @endif
                    </div>
                    <p class="whitespace-pre-line text-sm text-ink">{{ $item->comment }}</p>
                    <p class="font-mono text-xs text-ink-2">{{ $item->auteurPublic() }} · {{ ServiceReview::dateLongue($item->updated_at) }}</p>
                    @if ($item->response)
                        <div class="border-s-2 border-cyan ps-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-cyan">Réponse du service</p>
                            <p class="whitespace-pre-line text-sm text-ink">{{ $item->response }}</p>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2 pt-1">
                        <flux:button size="sm" icon="chat-bubble-left-right" wire:click="ouvrirReponse({{ $item->id }})">{{ $item->response ? 'Modifier la réponse' : 'Répondre' }}</flux:button>
                        @if ($item->estMasque())
                            <flux:button size="sm" icon="eye" wire:click="reafficher({{ $item->id }})">Réafficher</flux:button>
                        @else
                            <flux:button size="sm" variant="danger" icon="eye-slash" wire:click="ouvrirMasquage({{ $item->id }})">Masquer</flux:button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $this->items->links() }}
    @endif

    <flux:modal name="repondre-avis" class="md:w-lg">
        <form wire:submit="repondre" class="space-y-4">
            <flux:heading size="lg">Répondre à l’avis</flux:heading>
            <flux:text>La réponse est publiée sous l’avis, sur la fiche du service. L’habitant est prévenu.</flux:text>
            <flux:textarea wire:model="response" label="Réponse du service" rows="5" maxlength="2000" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Annuler</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Publier la réponse</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="masquer-avis" class="md:w-lg">
        <form wire:submit="masquer" class="space-y-4">
            <flux:heading size="lg">Masquer l’avis</flux:heading>
            <flux:text>L’avis disparaît de la fiche et de la note moyenne. Son auteur le voit toujours, avec le motif.</flux:text>
            <flux:select wire:model="hiddenReason" label="Motif" required>
                <flux:select.option value="">Choisir un motif…</flux:select.option>
                @foreach (ServiceReview::HIDDEN_REASON_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Annuler</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Masquer l’avis</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
