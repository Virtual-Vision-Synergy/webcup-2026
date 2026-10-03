<?php

use App\Concerns\BloqueSiServiceIndisponible;
use App\Models\Demarche;
use App\Models\Service;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Démarche')] class extends Component {
    use BloqueSiServiceIndisponible;

    #[Locked]
    public ?Demarche $record = null;

    public string $titre = '';
    public string $description = '';
    public string $service_id = '';

    public function mount(?Demarche $demarche = null): void
    {
        if ($demarche?->exists) {
            $this->authorize('update', $demarche);
            $this->record = $demarche;
            $this->titre = (string) ($demarche->titre ?? '');
            $this->description = (string) ($demarche->description ?? '');
            $this->service_id = (string) ($demarche->service_id ?? '');
        } else {
            $this->authorize('create', Demarche::class);

            // Pré-sélection du service (lien « Commencer une démarche » du parcours de prise en main, D12).
            $serviceId = request()->integer('service');
            $service = $serviceId > 0 ? Service::query()->find($serviceId) : null;

            // F38 : démarche sur un service interrompu → retour à sa fiche, qui explique quand revenir.
            if ($this->redirigerSiServiceIndisponible($service)) {
                return;
            }

            if ($service !== null) {
                $this->service_id = (string) $service->id;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'service_id' => ['nullable', Rule::exists(Service::class, 'id')],
        ];
    }

    /**
     * Valeurs proposées dans la liste déroulante « Service ».
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function serviceOptions(): Collection
    {
        return Service::query()->with('interruptionCourante')->orderBy('nom')->get(['id', 'nom']);
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Demarche::class);

        $validated = $this->validate();

        // F38 : pas de nouvelle démarche sur un service interrompu (une démarche déjà déposée reste modifiable sur son service).
        $serviceChoisi = filled($validated['service_id'] ?? null) ? Service::query()->find((int) $validated['service_id']) : null;
        if ($serviceChoisi !== null && (! $this->record || (int) $this->record->service_id !== $serviceChoisi->id)) {
            $serviceChoisi->assertDisponible('service_id');
        }

        foreach (['service_id'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $depuisParcours = ! $this->record && OnboardingProgress::pour(auth()->user())->doitRevenirAuParcours();

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Demarche($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Démarche enregistrée.');

        // Parcours de prise en main (D12) : la première démarche termine le parcours, on affiche les félicitations.
        if ($depuisParcours) {
            $this->redirectRoute('onboarding.show', navigate: true);

            return;
        }

        $this->redirectRoute('demarches.show', $record, navigate: true);
    }
}; ?>

@php
    $etapes = ['Service', 'Votre demande', 'Récapitulatif'];
    $nomsServices = $this->serviceOptions->pluck('nom', 'id')->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom]);
    // Après une erreur de validation, on revient sur l'étape qui contient le champ fautif.
    $etapeErreur = $errors->has('service_id') ? 1 : ($errors->hasAny(['titre', 'description']) ? 2 : null);
@endphp

<section
    class="mx-auto w-full max-w-2xl space-y-6"
    x-data="{
        etape: {{ $etapeErreur ?? 1 }},
        services: @js($nomsServices),
        suivant() { if (this.etape === 2 && (! $wire.titre.trim() || ! $wire.description.trim())) { $refs.erreurEtape.hidden = false; return; } $refs.erreurEtape.hidden = true; this.etape++; this.$nextTick(() => $refs.contenu.focus()); },
        precedent() { this.etape--; this.$nextTick(() => $refs.contenu.focus()); },
    }"
>
    <x-tn.page-header
        label="Démarches"
        :title="$record ? 'Modifier la démarche' : 'Nouvelle démarche'"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Démarches' => route('demarches.index'), ($record->titre ?: 'Démarche') => route('demarches.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Démarches' => route('demarches.index'), 'Nouvelle' => null]"
    />

    <x-tn.stepper :steps="$etapes" current="etape" />

    <form wire:submit="save" class="space-y-6" x-on:keydown.enter="if (etape < 3 && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); suivant(); }">
        <div x-ref="contenu" tabindex="-1" class="outline-none">
            {{-- ÉTAPE 1 : SERVICE --}}
            <fieldset x-show="etape === 1" class="space-y-3">
                <legend class="tn-display mb-1 text-xl font-semibold text-ink">Quel service est concerné ?</legend>
                <p class="mb-4 text-ink-2">Choisissez le service municipal. En cas de doute, la mairie orientera votre demande.</p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8">
                        <input type="radio" wire:model="service_id" value="" class="size-4 accent-[var(--color-cyan)]">
                        <span class="font-medium text-ink">Je ne sais pas</span>
                    </label>
                    @foreach ($this->serviceOptions as $option)
                        @php($bloque = $option->estIndisponible() && (! $record || (int) $record->service_id !== $option->id))
                        <label wire:key="service-{{ $option->id }}" @class([
                            'flex min-h-14 items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors',
                            'cursor-pointer hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8' => ! $bloque,
                            'cursor-not-allowed opacity-70' => $bloque,
                        ])>
                            <input type="radio" wire:model="service_id" value="{{ $option->id }}" class="size-4 accent-[var(--color-cyan)]" @disabled($bloque)>
                            <span class="min-w-0">
                                <span class="block font-medium text-ink">{{ $option->nom }}</span>
                                @if ($bloque)
                                    {{-- F38 : service interrompu, statut écrit en texte (pas seulement en couleur). --}}
                                    <span class="block text-sm text-ink-2">Indisponible · démarche suspendue pendant l’interruption</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="service_id" />
            </fieldset>

            {{-- ÉTAPE 2 : DEMANDE --}}
            <div x-show="etape === 2" x-cloak class="space-y-6">
                <div>
                    <h2 class="tn-display text-xl font-semibold text-ink">Décrivez votre demande</h2>
                    <p class="mt-1 text-ink-2">Un objet court, puis les détails utiles au traitement.</p>
                </div>
                <flux:input wire:model="titre" label="Objet de la démarche" placeholder="Ex. Demande d'acte de naissance" required />
                <flux:textarea wire:model="description" label="Détails" placeholder="Précisez votre demande (personnes concernées, dates, pièces disponibles…)" rows="6" required />
            </div>

            {{-- ÉTAPE 3 : RÉCAPITULATIF --}}
            <div x-show="etape === 3" x-cloak class="space-y-4">
                <h2 class="tn-display text-xl font-semibold text-ink">Vérifiez avant d'envoyer</h2>
                <x-tn.panel padding="p-5">
                    <dl>
                        <x-tn.field label="Service"><span x-text="services[$wire.service_id] ?? 'Je ne sais pas'"></span></x-tn.field>
                        <x-tn.field label="Objet"><span x-text="$wire.titre"></span></x-tn.field>
                        <x-tn.field label="Détails"><p class="whitespace-pre-line" x-text="$wire.description"></p></x-tn.field>
                    </dl>
                </x-tn.panel>
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-md border border-magenta/35 bg-magenta/8 p-4 text-magenta" role="alert">
                        <flux:icon.exclamation-circle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p>Certains champs sont à corriger : {{ implode(' ', $errors->all()) }}</p>
                    </div>
                @endif
            </div>

            <p x-ref="erreurEtape" hidden class="mt-4 text-sm text-magenta" role="alert"><flux:icon.exclamation-circle variant="micro" class="me-1 inline size-4 align-[-3px]" aria-hidden="true" />Renseignez l'objet et les détails pour continuer.</p>
        </div>

        {{-- Un seul CTA par étape --}}
        <div class="flex items-center justify-between gap-3 border-t border-line pt-5">
            <div>
                <flux:button type="button" variant="ghost" icon="arrow-left" x-show="etape > 1" x-on:click="precedent()">Retour</flux:button>
                <flux:button :href="route('demarches.index')" wire:navigate variant="ghost" x-show="etape === 1">Annuler</flux:button>
            </div>

            <flux:button type="button" variant="primary" icon:trailing="arrow-right" x-show="etape < 3" x-on:click="suivant()">Continuer</flux:button>
            <flux:button type="submit" variant="primary" class="tn-cta" x-show="etape === 3" x-cloak>
                <span wire:loading.remove wire:target="save">{{ $record ? 'Enregistrer' : 'Envoyer la démarche' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </flux:button>
        </div>
    </form>
</section>
