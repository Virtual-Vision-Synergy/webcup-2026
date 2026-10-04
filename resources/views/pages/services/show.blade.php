<?php

use App\Models\ActionLog;
use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Models\ServiceReview;
use App\Models\VueService;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Service')] class extends Component {
    #[Locked]
    public Service $record;

    /** F64 : formulaire « Mettre à jour l'état » (agent du service ou admin). */
    public string $etat = '';

    public string $motif = '';

    public string $retourPrevuLe = '';

    public string $alternativeTexte = '';

    public string $alternativeUrl = '';

    public function mount(Service $service): void
    {
        $this->authorize('view', $service);
        $this->record = $service->load('interruptionCourante.alternativeService');
        $this->remplirFormulaireEtat();

        // Parcours de prise en main (D12), étape « Trouver un service » : sans effet hors parcours en cours.
        OnboardingProgress::pour(auth()->user())->marquerServiceVisite($service);

        // F98 : compteur anonymisé des consultations (aucune donnée personnelle enregistrée).
        VueService::enregistrer($service, auth()->user());
    }

    /**
     * Services disponibles de la même catégorie, proposés quand celui-ci est indisponible (F63).
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function alternatives(): Collection
    {
        if (! $this->record->estIndisponible()) {
            return new Collection;
        }

        return Service::query()
            ->disponibles()
            ->whereKeyNot($this->record->id)
            ->when($this->record->categorie, fn ($query) => $query->where('categorie', $this->record->categorie))
            ->prioritaires()
            ->limit(3)
            ->get(['id', 'nom', 'slug']);
    }

    /**
     * F64 : change l'état du service (disponible, perturbé, indisponible), avec motif, retour prévu et alternative.
     */
    public function mettreAJourEtat(): void
    {
        $this->authorize('updateStatus', $this->record);

        $this->validate([
            'etat' => ['required', Rule::in(Service::ETAT_OPTIONS)],
            'motif' => ['nullable', Rule::requiredIf($this->etat !== Service::ETAT_DISPONIBLE), 'string', 'max:255'],
            'retourPrevuLe' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'alternativeTexte' => ['nullable', 'string', 'max:255'],
            'alternativeUrl' => ['nullable', 'url:http,https', 'max:255'],
        ], [
            'motif.required' => __('Indiquez le motif : il est affiché aux habitants.'),
            'retourPrevuLe.after_or_equal' => __('La date de retour prévue ne peut pas être passée.'),
            'alternativeUrl.url' => __('Le lien doit être une adresse web commençant par http:// ou https://.'),
        ], [
            'etat' => __('état'),
            'motif' => __('motif'),
            'retourPrevuLe' => __('date de retour prévue'),
            'alternativeTexte' => __('alternative'),
            'alternativeUrl' => __('lien de l\'alternative'),
        ]);

        $this->record->mettreAJourEtat(
            $this->etat,
            $this->motif,
            $this->retourPrevuLe !== '' ? Carbon::parse($this->retourPrevuLe) : null,
            $this->alternativeTexte,
            $this->alternativeUrl,
        );
        ActionLog::record('service_etat_modifie', $this->record);
        Cache::forget('landing.etat');

        $this->record->load('interruptionCourante.alternativeService');
        unset($this->alternatives, $this->interruption);
        $this->remplirFormulaireEtat();
        $this->modal('etat-service')->close();

        Flux::toast(variant: 'success', text: __('État du service mis à jour : :etat.', ['etat' => __($this->record->libelleEtat())]));
    }

    private function remplirFormulaireEtat(): void
    {
        $this->resetValidation();
        $this->etat = $this->record->etat();
        // Pré-rempli avec ce que voit l'habitant (interruption F38 en cours, sinon champs F63 / F64).
        $this->motif = (string) $this->record->motifEtat();
        $this->retourPrevuLe = (string) $this->record->retourPrevuEtat()?->setTimezone(CreneauRendezVous::fuseau())->toDateString();
        $this->alternativeTexte = Str::limit((string) $this->record->alternativeTexteEtat(), 255, '');
        $this->alternativeUrl = (string) $this->record->alternative_url;
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Service supprimé(e).'));

        $this->redirectRoute('services.index', navigate: true);
    }

    /**
     * Interruption en cours (F38) : seules les informations publiques sont envoyées à la vue (jamais l'agent).
     */
    #[Computed]
    public function interruption(): ?ServiceInterruption
    {
        return $this->record->interruptionEnCours();
    }

    /**
     * F76 : statistiques des avis publiés (moyenne, nombre, répartition).
     *
     * @return array{moyenne: float|null, total: int, repartition: array<int, int>}
     */
    #[Computed]
    public function statsAvis(): array
    {
        return $this->record->statistiquesAvis();
    }

    /**
     * F76 : les 5 avis publiés les plus récents (auteur : id et nom seulement, jamais l'e-mail).
     *
     * @return Collection<int, ServiceReview>
     */
    #[Computed]
    public function avisRecents(): Collection
    {
        return $this->record->reviews()->visibles()->with('user:id,name')->latest('updated_at')->latest('id')->limit(5)->get();
    }

    /**
     * F76 : avis de l'utilisateur connecté sur ce service (bouton « Donner » ou « Modifier mon avis »).
     */
    #[Computed]
    public function monAvis(): ?ServiceReview
    {
        return $this->record->reviews()->where('user_id', auth()->id())->first();
    }

    #[Computed]
    public function prendRendezVous(): bool
    {
        return (int) $this->record->duree_rendez_vous > 0;
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <x-tn.page-header
        :label="__(Service::labelCategorie($record->categorie) ?? __('Service municipal'))"
        :title="__($record->nom)"
        :breadcrumb="[__('Mon espace') => route('dashboard'), __('Services') => route('services.index'), __($record->nom) => null]"
    >
        <x-slot:actions>
            @if (Route::has('messages.create'))
                <flux:button icon="mail" :href="route('messages.create')" wire:navigate>{{ __('Écrire au service') }}</flux:button>
            @endif
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('services.edit', $record)" wire:navigate>{{ __('Modifier') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement ce service ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @if (session('service-indisponible'))
        <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
            <flux:callout.text>{{ session('service-indisponible') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- F64 : état actuel, puis encart d'interruption, puis seulement les boutons de démarche et de rendez-vous. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-service-status :service="$record" />
        @can('updateStatus', $record)
            <flux:modal.trigger name="etat-service">
                <flux:button size="sm" icon="arrow-path">{{ __('Mettre à jour l\'état') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <x-service-interruption :service="$record" :alternatives="$this->alternatives" />

    <div class="flex flex-wrap gap-2">
        @if ($record->estIndisponible())
            <flux:button variant="primary" icon="no-symbol" disabled aria-describedby="demarche-impossible">{{ __('Démarche momentanément impossible') }}</flux:button>
            <p id="demarche-impossible" class="text-sm font-medium text-ink-2">{{ __('Démarche suspendue pendant l’interruption : voyez « Que faire en attendant ? » ci-dessus.') }}</p>
        @else
            @if (Route::has('demarches.create'))
                <flux:button variant="primary" icon="document-plus" :href="route('demarches.create', ['service' => $record->id])" class="tn-cta" wire:navigate>{{ __('Commencer une démarche') }}</flux:button>
            @endif
            @if ($this->prendRendezVous && Route::has('appointments.create'))
                @can('create', RendezVous::class)
                    <flux:button icon="calendar-days" :href="route('appointments.create', ['service' => $record->slug])" wire:navigate>{{ __('Prendre rendez-vous') }}</flux:button>
                @endcan
            @endif
        @endif
    </div>

    @can('updateStatus', $record)
        <flux:modal name="etat-service" class="md:w-lg">
            <form wire:submit="mettreAJourEtat" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Mettre à jour l\'état') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('L\'état est affiché aux habitants sur la fiche et dans le catalogue.') }}</flux:text>
                </div>
                <flux:select wire:model.live="etat" :label="__('État')">
                    @foreach (Service::ETAT_LABELS as $valeur => $libelle)
                        <flux:select.option value="{{ $valeur }}">{{ __($libelle) }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($etat !== Service::ETAT_DISPONIBLE)
                    <flux:input wire:model="motif" :label="__('Motif affiché aux habitants')" :placeholder="__('Ex. Fermeture pour travaux')" required />
                    <flux:input type="date" wire:model="retourPrevuLe" :label="__('Retour prévu le (facultatif)')" :min="now(\App\Models\CreneauRendezVous::fuseau())->toDateString()" />
                    <flux:input wire:model="alternativeTexte" :label="__('Alternative proposée (facultatif)')" :placeholder="__('Ex. Point lecture de la mairie annexe')" />
                    <flux:input type="url" wire:model="alternativeUrl" :label="__('Lien de l\'alternative (facultatif)')" placeholder="https://" />
                @else
                    <flux:text>{{ __('Le motif, la date de retour et l\'alternative seront effacés.') }}</flux:text>
                @endif
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">
                        <span wire:loading.remove wire:target="mettreAJourEtat">{{ __('Enregistrer l\'état') }}</span>
                        <span wire:loading wire:target="mettreAJourEtat">{{ __('Enregistrement…') }}</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    <x-audit-history :subject="$record" variant="resume" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <x-tn.surface>
            <x-tn.section-label as="h2" class="mb-3">{{ __('Missions') }}</x-tn.section-label>
            <p class="whitespace-pre-line leading-relaxed text-ink">{{ __($record->description ?? __('Description à venir.')) }}</p>
        </x-tn.surface>

        {{-- F96 : en version légère, l'essentiel (horaires, contact, adresse) passe avant les missions sur mobile. --}}
        <x-tn.panel :label="__('Infos pratiques')" padding="p-5 md:p-6" @class(['max-lg:order-first' => \App\Support\ModeAllege::actif()])>
            <dl>
                @if ($record->horaires)
                    <x-tn.field :label="__('Horaires')"><p class="whitespace-pre-line font-mono text-sm leading-6">{{ __($record->horaires) }}</p></x-tn.field>
                @endif
                @if ($record->telephone)
                    <x-tn.field :label="__('Téléphone')"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $record->telephone) }}" class="font-mono text-sm text-cyan hover:underline">{{ $record->telephone }}</a></x-tn.field>
                @endif
                @if ($record->email)
                    <x-tn.field :label="__('E-mail')"><a href="mailto:{{ $record->email }}" class="break-all text-sm text-cyan hover:underline">{{ $record->email }}</a></x-tn.field>
                @endif
                @if ($record->adresse)
                    <x-tn.field :label="__('Adresse')"><p class="whitespace-pre-line text-sm">{{ __($record->adresse) }}</p></x-tn.field>
                @endif
            </dl>
            @if ($point = $record->pointCarte(__($record->nom)))
                <x-carte :points="[$point]" :itineraire="false" hauteur="14rem" :zoom="16" :label="__('Emplacement de :nom', ['nom' => __($record->nom)])" class="mt-4" />
                <a href="https://www.openstreetmap.org/directions?to={{ $record->latitude }}%2C{{ $record->longitude }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-sm text-cyan hover:underline">
                    <flux:icon name="arrow-top-right-on-square" class="size-4" />{{ __('Itinéraire (nouvel onglet)') }}
                </a>
            @endif
            @if (! $record->horaires && ! $record->telephone && ! $record->email && ! $record->adresse)
                <p class="text-ink-2">{{ __('Les informations pratiques seront publiées prochainement.') }}</p>
            @endif
        </x-tn.panel>
    </div>

    {{-- F76 : avis des habitants (distinct de la cloche « Avis » des notifications). --}}
    <x-tn.surface id="avis">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-tn.section-label as="h2">{{ __('Avis des habitants') }}</x-tn.section-label>
            @if ($this->monAvis?->estMasque())
                <flux:button size="sm" icon="eye-slash" :href="route('services.reviews.edit', $record)" wire:navigate>{{ __('Mon avis (masqué)') }}</flux:button>
            @elseif ($this->monAvis)
                <flux:button size="sm" icon="pencil-square" :href="route('services.reviews.edit', $record)" wire:navigate>{{ __('Modifier mon avis') }}</flux:button>
            @else
                <flux:button size="sm" variant="primary" icon="star" :href="route('services.reviews.edit', $record)" wire:navigate>{{ __('Donner mon avis') }}</flux:button>
            @endif
        </div>

        <x-service-reviews-summary :service="$record" :stats="$this->statsAvis" />

        @if ($this->avisRecents->isNotEmpty())
            <div class="mt-5 space-y-3">
                @foreach ($this->avisRecents as $avis)
                    <x-service-review :review="$avis" wire:key="avis-recent-{{ $avis->id }}" />
                @endforeach
            </div>
            <flux:link class="mt-4 inline-block text-sm" :href="route('services.reviews.index', $record)" wire:navigate>{{ __('Voir tous les avis (:count)', ['count' => $this->statsAvis['total']]) }}</flux:link>
        @endif
    </x-tn.surface>

    <x-audit-history :subject="$record" />
</section>
