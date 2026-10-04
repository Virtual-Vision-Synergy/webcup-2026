<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Annonce;
use App\Services\NotifierAnnonce;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::agent'), Title('Messages généraux')] class extends Component {
    use ThrottlesPerUser, WithPagination;

    public const STATUTS = [
        'en_cours' => ['label' => 'En cours', 'etat' => 'normal'],
        'programme' => ['label' => 'Programmé', 'etat' => 'info'],
        'expire' => ['label' => 'Expiré', 'etat' => 'perturbe'],
    ];

    #[Url(except: '')]
    public string $filterStatut = '';

    /** Annonce dont on met à jour la situation (vérifiée par findOrFail + authorize à l'enregistrement). */
    public ?int $miseAJourId = null;

    public string $miseAJour = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Annonce::class);
    }

    public function updatedFilterStatut(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', Annonce::class);

        return Annonce::query()
            ->with(['user:id,name', 'quartier:id,nom'])
            ->when($this->filterStatut === 'en_cours', fn ($query) => $query->active())
            ->when($this->filterStatut === 'programme', fn ($query) => $query->where('debut', '>', now()))
            ->when($this->filterStatut === 'expire', fn ($query) => $query->where('fin', '<=', now()))
            ->latest('debut')
            ->paginate(10);
    }

    /**
     * Arrête la diffusion tout de suite (la fin devient « maintenant »).
     */
    public function depublier(int $id): void
    {
        $annonce = Annonce::findOrFail($id);
        $this->authorize('update', $annonce);

        if ($annonce->statut() === 'expire') {
            return;
        }

        $annonce->fin = now();
        if ($annonce->debut->isFuture()) {
            $annonce->debut = now();
        }
        $annonce->save();

        Flux::toast(variant: 'success', text: __('Message dépublié : il n’apparaît plus dans le bandeau.'));
    }

    public function ouvrirMiseAJour(int $id): void
    {
        $annonce = Annonce::findOrFail($id);
        $this->authorize('update', $annonce);

        $this->miseAJourId = $annonce->id;
        $this->reset('miseAJour');
        $this->resetValidation();
        Flux::modal('mise-a-jour')->show();
    }

    /**
     * Ajoute une ligne horodatée au message en cours, sans le recréer (F29).
     */
    public function enregistrerMiseAJour(): void
    {
        $annonce = Annonce::findOrFail($this->miseAJourId);
        $this->authorize('update', $annonce);

        $this->validate(
            ['miseAJour' => ['required', 'string', 'max:500']],
            ['miseAJour.required' => 'Décrivez la nouvelle situation.', 'miseAJour.max' => 'La mise à jour ne doit pas dépasser 500 caractères.'],
        );

        $annonce->ajouterMiseAJour($this->miseAJour);

        $this->reset('miseAJour', 'miseAJourId');
        Flux::modal('mise-a-jour')->close();
        Flux::toast(variant: 'success', text: 'Situation mise à jour : le bandeau affiche la nouvelle ligne.');
    }

    /**
     * F101 : situation rétablie (« courant rétabli ») : ligne horodatée ajoutée, fin de diffusion dans 30 minutes.
     */
    public function retablir(int $id): void
    {
        $annonce = Annonce::findOrFail($id);
        $this->authorize('update', $annonce);

        if ($annonce->statut() !== 'en_cours') {
            return;
        }

        $annonce->retablir('Situation rétablie (courant rétabli). Cette alerte prend fin dans 30 minutes.');

        Flux::toast(variant: 'success', text: 'Situation rétablie : le bandeau l’indique et disparaîtra dans 30 minutes.');
    }

    /**
     * F104 : publie tout de suite une alerte à partir d'un modèle prêt (ex. « Tempête solaire »), sans passer par le formulaire.
     * Une alerte du même modèle encore en cours est arrêtée : la nouvelle la remplace (nouvelle estimation, nouveau compte à rebours).
     */
    public function publierModele(string $cle): void
    {
        $this->authorize('create', Annonce::class);
        $this->throttlePerUser('annonce-modele', maxAttempts: 3, decaySeconds: 60);

        $champs = Annonce::depuisModele($cle);
        if ($champs === null) {
            return;
        }

        Annonce::query()->where('titre', $champs['titre'])->where('fin', '>', now())->get()
            ->each(function (Annonce $ancienne): void {
                $this->authorize('update', $ancienne);
                $ancienne->fin = now();
                if ($ancienne->debut->isFuture()) {
                    $ancienne->debut = now();
                }
                $ancienne->save();
            });

        $annonce = new Annonce([
            ...$champs,
            'debut' => $champs['debut']->utc(),
            'fin' => $champs['fin']->utc(),
        ]);
        if (isset($champs['impact_prevu_le'])) {
            $annonce->impact_prevu_le = $champs['impact_prevu_le']->utc();
        }
        $annonce->user()->associate(auth()->user());
        $annonce->save();

        $notifiee = app(NotifierAnnonce::class)->notifierSiVisible($annonce);

        Flux::toast(variant: 'success', text: 'Alerte « '.Annonce::MODELES[$cle]['libelle'].' » publiée : bandeau en ligne sur toutes les pages.'
            .($notifiee ? ' Les habitants sont prévenus.' : ''));
    }

    public function delete(int $id): void
    {
        $annonce = Annonce::findOrFail($id);
        $this->authorize('delete', $annonce);

        $annonce->delete();

        Flux::toast(variant: 'success', text: __('Message supprimé.'));
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        label="{{ __('Haut Conseil de la Ville') }}"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Messages généraux' => null]"
        title="{{ __('Messages généraux') }}"
        subtitle="{{ __('Diffusez une information à tous les habitants : elle s’affiche en bandeau sur toutes les pages pendant sa période de validité.') }}"
    >
        <x-slot:actions>
            @can('create', \App\Models\Annonce::class)
                <flux:button variant="danger" icon="sun" wire:click="publierModele('tempete-solaire')">
                    Alerte tempête solaire (1 clic)
                </flux:button>
                <flux:button icon="bolt" :href="route('agent.annonces.create', ['modele' => 'panne-electrique'])" wire:navigate>
                    Alerte panne électrique
                </flux:button>
                <flux:button variant="primary" icon="megaphone" :href="route('agent.annonces.create')" wire:navigate>
                    {{ __('Publier un message') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @error('throttle')
        <p role="alert" class="text-sm font-medium text-magenta">{{ $message }}</p>
    @enderror

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:select wire:model.live="filterStatut" class="sm:max-w-52" aria-label="{{ __('Filtrer par statut') }}">
            <flux:select.option value="">{{ __('Statut : tous') }}</flux:select.option>
            @foreach ($this::STATUTS as $code => $statut)
                <flux:select.option :value="$code">{{ __($statut['label']) }}</flux:select.option>
            @endforeach
        </flux:select>
        <span wire:loading wire:target="filterStatut" class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ __('Mise à jour…') }}</span>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="megaphone" title="{{ __('Aucun message') }}" text="{{ __('Publiez un premier message : il apparaîtra en bandeau pour tous les habitants.') }}">
            <flux:button variant="primary" :href="route('agent.annonces.create')" wire:navigate>{{ __('Publier un message') }}</flux:button>
        </x-tn.empty>
    @else
        <x-tn.surface padding="px-4 py-2">
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>{{ __('Message') }}</flux:table.column>
                <flux:table.column>{{ __('Niveau') }}</flux:table.column>
                <flux:table.column>{{ __('Diffusion (heure de Madagascar)') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->items as $item)
                    @php($statut = $this::STATUTS[$item->statut()])
                    <flux:table.row wire:key="annonce-{{ $item->id }}">
                        <flux:table.cell class="max-w-xs">
                            <p class="flex items-center gap-1.5 font-medium text-ink">
                                @if ($item->estOfficiel())
                                    <flux:icon.building-library class="size-4 shrink-0" aria-hidden="true" />
                                    <span class="shrink-0 rounded-xs bg-ink px-1 font-mono text-[10px] uppercase text-surface">Officiel</span>
                                @endif
                                <span class="truncate">{{ $item->titre }}</span>
                            </p>
                            <p class="truncate text-xs text-ink-2">{{ $item->quartier ? 'Quartier '.$item->quartier->nom : 'Toute la ville' }} · Par {{ $item->user?->name ?? '—' }}</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-tn.status-badge :etat="match ($item->niveau) { 'danger', 'alerte' => 'alerte', 'vigilance' => 'perturbe', default => 'info' }">
                                {{ $item->libelleNiveau() }}
                            </x-tn.status-badge>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-sm">
                            {{ $item->debut->timezone(\App\Models\Annonce::FUSEAU)->format('d/m/Y H:i') }}
                            → {{ $item->fin->timezone(\App\Models\Annonce::FUSEAU)->format('d/m/Y H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-tn.status-badge :etat="$statut['etat']" :live="$item->statut() === 'en_cours'">{{ __($statut['label']) }}</x-tn.status-badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex justify-end gap-1">
                                @can('update', $item)
                                    @if ($item->statut() === 'en_cours')
                                        <flux:button size="sm" variant="ghost" icon="clock" wire:click="ouvrirMiseAJour({{ $item->id }})">
                                            Mettre à jour
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" icon="check-circle" wire:click="retablir({{ $item->id }})" wire:confirm="Indiquer que la situation est rétablie ? L’alerte prendra fin dans 30 minutes.">
                                            Rétabli
                                        </flux:button>
                                    @endif
                                    @if ($item->statut() !== 'expire')
                                        <flux:button size="sm" variant="ghost" icon="stop-circle" wire:click="depublier({{ $item->id }})" wire:confirm="{{ __('Arrêter la diffusion de ce message maintenant ?') }}">
                                            {{ __('Dépublier') }}
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('agent.annonces.edit', $item)" wire:navigate aria-label="{{ __('Modifier le message : :titre', ['titre' => $item->titre]) }}" />
                                @endcan
                                @can('delete', $item)
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="{{ __('Supprimer définitivement ce message ?') }}" aria-label="{{ __('Supprimer le message : :titre', ['titre' => $item->titre]) }}" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-tn.surface>
    @endif

    <flux:modal name="mise-a-jour" class="w-full max-w-lg">
        <form wire:submit="enregistrerMiseAJour" class="space-y-5">
            <div>
                <flux:heading size="lg">Mettre à jour la situation</flux:heading>
                <flux:text class="mt-1">Une ligne « Mise à jour {{ now(\App\Models\Annonce::FUSEAU)->format('G \h i') }} : … » est ajoutée au message, sans le recréer.</flux:text>
            </div>
            <flux:textarea wire:model="miseAJour" label="Nouvelle situation" rows="3" maxlength="500" required
                placeholder="Ex. Le niveau de l’eau se stabilise, restez éloignés des berges." />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Annuler</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Publier la mise à jour</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
