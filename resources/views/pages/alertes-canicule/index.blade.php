<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\AlerteCanicule;
use App\Services\RecommandationsCanicule;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Alertes canicule')] class extends Component {
    use ThrottlesPerUser;
    use WithPagination;

    #[Url(except: 'personnes_agees')]
    public string $profil = 'personnes_agees';

    /** @var array<int, array{profil: string, lignes: list<string>, source: string}> */
    #[Locked]
    public array $resultats = [];

    public function mount(): void
    {
        $this->authorize('viewAny', AlerteCanicule::class);

        if (! in_array($this->profil, AlerteCanicule::PROFIL_OPTIONS, true)) {
            $this->profil = 'personnes_agees';
        }
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return AlerteCanicule::query()
            ->orderByRaw('case when fin is null or fin >= ? then 0 else 1 end', [today()->toDateString()])
            ->latest('debut')
            ->paginate(10);
    }

    public function recommander(int $id): void
    {
        $alerte = AlerteCanicule::findOrFail($id);
        $this->authorize('view', $alerte);

        $this->validate(['profil' => ['required', Rule::in(AlerteCanicule::PROFIL_OPTIONS)]]);
        $this->throttlePerUser('canicule-recommandations', maxAttempts: 6, decaySeconds: 60);

        $this->resultats[$alerte->id] = ['profil' => $this->profil] + app(RecommandationsCanicule::class)->pour($alerte, $this->profil);
    }

    public function delete(int $id): void
    {
        $alerte = AlerteCanicule::findOrFail($id);
        $this->authorize('delete', $alerte);
        $alerte->delete();

        Flux::toast(variant: 'success', text: 'Alerte supprimée.');
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Agence sanitaire"
        title="Alertes canicule"
        subtitle="Vague de chaleur par secteur, avec des conseils adaptés à votre profil."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Alertes canicule' => null]"
    >
        <x-slot:actions>
            @can('create', App\Models\AlerteCanicule::class)
                <flux:button variant="primary" icon="plus" :href="route('alertes-canicule.create')" wire:navigate>Publier une alerte</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="max-w-sm">
        <flux:select wire:model.live="profil" label="Mon profil">
            @foreach (App\Models\AlerteCanicule::PROFIL_LABELS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @error('throttle')
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$message" />
    @enderror

    @if ($this->items->isEmpty())
        <x-tn.empty icon="sun" title="Aucune alerte canicule" text="Aucun secteur n'est concerné pour le moment." />
    @else
        <ul class="space-y-4">
            @foreach ($this->items as $alerte)
                @php
                    $enCours = $alerte->fin === null || $alerte->fin->greaterThanOrEqualTo(today());
                    $resultat = $resultats[$alerte->id] ?? null;
                @endphp
                <li wire:key="alerte-{{ $alerte->id }}" class="space-y-4 rounded-md border border-line bg-surface p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-ink">{{ $alerte->secteur }}</h2>
                            <p class="font-mono text-sm text-ink-2">
                                Jusqu'à {{ $alerte->temperature_max }} °C ·
                                du {{ $alerte->debut->format('d.m.Y') }}{{ $alerte->fin ? ' au '.$alerte->fin->format('d.m.Y') : '' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-tn.status-badge :etat="$enCours ? ($alerte->niveau === 'vigilance' ? 'perturbe' : 'alerte') : 'normal'" :live="$enCours">
                                {{ $enCours ? (App\Models\AlerteCanicule::NIVEAU_LABELS[$alerte->niveau] ?? $alerte->niveau) : 'Terminée' }}
                            </x-tn.status-badge>
                            @can('update', $alerte)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('alertes-canicule.edit', $alerte)" wire:navigate aria-label="Modifier" />
                            @endcan
                            @can('delete', $alerte)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $alerte->id }})" wire:confirm="Supprimer cette alerte ?" aria-label="Supprimer" />
                            @endcan
                        </div>
                    </div>

                    @if ($alerte->message)
                        <p class="text-ink-2">{{ $alerte->message }}</p>
                    @endif

                    @if ($enCours)
                        <flux:button size="sm" icon="sparkles" wire:click="recommander({{ $alerte->id }})" wire:loading.attr="disabled" wire:target="recommander({{ $alerte->id }})">
                            Conseils pour : {{ App\Models\AlerteCanicule::PROFIL_LABELS[$profil] ?? '' }}
                        </flux:button>
                        <span wire:loading wire:target="recommander({{ $alerte->id }})" class="ms-2 font-mono text-[11px] uppercase text-cyan">Préparation des conseils…</span>
                    @endif

                    @if ($resultat)
                        <div class="rounded-sm border border-line p-4" aria-live="polite">
                            <p class="mb-2 font-semibold text-ink">
                                {{ App\Models\AlerteCanicule::PROFIL_LABELS[$resultat['profil']] ?? '' }}
                                <span class="ms-2 font-mono text-[11px] font-normal uppercase text-ink-2">
                                    {{ $resultat['source'] === 'ia' ? 'Généré par IA' : 'Conseils standards' }}
                                </span>
                            </p>
                            <ul class="list-disc space-y-1 ps-5 text-ink-2">
                                @foreach ($resultat['lignes'] as $ligne)
                                    <li>{{ $ligne }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div>{{ $this->items->links() }}</div>
    @endif
</section>
