<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\AvisProjet;
use App\Models\Projet;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Fiche d'un projet de la ville (F67), publique : description en langage simple, étapes, avancement et lieu.
 * F66 : si la consultation est ouverte, l'habitant connecté donne son avis (pour / contre / sans avis).
 */
new #[Layout('layouts::public'), Title('Projet de la ville')] class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public Projet $record;

    public string $position = '';

    public string $commentaire = '';

    public function mount(Projet $projet): void
    {
        $this->authorize('view', $projet);
        $this->record = $projet->load('quartier:id,nom');

        if ($this->monAvis) {
            $this->position = $this->monAvis->position;
            $this->commentaire = (string) $this->monAvis->commentaire;
        }
    }

    /**
     * Avis de l'habitant connecté sur ce projet (jamais celui d'un autre : filtré par user_id).
     */
    #[Computed]
    public function monAvis(): ?AvisProjet
    {
        if (! auth()->check()) {
            return null;
        }

        return AvisProjet::query()
            ->where('projet_id', $this->record->id)
            ->where('user_id', auth()->id())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'position' => ['required', Rule::in(AvisProjet::POSITION_OPTIONS)],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'position.required' => __('Choisissez votre réponse : pour, contre ou sans avis.'),
        ];
    }

    public function donnerAvis(): void
    {
        $this->authorize('donnerAvis', $this->record);
        $this->throttlePerUser('avis-projet', maxAttempts: 10, decaySeconds: 60);

        $validated = $this->validate();

        $avis = $this->monAvis ?? new AvisProjet;
        $avis->fill([
            'position' => $validated['position'],
            'commentaire' => trim((string) $validated['commentaire']) ?: null,
        ]);

        if (! $avis->exists) {
            $avis->user()->associate(auth()->user());
            $avis->projet()->associate($this->record);
        }

        $avis->save();
        unset($this->monAvis);

        Flux::toast(variant: 'success', text: __('Votre avis a été enregistré le :date.', ['date' => $avis->enregistreLe()]));
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: __('Projet supprimé.'));

        $this->redirectRoute('projets.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-5xl space-y-6 @guest px-4 py-6 lg:px-8 @endguest">
    @php
        $etapes = $record->listeEtapes();
        $point = $record->pointCarte($record->titre);
    @endphp

    <x-tn.page-header
        :label="$record->categorieLabel().' · '.$record->nomQuartier()"
        :title="$record->titre"
        :subtitle="$record->resume"
        :breadcrumb="auth()->check()
            ? ['Mon espace' => route('dashboard'), 'Projets de la ville' => route('projets.index'), $record->titre => null]
            : ['Accueil' => route('home'), 'Projets de la ville' => route('projets.index'), $record->titre => null]"
    >
        <x-slot:meta>
            <div class="mt-3">
                <x-tn.status-badge :etat="$record->etatBadge()">{{ $record->etatLabel() }}</x-tn.status-badge>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('voirAvis', $record)
                <flux:button icon="chat-bubble-bottom-center-text" :href="route('agent.projets.avis', $record)" wire:navigate>{{ __('Synthèse des avis') }}</flux:button>
            @endcan
            @can('update', $record)
                <flux:button icon="pencil-square" :href="route('projets.edit', $record)" wire:navigate>{{ __('Mettre à jour') }}</flux:button>
            @endcan
            @can('delete', $record)
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Supprimer définitivement ce projet ?') }}">{{ __('Supprimer') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-4">{{ __('Le projet en quelques mots') }}</x-tn.section-label>
                <p class="whitespace-pre-line leading-7 text-ink">{{ $record->description }}</p>
            </x-tn.surface>

            <x-tn.surface>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <x-tn.section-label as="h2">{{ __('Étapes et avancement') }}</x-tn.section-label>
                    <span class="font-mono text-sm text-ink">{{ $record->avancement }} %</span>
                </div>
                <div class="mb-6 h-2 w-full overflow-hidden rounded-full bg-line" role="progressbar" aria-valuenow="{{ $record->avancement }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Avancement du projet') }}">
                    <div class="h-full rounded-full bg-cyan" style="width: {{ $record->avancement }}%"></div>
                </div>
                @if (count($etapes) > 0)
                    <x-tn.timeline :items="collect($etapes)->map(fn (string $etape, int $i) => [
                        'label' => $etape,
                        'texte' => $i < $record->etapes_terminees ? __('Terminée') : ($i === $record->etapes_terminees && $record->etat !== 'termine' ? __('En cours') : null),
                        'etat' => $i < $record->etapes_terminees ? 'normal' : 'info',
                        'fait' => $i < $record->etapes_terminees || ($i === $record->etapes_terminees && $record->etat === 'en_cours'),
                    ])->all()" />
                @else
                    <p class="text-ink-2">{{ __('Les étapes du projet seront publiées prochainement.') }}</p>
                @endif
            </x-tn.surface>

            @if ($record->consultation_ouverte || $this->monAvis)
                <x-tn.surface id="avis">
                    <x-tn.section-label as="h2" class="mb-2">{{ __('Votre avis sur ce projet') }}</x-tn.section-label>
                    <p class="mb-4 text-sm text-ink-2">
                        {{ $record->consultation_ouverte
                            ? __('La ville consulte les habitants sur ce projet. Ce n\'est pas un vote officiel : votre avis aide les agents à l\'améliorer.')
                            : __('La consultation sur ce projet est close.') }}
                    </p>

                    @if ($this->monAvis)
                        <flux:callout variant="success" icon="check-circle" class="mb-4">
                            <flux:callout.heading>{{ __('Votre avis a été enregistré le :date.', ['date' => $this->monAvis->enregistreLe()]) }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Votre réponse : :position.', ['position' => $this->monAvis->positionLabel()]) }}
                                @if ($record->consultation_ouverte)
                                    {{ __('Vous pouvez la modifier tant que la consultation est ouverte.') }}
                                @endif
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @guest
                        <flux:button variant="primary" icon="arrow-right-end-on-rectangle" :href="route('login')">{{ __('Se connecter pour donner mon avis') }}</flux:button>
                    @else
                        @can('donnerAvis', $record)
                            <form wire:submit="donnerAvis" class="space-y-4">
                                <fieldset data-requis>
                                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('Êtes-vous favorable à ce projet ?') }}</legend>
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach (AvisProjet::POSITION_LABELS as $valeur => $libelle)
                                            <label wire:key="position-{{ $valeur }}" class="flex cursor-pointer items-center justify-center gap-2 rounded-md border border-line p-3 text-sm has-[:checked]:border-cyan has-[:checked]:ring-2 has-[:checked]:ring-cyan">
                                                <input type="radio" wire:model="position" name="position" value="{{ $valeur }}" required class="size-4 accent-[var(--color-cyan)]">
                                                <span>{{ __($libelle) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <flux:error name="position" class="mt-2" />
                                </fieldset>

                                <flux:textarea wire:model="commentaire" label="{{ __('Commentaire (facultatif)') }}" rows="3" maxlength="2000" placeholder="{{ __('Une remarque, une idée, une inquiétude…') }}" />

                                <flux:error name="throttle" />

                                <div class="flex flex-wrap items-center gap-3">
                                    <flux:button type="submit" variant="primary">
                                        <span wire:loading.remove wire:target="donnerAvis">{{ $this->monAvis ? __('Modifier mon avis') : __('Envoyer mon avis') }}</span>
                                        <span wire:loading wire:target="donnerAvis">{{ __('Enregistrement…') }}</span>
                                    </flux:button>
                                    <flux:link :href="route('avis.index')" wire:navigate class="text-sm">{{ __('Voir tous mes avis') }}</flux:link>
                                </div>
                            </form>
                        @elseif ($record->consultation_ouverte && ! auth()->user()->isCitoyen())
                            <flux:text>{{ __('Seuls les habitants peuvent donner leur avis.') }}</flux:text>
                        @endcan
                    @endguest
                </x-tn.surface>
            @endif
        </div>

        <div class="space-y-6">
            <x-tn.panel label="{{ __('En bref') }}" padding="p-5 md:p-6">
                <dl>
                    <x-tn.field label="{{ __('État') }}">
                        <x-tn.status-badge :etat="$record->etatBadge()">{{ $record->etatLabel() }}</x-tn.status-badge>
                    </x-tn.field>
                    @if ($record->consultation_ouverte)
                        <x-tn.field label="{{ __('Consultation') }}">
                            <a href="#avis" class="text-sm text-cyan hover:underline">{{ __('Ouverte : donnez votre avis') }}</a>
                        </x-tn.field>
                    @endif
                    <x-tn.field label="{{ __('Quartier') }}"><p class="text-sm">{{ $record->nomQuartier() }}</p></x-tn.field>
                    @if ($record->date_debut)
                        <x-tn.field label="{{ __('Début') }}"><p class="font-mono text-sm">{{ $record->date_debut->translatedFormat('d F Y') }}</p></x-tn.field>
                    @endif
                    @if ($record->date_fin)
                        <x-tn.field label="{{ $record->etat === 'termine' ? __('Fin') : __('Fin prévue') }}"><p class="font-mono text-sm">{{ $record->date_fin->translatedFormat('d F Y') }}</p></x-tn.field>
                    @endif
                    <x-tn.field label="{{ __('Budget') }}"><p class="font-mono text-sm">{{ $record->budgetFormate() ?? __('Non communiqué') }}</p></x-tn.field>
                    @if ($record->lieu)
                        <x-tn.field label="{{ __('Lieu') }}"><p class="text-sm">{{ $record->lieu }}</p></x-tn.field>
                    @endif
                    <x-tn.field label="{{ __('Dernière mise à jour') }}"><p class="font-mono text-sm">{{ $record->updated_at?->translatedFormat('d F Y') }}</p></x-tn.field>
                </dl>
            </x-tn.panel>

            @if ($point)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-4">{{ __('Où ?') }}</x-tn.section-label>
                    <x-carte :points="[[...$point, 'etat' => $record->etatBadge(), 'lignes' => array_filter([$record->lieu])]]" :centre="[$point['lat'], $point['lng']]" :zoom="15" hauteur="18rem" :label="__('Emplacement du projet')" />
                </x-tn.surface>
            @endif
        </div>
    </div>
</section>
