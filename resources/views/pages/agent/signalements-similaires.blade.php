<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Signalement;
use App\Notifications\Avis;
use App\Services\RegroupementSignalements;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Espace agent — Demandes similaires')] class extends Component {
    use ThrottlesPerUser;

    #[Url(except: '')]
    public string $filterCategorie = '';

    public function mount(): void
    {
        Gate::authorize('viewAgentSpace');
    }

    /**
     * Groupes de signalements similaires, du plus gros au plus petit.
     *
     * @return Collection<int, Collection<int, Signalement>>
     */
    #[Computed]
    public function groupes(): Collection
    {
        Gate::authorize('viewAgentSpace');

        $categorie = in_array($this->filterCategorie, Signalement::CATEGORIE_OPTIONS, true) ? $this->filterCategorie : null;

        return app(RegroupementSignalements::class)->groupes($categorie);
    }

    /**
     * Applique un même état à tout le groupe (et aux signalements déjà fusionnés dedans), puis prévient chaque habitant.
     * Le groupe est recalculé côté serveur à partir d'un de ses signalements : aucune liste d'ID ne vient du navigateur.
     */
    public function traiterGroupe(int $id, string $statut): void
    {
        Gate::authorize('viewAgentSpace');
        abort_unless(in_array($statut, Signalement::STATUT_OPTIONS, true), 422);

        $groupe = $this->groupeAutorise($id);
        $this->throttlePerUser('groupe-signalements', maxAttempts: 10, decaySeconds: 60);

        $concernes = $groupe->concat(
            Signalement::query()->whereIn('doublon_de_id', $groupe->pluck('id'))->with('user')->get()
        );

        DB::transaction(fn () => $concernes->each(fn (Signalement $signalement) => $signalement->changerStatut($statut)));

        $concernes->each(fn (Signalement $signalement) => $signalement->user?->notify(new Avis(
            'Votre signalement : '.Signalement::libelleStatut($statut),
            [
                'Votre signalement « '.$signalement->auditLabel().' » fait partie d’un groupe de demandes similaires traité par la mairie.',
                'Nouvel état : '.Signalement::libelleStatut($statut).'.',
            ],
            'Voir mon signalement',
            route('signalements.show', $signalement),
        )));

        unset($this->groupes);

        Flux::toast(variant: 'success', text: $concernes->count().' signalement(s) passés à l’état « '.Signalement::libelleStatut($statut).' », habitants prévenus.');
    }

    /**
     * Fusionne le groupe dans sa demande la plus ancienne : les autres y sont rattachées et en prennent l'état.
     */
    public function fusionnerGroupe(int $id): void
    {
        Gate::authorize('viewAgentSpace');

        $groupe = $this->groupeAutorise($id);
        $this->throttlePerUser('groupe-signalements', maxAttempts: 10, decaySeconds: 60);

        /** @var Signalement $principal */
        $principal = $groupe->first();
        $doublons = $groupe->slice(1);

        DB::transaction(fn () => $doublons->each(fn (Signalement $signalement) => $signalement->fusionnerDans($principal)));

        $doublons->each(fn (Signalement $signalement) => $signalement->user?->notify(new Avis(
            'Votre signalement a été regroupé',
            [
                'D’autres habitants ont signalé le même problème : « '.$principal->auditLabel().' ».',
                'Votre signalement est regroupé avec le leur et sera traité en une seule fois.',
            ],
            'Voir mon signalement',
            route('signalements.show', $signalement),
        )));

        unset($this->groupes);

        Flux::toast(variant: 'success', text: $doublons->count().' signalement(s) fusionnés dans la demande n° '.$principal->id.'.');
    }

    /**
     * @return Collection<int, Signalement>
     */
    private function groupeAutorise(int $id): Collection
    {
        $signalement = Signalement::findOrFail($id);
        $groupe = app(RegroupementSignalements::class)->groupeDe($signalement);
        abort_if($groupe === null, 404);

        $groupe->each(fn (Signalement $membre) => $this->authorize('changerStatut', $membre));

        return $groupe;
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Demandes similaires' => null]"
        title="Demandes similaires"
        :subtitle="$this->groupes->count().' groupe(s) de signalements qui parlent du même problème ('.$this->groupes->sum(fn ($groupe) => $groupe->count()).' signalements)'"
    />

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:select wire:model.live="filterCategorie" aria-label="Filtrer par catégorie" class="sm:max-w-56">
            <flux:select.option value="">Catégorie : toutes</flux:select.option>
            @foreach (Signalement::CATEGORIE_OPTIONS as $option)
                <flux:select.option :value="$option">{{ Signalement::libelleCategorie($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <span class="text-sm text-ink-2">Regroupés par catégorie, lieu proche et mots communs. Les plus gros groupes d’abord.</span>
        <span wire:loading class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Mise à jour…</span>
    </div>

    @error('throttle')
        <flux:callout variant="danger" icon="exclamation-circle" :heading="$message" />
    @enderror

    @if ($this->groupes->isEmpty())
        <x-tn.empty icon="squares-2x2" title="Aucune demande similaire" text="Aucun signalement ouvert ne semble décrire le même problème pour le moment." />
    @else
        <ul class="space-y-4">
            @foreach ($this->groupes as $groupe)
                @php($principal = $groupe->first())
                <li wire:key="groupe-{{ $principal->id }}">
                    <x-tn.surface>
                        <div class="space-y-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-tn.status-badge etat="perturbe">{{ $groupe->count() }} demandes similaires</x-tn.status-badge>
                                        <span class="inline-flex items-center gap-1 text-sm text-ink-2"><flux:icon.users variant="micro" />{{ $groupe->sum('soutiens_count') }} soutien(s)</span>
                                    </div>
                                    <h3 class="font-medium text-ink">{{ Signalement::libelleCategorie($principal->categorie) }} · {{ $principal->lieu }}</h3>
                                    <p class="text-sm text-ink-2">{{ Str::limit($principal->description, 200) }}</p>
                                </div>

                                <div class="flex flex-col gap-2 md:shrink-0 md:items-end">
                                    <flux:button size="sm" variant="primary" icon="arrows-pointing-in" wire:click="fusionnerGroupe({{ $principal->id }})" wire:confirm="Fusionner ces {{ $groupe->count() }} signalements dans la demande la plus ancienne ?" wire:loading.attr="disabled">
                                        Fusionner le groupe
                                    </flux:button>
                                    <div class="flex flex-wrap gap-1" role="group" aria-label="Changer l’état de tout le groupe">
                                        @foreach (Signalement::STATUT_OPTIONS as $option)
                                            <flux:button size="xs" variant="outline" wire:click="traiterGroupe({{ $principal->id }}, '{{ $option }}')" wire:confirm="Passer les {{ $groupe->count() }} signalements à l’état « {{ Signalement::libelleStatut($option) }} » et prévenir les habitants ?" wire:loading.attr="disabled">
                                                Tout : {{ Signalement::libelleStatut($option) }}
                                            </flux:button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <ul class="divide-y divide-line border-t border-line">
                                @foreach ($groupe as $item)
                                    <li wire:key="sig-{{ $item->id }}" class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <a href="{{ route('signalements.show', $item) }}" wire:navigate class="block truncate text-sm font-medium text-ink hover:text-cyan">
                                                n° {{ $item->id }} · {{ $item->lieu }}
                                                @if ($loop->first)
                                                    <span class="text-xs text-cyan">(principale)</span>
                                                @endif
                                            </a>
                                            <span class="block truncate text-xs text-ink-2">{{ $item->user?->name ?? 'Habitant inconnu' }} · {{ Str::limit($item->description, 90) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 sm:shrink-0">
                                            <span class="font-mono text-xs text-ink-2">{{ $item->created_at->format('d.m.Y') }}</span>
                                            <x-tn.status-badge :etat="$item->etatStatut()">{{ Signalement::libelleStatut($item->statut) }}</x-tn.status-badge>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </x-tn.surface>
                </li>
            @endforeach
        </ul>
    @endif
</section>
