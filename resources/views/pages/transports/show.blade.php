<?php

use App\Models\AbonnementLigne;
use App\Models\InterruptionTransport;
use App\Models\LigneTransport;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ligne de transport')] class extends Component {
    #[Locked]
    public LigneTransport $record;

    /** F97 : arrêt habituel choisi par l'habitant (vide = toute la ligne). */
    public string $arret = '';

    public function mount(LigneTransport $ligneTransport): void
    {
        $this->authorize('view', $ligneTransport);
        $this->record = $ligneTransport;
        $this->arret = (string) ($this->abonnement?->arret ?? '');
    }

    /**
     * F97 : interruption en cours sur cette ligne (déclarée dans Filament).
     */
    #[Computed]
    public function interruption(): ?InterruptionTransport
    {
        return $this->record->interruptionsEnCours()->first();
    }

    /**
     * F97 : trajet habituel de l'utilisateur connecté sur cette ligne (filtré par propriétaire).
     */
    #[Computed]
    public function abonnement(): ?AbonnementLigne
    {
        return AbonnementLigne::query()
            ->whereBelongsTo(auth()->user())
            ->where('ligne_transport_id', $this->record->id)
            ->first();
    }

    /**
     * F97 : enregistre (ou met à jour) le trajet habituel : l'habitant sera prévenu si la ligne est interrompue.
     * La ligne vient de la route (#[Locked]) et l'utilisateur de la session : jamais du navigateur.
     */
    public function suivre(): void
    {
        $this->authorize('view', $this->record);

        $abonnement = $this->abonnement;

        if ($abonnement !== null) {
            $this->authorize('update', $abonnement);
        } else {
            $this->authorize('create', AbonnementLigne::class);
            $abonnement = new AbonnementLigne;
            $abonnement->user()->associate(auth()->user());
            $abonnement->ligne()->associate($this->record);
        }

        $validated = $this->validate([
            'arret' => ['nullable', 'string', Rule::in($this->record->listeArrets())],
        ], [
            'arret.in' => __('Choisissez un arrêt de cette ligne.'),
        ]);

        $abonnement->arret = $validated['arret'] === '' ? null : $validated['arret'];
        $abonnement->save();

        unset($this->abonnement);

        Flux::toast(variant: 'success', text: __('Trajet enregistré : vous serez prévenu si la ligne :numero est interrompue.', ['numero' => $this->record->numero]));
    }

    public function nePlusSuivre(): void
    {
        $abonnement = $this->abonnement;

        if ($abonnement === null) {
            return;
        }

        $this->authorize('delete', $abonnement);

        $abonnement->delete();
        $this->arret = '';

        unset($this->abonnement);

        Flux::toast(text: __('Cette ligne ne fait plus partie de vos trajets habituels.'));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Ligne supprimée.'));

        $this->redirectRoute('transports.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    @php($arrets = $record->listeArrets())
    @php($interruption = $this->interruption)
    @php($etat = $interruption ? 'interrompu' : $record->etat)

    <x-tn.page-header
        :label="$record->modeLabel().' · '.__('Ligne :numero', ['numero' => $record->numero])"
        :title="$record->nom"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Transports' => route('transports.index'), 'Ligne '.$record->numero => null]"
    >
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('transports.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement cette ligne ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-audit-history :subject="$record" variant="resume" />

    @if ($interruption)
        <x-transport-interruption :interruption="$interruption" :ligne="$record" :arret="$this->abonnement?->arret" :personnel="$this->abonnement !== null" carte />
    @elseif ($record->estPerturbee())
        <div role="alert" @class([
            'flex items-start gap-3 rounded-md border p-4',
            'border-amber/40 bg-amber/8 text-amber' => $record->etat === 'perturbe',
            'border-magenta/40 bg-magenta/8 text-magenta' => $record->etat === 'interrompu',
        ])>
            <flux:icon :name="$record->etat === 'interrompu' ? 'x-circle' : 'exclamation-triangle'" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div>
                <p class="font-semibold">{{ $record->etatLabel() }}</p>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $record->perturbation ?? __('Perturbation signalée, informations à venir.') }}</p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <x-tn.panel label="{{ __('Horaires et fréquence') }}" padding="p-5 md:p-6">
            <dl>
                <x-tn.field label="{{ __('État') }}">
                    <x-tn.status-badge :etat="$interruption ? 'alerte' : $record->etatBadge()" :live="$etat !== 'normal'">{{ __(LigneTransport::ETAT_LABELS[$etat] ?? $etat) }}</x-tn.status-badge>
                </x-tn.field>
                <x-tn.field label="{{ __('Horaires') }}"><p class="whitespace-pre-line font-mono text-sm leading-6">{{ $record->horaires }}</p></x-tn.field>
                @if ($record->frequence)
                    <x-tn.field label="{{ __('Fréquence') }}"><p class="font-mono text-sm">{{ $record->frequence }}</p></x-tn.field>
                @endif
                @if (count($arrets) > 0)
                    <x-tn.field label="{{ __('Trajet') }}"><p class="text-sm">{{ $arrets[0] }} → {{ end($arrets) }}</p></x-tn.field>
                @endif
            </dl>
        </x-tn.panel>

        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-4">Arrêts desservis ({{ count($arrets) }})</x-tn.section-label>
            @if (count($arrets) > 0)
                <x-tn.timeline :items="collect($arrets)->map(fn (string $arret, int $i) => [
                    'label' => $arret,
                    'texte' => $i === 0 ? __('Départ') : ($i === count($arrets) - 1 ? __('Terminus') : null),
                    'etat' => $interruption && $interruption->toucheArret($arret) ? 'alerte' : $record->etatBadge(),
                ])->all()" />
            @else
                <p class="text-ink-2">{{ __('La liste des arrêts sera publiée prochainement.') }}</p>
            @endif
        </x-tn.surface>
    </div>

    @can('create', AbonnementLigne::class)
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">{{ __('Mon trajet habituel') }}</x-tn.section-label>
            <p class="text-sm text-ink-2">
                @if ($this->abonnement)
                    {{ __('Vous prenez cette ligne :arret. Si elle est interrompue, vous êtes prévenu (cloche et e-mail selon votre profil) avec les solutions de remplacement.', ['arret' => $this->abonnement->arret ? __('à l’arrêt « :arret »', ['arret' => $this->abonnement->arret]) : '']) }}
                @else
                    {{ __('Vous prenez cette ligne ? Choisissez votre arrêt : si la ligne est interrompue, vous serez prévenu et on vous dira comment faire.') }}
                @endif
            </p>
            <form wire:submit="suivre" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <flux:select wire:model="arret" :label="__('Mon arrêt')" class="sm:max-w-xs">
                    <flux:select.option value="">{{ __('Toute la ligne') }}</flux:select.option>
                    @foreach (array_unique($arrets) as $nomArret)
                        <flux:select.option :value="$nomArret">{{ $nomArret }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary" icon="bell">
                    <span wire:loading.remove wire:target="suivre">{{ $this->abonnement ? __('Mettre à jour mon trajet') : __('Enregistrer mon trajet') }}</span>
                    <span wire:loading wire:target="suivre">{{ __('Enregistrement…') }}</span>
                </flux:button>
                @if ($this->abonnement)
                    <flux:button type="button" variant="ghost" icon="bell-slash" wire:click="nePlusSuivre">{{ __('Ne plus suivre') }}</flux:button>
                @endif
            </form>
            <flux:error name="arret" class="mt-2" />
        </x-tn.surface>
    @endcan

    <x-audit-history :subject="$record" />
</section>
