<?php

use App\Concerns\BloqueSiServiceIndisponible;
use App\Concerns\EmpecheEnvoiEnDouble;
use App\Concerns\ThrottlesPerUser;
use App\Models\Demarche;
use App\Models\Service;
use App\Services\OnboardingProgress;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Démarche')] class extends Component {
    use BloqueSiServiceIndisponible, EmpecheEnvoiEnDouble, ThrottlesPerUser;

    #[Locked]
    public ?Demarche $record = null;

    public string $titre = '';
    public string $description = '';
    public string $service_id = '';

    /** F86 : case « urgence médicale » (les mots-clés de la description suffisent aussi). */
    public bool $urgenceMedicale = false;

    public function mount(?Demarche $demarche = null): void
    {
        if ($demarche?->exists) {
            $this->authorize('update', $demarche);
            $this->record = $demarche;
            $this->titre = (string) ($demarche->titre ?? '');
            $this->description = (string) ($demarche->description ?? '');
            $this->service_id = (string) ($demarche->service_id ?? '');
            $this->urgenceMedicale = $demarche->urgence_medicale;
        } else {
            $this->authorize('create', Demarche::class);
            $this->initialiserJetonEnvoi();

            // Pré-sélection du service (lien « Commencer une démarche » du parcours de prise en main, D12).
            $serviceId = request()->integer('service');
            $service = $serviceId > 0 ? Service::query()->find($serviceId) : null;

            // F38 / F64 : lien direct vers un service indisponible → retour sur sa fiche (motif, retour prévu, alternative).
            if ($this->redirigerSiServiceIndisponible($service)) {
                return;
            }

            if ($service !== null) {
                $this->service_id = (string) $service->id;
            }

            // F92 : besoin décrit sur la page d'orientation, repris comme description de la démarche.
            $this->description = Str::limit(trim(request()->string('besoin')->toString()), 5000, '');
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
            'urgenceMedicale' => ['boolean'],
            // F63 : un service rendu indisponible par un administrateur n'accepte plus de démarche (contrôle serveur).
            'service_id' => [
                'nullable',
                Rule::exists(Service::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $service = filled($value) ? Service::query()->find($value) : null;

                    // F64 : une démarche déjà en cours n'est pas bloquée tant qu'elle ne change pas de service.
                    $dejaRattachee = $this->record !== null && (string) $this->record->service_id === (string) $value;

                    if ($service?->estIndisponible() && ! $dejaRattachee) {
                        $fail(__('Le service « :nom » est momentanément indisponible : choisissez un autre service ou « Je ne sais pas », la mairie orientera votre demande.', ['nom' => $service->t('nom')]));
                    }
                },
            ],
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
        return Service::query()->with('interruptionCourante')->avecTraduction()->orderBy('nom')->get(['id', 'nom', 'pieces_a_fournir', 'indisponible_depuis', 'perturbe_depuis', 'motif_indisponibilite', 'retour_prevu_le']);
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Demarche::class);

        // F38 / F64 : refus serveur d'une NOUVELLE démarche sur un service indisponible (le bouton masqué ne suffit pas).
        // Une démarche déjà déposée reste modifiable sur son service (règle de validation de service_id).
        if (! $this->record && filled($this->service_id) && $this->redirigerSiServiceIndisponible(Service::query()->find($this->service_id))) {
            return;
        }

        $validated = $this->validate();

        // F78 : 10 envois par minute et par habitant au plus (message clair au-dessus du bouton).
        $this->throttlePerUser('demarche', maxAttempts: 10, decaySeconds: 60);

        // F86 : urgence médicale si la case est cochée ou si le texte l'évoque ; jamais retirée par l'habitant.
        $urgence = (bool) ($validated['urgenceMedicale'] ?? false) || Demarche::detecterUrgenceMedicale($validated['titre'], $validated['description']);
        unset($validated['urgenceMedicale']);

        foreach (['service_id'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $depuisParcours = ! $this->record && OnboardingProgress::pour(auth()->user())->doitRevenirAuParcours();

        $record = $this->record ?? new Demarche;
        $nouvelleUrgence = $urgence && ! $record->urgence_medicale;

        $record->fill($validated);

        if ($nouvelleUrgence) {
            $record->urgence_medicale = true;
        }

        $nouvelle = ! $record->exists;

        if ($record->exists) {
            $record->save();
        } else {
            // F82 : même démarche renvoyée (double clic, retour arrière) → rien n'est créé, message avec lien.
            $record = $this->envoyerUneSeuleFois(
                'demarche',
                ['titre' => $validated['titre'], 'description' => $validated['description'], 'service_id' => $validated['service_id'] ?? null],
                function () use ($record): Demarche {
                    $record->user()->associate(auth()->user());
                    $record->save();

                    return $record;
                },
                fn (Demarche $demarche): string => route('demarches.show', $demarche),
            );

            if ($record === null) {
                return;
            }

            // F83 : accusé de réception par e-mail (référence, date et heure, objet, service).
            $record->envoyerAccuseReception();
        }

        if ($nouvelleUrgence) {
            $record->alerterUrgenceMedicale();
        }

        Flux::toast(
            variant: $record->urgence_medicale ? 'warning' : 'success',
            text: $record->urgence_medicale
                ? __('Urgence médicale transmise en priorité aux agents. Si une vie est en danger, appelez le 15 ou le 112.')
                : ($nouvelle ? __('Démarche envoyée. Numéro de suivi : :numero', ['numero' => $record->numeroSuivi()]) : __('Démarche enregistrée.')),
        );

        // Confirmation claire après l'envoi (D16) : affichée sur la page de suivi de la démarche.
        if ($nouvelle) {
            session()->flash('demarche_envoyee', $record->numeroSuivi());
        }

        // F86 : une urgence ne suit pas le circuit ordinaire (pas de retour au parcours) : fiche avec les numéros d'urgence.
        if ($record->urgence_medicale) {
            $this->redirectRoute('demarches.show', $record, navigate: true);

            return;
        }

        // Parcours de prise en main (D12) : la première démarche termine le parcours ;
        // F83 : l'habitant arrive sur son accusé de réception (et non sur « Bienvenue »).
        if ($depuisParcours) {
            OnboardingProgress::pour(auth()->user())->synchroniser();
            $this->redirectRoute('demarches.accuse', $record);

            return;
        }

        $this->redirectRoute('demarches.show', $record, navigate: true);
    }
}; ?>

@php
    $etapes = [__('Service'), __('Votre demande'), __('Récapitulatif')];
    // F27 : noms des services et pièces à préparer dans la langue de l'habitant (repli sur le français).
    $nomsServices = $this->serviceOptions->mapWithKeys(fn ($service) => [(string) $service->id => $service->t('nom')]);
    $piecesServices = $this->serviceOptions->mapWithKeys(fn ($service) => [(string) $service->id => $service->piecesAFournir()])->filter();
    // Après une erreur de validation, on revient sur l'étape qui contient le champ fautif.
    $etapeErreur = $errors->has('service_id') ? 1 : ($errors->hasAny(['titre', 'description']) ? 2 : null);
@endphp

<section
    class="mx-auto w-full max-w-2xl space-y-6"
    x-data="{
        etape: {{ $etapeErreur ?? 1 }},
        services: @js($nomsServices),
        pieces: @js($piecesServices),
        suivant() { if (this.etape === 2 && (! $wire.titre.trim() || ! $wire.description.trim())) { $refs.erreurEtape.hidden = false; return; } $refs.erreurEtape.hidden = true; this.etape++; this.$nextTick(() => $refs.contenu.focus()); },
        precedent() { this.etape--; this.$nextTick(() => $refs.contenu.focus()); },
    }"
>
    <x-tn.page-header
        :label="__('Démarches')"
        :title="$record ? __('Modifier la démarche') : __('Nouvelle démarche')"
        :breadcrumb="$record
            ? [__('Mon espace') => route('dashboard'), __('Démarches') => route('demarches.index'), ($record->titre ?: __('Démarche')) => route('demarches.show', $record), __('Modifier') => null]
            : [__('Mon espace') => route('dashboard'), __('Démarches') => route('demarches.index'), __('Nouvelle') => null]"
    />

    <x-tn.stepper :steps="$etapes" current="etape" />

    <x-tn.aide id="demarches-form">{{ __('Quatre étapes : choisissez le service, décrivez votre demande, ajoutez une pièce si besoin, puis vérifiez avant d\'envoyer.') }}</x-tn.aide>

    <livewire:explication-simple cle="depot-demarche" />

    <form wire:submit="save" @if (! $record) data-brouillon="demarche" data-brouillon-libelle="{{ __('Nouvelle démarche') }}" @endif class="space-y-6" x-on:keydown.enter="if (etape < 3 && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); suivant(); }">
        <div x-ref="contenu" tabindex="-1" class="outline-none">
            {{-- ÉTAPE 1 : SERVICE --}}
            <fieldset x-show="etape === 1" class="space-y-3">
                <legend class="tn-display mb-1 text-xl font-semibold text-ink">{{ __('Quel service est concerné ?') }}</legend>
                <p class="mb-4 text-ink-2">{{ __('Choisissez le service municipal. En cas de doute, la mairie orientera votre demande.') }}</p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8">
                        <input type="radio" wire:model="service_id" name="service_id" value="" class="size-4 accent-[var(--color-cyan)]">
                        <span class="font-medium text-ink">{{ __('Je ne sais pas') }}</span>
                    </label>
                    @foreach ($this->serviceOptions as $option)
                        @php($bloque = $option->estIndisponible() && (! $record || (int) $record->service_id !== $option->id))
                        <label wire:key="service-{{ $option->id }}" @class([
                            'flex min-h-14 items-center gap-3 rounded-md border border-line bg-surface px-4 py-3 transition-colors',
                            'cursor-pointer hover:border-cyan/40 has-checked:border-cyan has-checked:bg-cyan/8' => ! $bloque,
                            'cursor-not-allowed opacity-70' => $bloque,
                        ])>
                            <input type="radio" wire:model="service_id" name="service_id" value="{{ $option->id }}" class="size-4 accent-[var(--color-cyan)]" @disabled($bloque)>
                            <span class="min-w-0">
                                <span class="block font-medium text-ink">{{ $option->t('nom') }}</span>
                                @if ($option->estPerturbe())
                                    <span class="block text-xs text-amber">{{ __('Perturbé · délais allongés') }}</span>
                                @endif
                                @if ($bloque)
                                    {{-- F38 / F63 : service indisponible, statut écrit en texte (pas seulement en couleur). --}}
                                    @if ($option->indisponible_depuis)
                                        <span class="block text-sm text-ink-2">{{ __('Indisponible') }}{{ $option->retour_prevu_le ? ' · '.__('retour prévu le :date', ['date' => $option->retour_prevu_le->isoFormat('LL')]) : '' }}</span>
                                    @else
                                        <span class="block text-sm text-ink-2">{{ __('Indisponible · démarche suspendue pendant l’interruption') }}</span>
                                    @endif
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
                    <h2 class="tn-display text-xl font-semibold text-ink">{{ __('Décrivez votre demande') }}</h2>
                    <p class="mt-1 text-ink-2">{{ __('Un objet court, puis les détails utiles au traitement.') }}</p>
                    <x-tn.mention-obligatoire />
                </div>
                {{-- F27 : pièces à préparer pour le service choisi, dans la langue de l'habitant (sinon en français). --}}
                <div x-show="pieces[$wire.service_id]" x-cloak class="rounded-md border border-line bg-surface p-4" data-test="pieces-a-preparer">
                    <h3 class="font-semibold text-ink">{{ __('Pièces à préparer') }}</h3>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-ink">
                        <template x-for="piece in (pieces[$wire.service_id] ?? [])" :key="piece">
                            <li x-text="piece"></li>
                        </template>
                    </ul>
                </div>
                {{-- F86 : urgence médicale, numéros d'urgence affichés dès que la case est cochée. --}}
                <div class="space-y-3">
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-md border border-magenta/35 bg-surface px-4 py-3 has-checked:border-magenta has-checked:bg-magenta/8">
                        <input type="checkbox" wire:model="urgenceMedicale" class="mt-1 size-4 accent-[var(--color-magenta)]">
                        <span>
                            <span class="block font-medium text-ink">{{ __('Il s’agit d’une urgence médicale') }}</span>
                            <span class="block text-sm text-ink-2">{{ __('Votre demande sera traitée en priorité. Les mots-clés (malaise, hémorragie, ne respire plus…) sont aussi détectés automatiquement.') }}</span>
                        </span>
                    </label>
                    <div x-show="$wire.urgenceMedicale" x-cloak>
                        <x-urgence-medicale-numeros />
                    </div>
                </div>
                <flux:input wire:model="titre" :label="__('Objet de la démarche')" :placeholder="__('Ex. Demande d\'acte de naissance')" required />
                <flux:textarea wire:model="description" :label="__('Détails')" :placeholder="__('Précisez votre demande (personnes concernées, dates, pièces disponibles…)')" rows="6" required />
            </div>

            {{-- ÉTAPE 3 : RÉCAPITULATIF --}}
            <div x-show="etape === 3" x-cloak class="space-y-4">
                <h2 class="tn-display text-xl font-semibold text-ink">{{ __('Vérifiez avant d\'envoyer') }}</h2>
                <x-tn.panel padding="p-5">
                    <dl>
                        <x-tn.field :label="__('Service')"><span x-text="services[$wire.service_id] ?? @js(__('Je ne sais pas'))"></span></x-tn.field>
                        <x-tn.field :label="__('Objet')"><span x-text="$wire.titre"></span></x-tn.field>
                        <x-tn.field :label="__('Urgence médicale')"><span x-text="$wire.urgenceMedicale ? @js(__('Oui : traitée en priorité')) : @js(__('Non'))"></span></x-tn.field>
                        <x-tn.field :label="__('Détails')"><p class="whitespace-pre-line" x-text="$wire.description"></p></x-tn.field>
                    </dl>
                </x-tn.panel>
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-md border border-magenta/35 bg-magenta/8 p-4 text-magenta" role="alert">
                        <flux:icon.exclamation-circle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p>{{ __('Certains champs sont à corriger :') }} {{ implode(' ', $errors->all()) }}</p>
                    </div>
                @endif
            </div>

            <p x-ref="erreurEtape" hidden class="mt-4 text-sm text-magenta" role="alert"><flux:icon.exclamation-circle variant="micro" class="me-1 inline size-4 align-[-3px]" aria-hidden="true" />{{ __('Renseignez l\'objet et les détails pour continuer.') }}</p>
        </div>

        <flux:error name="throttle" />

        <x-envoi-deja-fait :le="$envoiDejaFaitLe" :url="$envoiDejaFaitUrl" />

        {{-- Un seul CTA par étape --}}
        <div class="flex items-center justify-between gap-3 border-t border-line pt-5">
            <div>
                <flux:button type="button" variant="ghost" icon="arrow-left" x-show="etape > 1" x-on:click="precedent()">{{ __('Retour') }}</flux:button>
                <flux:button :href="route('demarches.index')" wire:navigate variant="ghost" x-show="etape === 1">{{ __('Annuler') }}</flux:button>
            </div>

            <flux:button type="button" variant="primary" icon:trailing="arrow-right" x-show="etape < 3" x-on:click="suivant()">{{ __('Continuer') }}</flux:button>
            <x-submit-button variant="primary" class="tn-cta" x-show="etape === 3" x-cloak>{{ $record ? __('Enregistrer') : __('Envoyer la démarche') }}</x-submit-button>
        </div>
    </form>
</section>
