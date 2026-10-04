<?php

use App\Models\AuditLog;
use App\Models\Demarche;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Démarche')] class extends Component {
    #[Locked]
    public Demarche $record;

    public function mount(Demarche $demarche): void
    {
        // F70 : refus explicite et journalisé pour un agent d'un autre service (DemarchePolicy::view).
        AuditLogger::autoriser('view', $demarche);
        $this->record = $demarche->loadMissing(['service', 'user', 'prisEnChargePar:id,name']);
    }

    /**
     * F86 : prise en charge d'une urgence médicale, tracée (qui, quand).
     */
    public function prendreEnCharge(): void
    {
        AuditLogger::autoriser('prendreEnCharge', $this->record);

        $prise = $this->record->prendreEnCharge(auth()->user());
        $this->record->load('prisEnChargePar:id,name');

        Flux::toast(
            variant: $prise ? 'success' : 'warning',
            text: $prise ? __('Urgence prise en charge.') : __('Cette urgence est déjà prise en charge.'),
        );
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Démarche supprimée.'));

        $this->redirectRoute('demarches.index', navigate: true);
    }

    public function changerStatut(string $statut): void
    {
        AuditLogger::autoriser('changerStatut', $this->record);
        abort_unless(in_array($statut, Demarche::STATUT_OPTIONS, true), 422);

        $this->record->changerStatut($statut);

        Flux::toast(variant: 'success', text: __('Statut mis à jour.'));
    }

    /**
     * F70 : consultations des données confidentielles de ce dossier (bloc réservé à l'admin).
     *
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function consultations(): Collection
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return AuditLog::query()
            ->where('subject_type', 'Demarche')
            ->where('subject_id', $this->record->id)
            ->where('action', 'confidential_viewed')
            ->latest('id')
            ->limit(20)
            ->get();
    }
}; ?>

@php
    $statut = $record->statut;
    $avance = in_array($statut, ['en_cours', 'traitee', 'refusee'], true);
    $termine = in_array($statut, ['traitee', 'refusee'], true);
    // Chronologie : seules les dates réellement connues sont affichées (dépôt, dernière mise à jour).
    $chronologie = [
        ['label' => __('Démarche déposée'), 'date' => $record->created_at, 'etat' => 'info', 'fait' => true],
        ['label' => __('Prise en charge par le service'), 'date' => $statut === 'en_cours' ? $record->updated_at : null, 'etat' => 'info', 'fait' => $avance, 'texte' => $avance ? null : __('En attente d’un agent municipal.')],
        [
            'label' => $termine ? __('Décision').' : '.__(Demarche::libelleStatut($statut)) : __('Décision'),
            'date' => $termine ? $record->updated_at : null,
            'etat' => $record->etatStatut(),
            'fait' => $termine,
            'texte' => $termine ? null : __('La décision apparaîtra ici.'),
        ],
    ];
@endphp

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__('Démarche')"
        :title="$record->titre"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Démarches') => route('demarches.index'), ($record->titre ?: __('Démarche')) => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ __(Demarche::libelleStatut($statut)) }}</x-tn.status-badge>
                @if ($record->urgence_medicale)
                    <x-tn.status-badge etat="alerte">{{ __('Urgence médicale') }}</x-tn.status-badge>
                @endif
                <span>Par {{ $record->user?->name }}</span>
                <span class="font-mono text-xs">{{ $record->created_at->format('d.m.Y · H:i') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('demarches.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement cette démarche ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    {{-- F86 : urgence médicale : numéros d'urgence en haut pour l'habitant, prise en charge tracée pour le personnel. --}}
    @if ($record->urgence_medicale)
        @if ($record->user_id === auth()->id() && $record->estUrgenceOuverte())
            <x-urgence-medicale-numeros />
        @endif

        <div class="flex flex-col gap-3 rounded-md border border-magenta/40 bg-magenta/5 p-4 sm:flex-row sm:items-center sm:justify-between" data-test="urgence-prise-en-charge">
            <div class="flex items-start gap-2">
                <flux:icon name="heart" class="mt-0.5 size-5 shrink-0 text-magenta" aria-hidden="true" />
                <div>
                    <p class="font-semibold text-ink">{{ __('Urgence médicale : traitée en priorité') }}</p>
                    @if ($record->pris_en_charge_le)
                        <p class="text-sm text-ink-2">
                            {{ __('Prise en charge par') }}
                            {{ $record->user_id === auth()->id() ? __('un agent municipal') : ($record->prisEnChargePar?->name ?? __('un agent')) }}
                            {{ __('le') }} <span class="font-mono">{{ $record->pris_en_charge_le->timezone(config('app.timezone'))->format('d.m.Y · H:i') }}</span>
                        </p>
                    @else
                        <p class="text-sm text-ink-2">{{ __('En attente de prise en charge : les agents concernés ont été prévenus immédiatement.') }}</p>
                    @endif
                </div>
            </div>
            @if (! $record->pris_en_charge_le)
                @can('prendreEnCharge', $record)
                    <flux:button variant="primary" icon="hand-raised" wire:click="prendreEnCharge">
                        <span wire:loading.remove wire:target="prendreEnCharge">{{ __('Prendre en charge') }}</span>
                        <span wire:loading wire:target="prendreEnCharge">{{ __('Enregistrement…') }}</span>
                    </flux:button>
                @endcan
            @endif
        </div>
    @endif

    <x-audit-history :subject="$record" variant="resume" />

    {{-- F76 : démarche traitée → invitation à donner (ou modifier) son avis sur le service. --}}
    @if ($statut === 'traitee' && $record->service && $record->user?->is(auth()->user()))
        <flux:callout icon="star" color="cyan">
            <flux:callout.heading>{{ __('Comment s’est passée votre démarche ? Donnez votre avis') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Votre note et votre commentaire aident la ville à améliorer le service « :service ».', ['service' => $record->service->nom]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="route('services.reviews.edit', $record->service)" wire:navigate>{{ __('Donner mon avis') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-2">{{ __('Détails') }}</x-tn.section-label>
            <dl>
                <x-tn.field :label="__('Objet')">{{ $record->titre ?? '—' }}</x-tn.field>
                <x-tn.field :label="__('Service')">{{ $record->service?->nom ? __($record->service->nom) : __('Non précisé') }}</x-tn.field>
                <x-tn.field :label="__('Description')"><p class="whitespace-pre-line leading-relaxed">{{ $record->description ?? '—' }}</p></x-tn.field>
            </dl>
        </x-tn.surface>

        <div class="flex flex-col gap-6">
            <x-tn.panel :label="__('Suivi')" padding="p-5 md:p-6">
                <x-tn.timeline :items="$chronologie" />
            </x-tn.panel>

            {{-- F70 : coordonnées du demandeur, masquées par défaut (motif + journal pour les afficher). --}}
            @if (! $record->user?->is(auth()->user()))
                @can('viewConfidential', $record)
                    <x-tn.surface>
                        <x-tn.section-label as="h2" class="mb-2">{{ __('Demandeur') }}</x-tn.section-label>
                        <dl>
                            <x-tn.field :label="__('Nom')">{{ $record->user?->name ?? '—' }}</x-tn.field>
                            @foreach ($record->confidentialFields() as $champ => $definition)
                                <x-tn.field :label="$definition['label']">
                                    <livewire:donnee-confidentielle :subject="$record" :champ="$champ" wire:key="confidentiel-{{ $champ }}" />
                                </x-tn.field>
                            @endforeach
                        </dl>
                    </x-tn.surface>
                @endcan
            @endif

            @can('changerStatut', $record)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-3">{{ __('Changer le statut') }}</x-tn.section-label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (Demarche::STATUT_OPTIONS as $option)
                            <flux:button size="sm" wire:click="changerStatut('{{ $option }}')" :disabled="$option === $statut" :variant="$option === $statut ? 'primary' : 'outline'">
                                {{ __(Demarche::libelleStatut($option)) }}
                            </flux:button>
                        @endforeach
                    </div>
                </x-tn.surface>
            @endcan
        </div>
    </div>

    {{-- F84 : échanges entre les agents et l'habitant (réponses, réponses types, relance de l'habitant). --}}
    <livewire:reponses-demarche :demarche="$record" />

    @if (auth()->user()->isAdmin())
        <x-tn.surface data-test="consultations-confidentielles">
            <x-tn.section-label as="h2" class="mb-3">{{ __('Consultations des données confidentielles') }}</x-tn.section-label>
            @if ($this->consultations->isEmpty())
                <flux:text>Aucune consultation enregistrée pour ce dossier.</flux:text>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($this->consultations as $consultation)
                        <li wire:key="consultation-{{ $consultation->id }}" class="flex flex-col gap-1 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                <span class="font-medium text-ink">{{ $consultation->auteur() }}</span>
                                a consulté « {{ $consultation->changes['champ']['apres'] ?? '—' }} »
                                <span class="text-ink-2">— motif : {{ $consultation->changes['motif']['apres'] ?? '—' }}</span>
                            </span>
                            <span class="font-mono text-xs text-ink-2">{{ $consultation->dateLocale() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-tn.surface>
    @endif

    <x-audit-history :subject="$record" />
</section>
