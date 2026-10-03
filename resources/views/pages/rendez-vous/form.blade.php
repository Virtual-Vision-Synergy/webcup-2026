<?php

use App\Concerns\BloqueSiServiceIndisponible;
use App\Concerns\ThrottlesPerUser;
use App\Exceptions\CreneauIndisponible;
use App\Models\CreneauRendezVous;
use App\Models\RendezVous;
use App\Models\Service;
use App\Services\PriseDeRendezVous;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Parcours de prise de rendez-vous (F39) : 1. service, 2. créneau, 3. récapitulatif puis confirmation.
 * Le service et le créneau choisis sont dans l'URL, mais toujours revalidés côté serveur.
 */
new #[Title('Prendre rendez-vous')] class extends Component {
    use BloqueSiServiceIndisponible, ThrottlesPerUser;

    #[Url(as: 'service', except: '')]
    public string $serviceSlug = '';

    #[Url(as: 'creneau', except: '')]
    public string $creneauId = '';

    public string $motif = '';

    /** Jour choisi dans le sélecteur de date (Y-m-d, heure locale). */
    public string $jour = '';

    /** Créneau sélectionné dans la liste des horaires du jour (pas encore validé). */
    public string $creneauSelectionne = '';

    /** Message affiché quand le créneau demandé n'est plus disponible. */
    public string $erreurCreneau = '';

    public function mount(): void
    {
        $this->authorize('create', RendezVous::class);

        if ($this->serviceSlug !== '' && $this->service === null) {
            $this->serviceSlug = '';
            $this->creneauId = '';
        }

        // F38 : service interrompu (maintenance, incident) → retour à sa fiche, qui explique quand revenir.
        if ($this->redirigerSiServiceIndisponible($this->service)) {
            return;
        }

        if ($this->creneauId !== '' && $this->creneau === null) {
            $this->creneauId = '';
            $this->erreurCreneau = CreneauIndisponible::INVALIDE;
        }

        $this->jour = (string) $this->jourAffiche;
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->prendRendezVous()->with('interruptionCourante')->orderBy('nom')->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        if ($this->serviceSlug === '') {
            return null;
        }

        return Service::query()->prendRendezVous()->where('slug', $this->serviceSlug)->first();
    }

    /**
     * Créneau choisi, seulement s'il est libre, réservable et rattaché au service choisi.
     */
    #[Computed]
    public function creneau(): ?CreneauRendezVous
    {
        if ($this->service === null || ! ctype_digit($this->creneauId)) {
            return null;
        }

        return CreneauRendezVous::query()
            ->whereBelongsTo($this->service)
            ->libres()
            ->reservables()
            ->find((int) $this->creneauId);
    }

    /**
     * Créneaux libres et futurs du service, groupés par jour local.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, CreneauRendezVous>>
     */
    #[Computed]
    public function creneauxParJour(): \Illuminate\Support\Collection
    {
        if ($this->service === null) {
            return collect();
        }

        return CreneauRendezVous::query()
            ->whereBelongsTo($this->service)
            ->libres()
            ->reservables()
            ->orderBy('debut')
            ->limit(400)
            ->get()
            ->groupBy(fn (CreneauRendezVous $creneau): string => $creneau->jourLocal());
    }

    /**
     * Jour affiché : celui saisi s'il est valide, sinon le premier jour qui a des créneaux libres.
     */
    #[Computed]
    public function jourAffiche(): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->jour) === 1) {
            return $this->jour;
        }

        $premier = $this->creneauxParJour->keys()->first();

        return is_string($premier) ? $premier : null;
    }

    /**
     * Créneaux libres du jour affiché.
     *
     * @return Collection<int, CreneauRendezVous>
     */
    #[Computed]
    public function creneauxDuJour(): Collection
    {
        return $this->creneauxParJour->get((string) $this->jourAffiche, new Collection);
    }

    /**
     * Prochain jour avec des créneaux libres après le jour affiché (quand celui-ci n'en a pas).
     */
    #[Computed]
    public function prochainJourLibre(): ?CreneauRendezVous
    {
        return $this->creneauxParJour
            ->filter(fn (Collection $creneaux, string $jour): bool => $jour > (string) $this->jourAffiche)
            ->first()
            ?->first();
    }

    public function updatedJour(): void
    {
        $this->creneauSelectionne = '';
        $this->erreurCreneau = '';
        unset($this->jourAffiche, $this->creneauxDuJour, $this->prochainJourLibre);
    }

    public function allerAuJour(string $jour): void
    {
        $this->authorize('create', RendezVous::class);

        $this->jour = $jour;
        $this->updatedJour();
    }

    public function validerCreneau(): void
    {
        $this->authorize('create', RendezVous::class);

        if (! ctype_digit($this->creneauSelectionne)) {
            $this->addError('creneauSelectionne', 'Choisissez un horaire dans la liste.');

            return;
        }

        $this->choisirCreneau((int) $this->creneauSelectionne);
    }

    #[Computed]
    public function etape(): int
    {
        return match (true) {
            $this->service === null => 1,
            $this->creneau === null => 2,
            default => 3,
        };
    }

    public function choisirService(string $slug): void
    {
        $this->authorize('create', RendezVous::class);

        // F38 : refusé côté serveur si le service est interrompu, même si le bouton a été contourné.
        Service::query()->prendRendezVous()->where('slug', $slug)->first()?->assertDisponible('service');

        $this->serviceSlug = $slug;
        $this->creneauId = '';
        $this->erreurCreneau = '';
        $this->resetComputed();

        if ($this->service === null) {
            $this->serviceSlug = '';
        }

        $this->jour = (string) $this->jourAffiche;
    }

    public function choisirCreneau(int $id): void
    {
        $this->authorize('create', RendezVous::class);

        $this->creneauId = (string) $id;
        $this->erreurCreneau = '';
        $this->resetComputed();

        if ($this->creneau === null) {
            $this->creneauId = '';
            $this->erreurCreneau = CreneauIndisponible::INVALIDE;
        }
    }

    public function retourServices(): void
    {
        $this->authorize('create', RendezVous::class);

        $this->reset('serviceSlug', 'creneauId', 'erreurCreneau', 'jour', 'creneauSelectionne');
        $this->resetComputed();
    }

    public function retourCreneaux(): void
    {
        $this->authorize('create', RendezVous::class);

        $this->reset('creneauId', 'erreurCreneau');
        $this->resetComputed();
    }

    public function confirmer(): void
    {
        $this->authorize('create', RendezVous::class);

        $this->validate(['motif' => ['nullable', 'string', 'max:1000']]);
        $this->throttlePerUser('rendez-vous', maxAttempts: 10, decaySeconds: 60);

        $service = $this->service;

        if ($service === null || ! ctype_digit($this->creneauId)) {
            $this->retourServices();

            return;
        }

        // F38 : le service a pu être interrompu depuis le choix du créneau.
        $service->assertDisponible('service');

        try {
            $rendezVous = app(PriseDeRendezVous::class)->reserver(auth()->user(), $service, (int) $this->creneauId, $this->motif);
        } catch (CreneauIndisponible $e) {
            $this->creneauId = '';
            $this->erreurCreneau = $e->getMessage();
            $this->resetComputed();

            return;
        }

        session()->flash('rendez-vous-confirme', true);

        $this->redirectRoute('appointments.show', $rendezVous, navigate: true);
    }

    private function resetComputed(): void
    {
        unset($this->service, $this->creneau, $this->creneauxParJour, $this->etape, $this->jourAffiche, $this->creneauxDuJour, $this->prochainJourLibre);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Rendez-vous"
        title="Prendre rendez-vous"
        subtitle="Choisissez un service, puis un créneau. Vous vérifiez tout avant de confirmer."
        :breadcrumb="['Mes rendez-vous' => route('appointments.index'), 'Prendre rendez-vous' => null]"
    />

    {{-- Étapes --}}
    <ol class="grid grid-cols-3 gap-2 font-mono text-[11px] uppercase tracking-[.06em]" aria-label="Étapes de la prise de rendez-vous">
        @foreach (['Service', 'Créneau', 'Récapitulatif'] as $i => $libelle)
            <li @class([
                'border-t-2 pt-2',
                'border-cyan text-ink' => $this->etape >= $i + 1,
                'border-line text-ink-2' => $this->etape < $i + 1,
            ]) @if ($this->etape === $i + 1) aria-current="step" @endif>
                {{ $i + 1 }}. {{ $libelle }}
            </li>
        @endforeach
    </ol>

    @if ($erreurCreneau !== '')
        <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
            <flux:callout.text>{{ $erreurCreneau }}</flux:callout.text>
        </flux:callout>
    @endif

    @error('service')
        <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @error('throttle')
        <flux:callout variant="danger" icon="exclamation-triangle" role="alert">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <div wire:loading.delay class="font-mono text-[11px] uppercase tracking-[.06em] text-cyan">Chargement…</div>

    {{-- Étape 1 : service --}}
    @if ($this->etape === 1)
        @if ($this->services->isEmpty())
            <x-tn.empty icon="calendar-days" title="Aucun service ne propose de rendez-vous pour le moment" text="Revenez plus tard ou contactez la mairie." />
        @else
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach ($this->services as $service)
                    <li wire:key="service-{{ $service->id }}">
                        @if ($interruption = $service->interruptionEnCours())
                            {{-- F38 : service interrompu, listé mais non sélectionnable. --}}
                            <div class="h-full w-full rounded-md border border-line bg-surface p-4 opacity-80" aria-disabled="true">
                                <span class="block font-semibold text-ink">{{ $service->nom }}</span>
                                <x-tn.status-badge :etat="$interruption->etatBadge()" class="mt-2">Indisponible · {{ $interruption->libelleType() }}</x-tn.status-badge>
                                <span class="mt-2 block text-sm text-ink-2">{{ $interruption->libelleRetour() }}</span>
                                <a href="{{ route('services.show', $service) }}" wire:navigate class="mt-2 inline-block text-sm text-cyan hover:underline">Démarche suspendue pendant l’interruption · que faire en attendant ?</a>
                            </div>
                        @else
                            <button type="button" wire:click="choisirService('{{ $service->slug }}')" class="h-full w-full cursor-pointer rounded-md border border-line bg-surface p-4 text-left transition hover:border-cyan hover:bg-cyan/5 focus-visible:border-cyan">
                                <span class="block font-semibold text-ink">{{ $service->nom }}</span>
                                <span class="mt-2 flex items-start gap-1.5 text-sm text-ink-2">
                                    <flux:icon.map-pin class="mt-0.5 size-4 shrink-0" />
                                    {{ $service->lieuRendezVous() ?? 'Lieu communiqué par le service' }}
                                </span>
                                <span class="mt-1 flex items-center gap-1.5 text-sm text-ink-2">
                                    <flux:icon.clock class="size-4 shrink-0" />
                                    Durée : {{ $service->duree_rendez_vous }} minutes
                                </span>
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    {{-- Étape 2 : créneau --}}
    @if ($this->etape === 2)
        <x-tn.surface class="space-y-1">
            <p class="font-semibold text-ink">{{ $this->service->nom }}</p>
            <p class="text-sm text-ink-2">{{ $this->service->lieuRendezVous() }} · {{ $this->service->duree_rendez_vous }} minutes</p>
            <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="retourServices" class="mt-2">Changer de service</flux:button>
        </x-tn.surface>

        <flux:text>
            Tous les horaires sont en <strong>{{ config('rendez_vous.libelle_fuseau') }}</strong>
            (UTC{{ now(\App\Models\CreneauRendezVous::fuseau())->format('P') }}).
        </flux:text>

        @if ($this->creneauxParJour->isEmpty())
            <x-tn.empty icon="calendar-days" title="Aucun créneau disponible" text="Tous les créneaux de ce service sont réservés. Essayez un autre service ou revenez plus tard.">
                <flux:button wire:click="retourServices" icon="arrow-left">Choisir un autre service</flux:button>
            </x-tn.empty>
        @else
            @php
                $jourLibelle = \Illuminate\Support\Str::ucfirst(
                    \Carbon\CarbonImmutable::parse($this->jourAffiche, \App\Models\CreneauRendezVous::fuseau())->settings(['locale' => 'fr'])->translatedFormat('l j F Y')
                );
            @endphp

            <form wire:submit="validerCreneau" class="space-y-4 rounded-md border border-line bg-surface p-4 md:p-5">
                <div class="grid items-end gap-4 sm:grid-cols-2">
                    <flux:input
                        type="date"
                        wire:model.live="jour"
                        label="Jour"
                        :min="$this->creneauxParJour->keys()->first()"
                        :max="$this->creneauxParJour->keys()->last()"
                        class="cursor-pointer"
                    />

                    @if ($this->creneauxDuJour->isNotEmpty())
                        <flux:select wire:model="creneauSelectionne" label="Horaire" placeholder="Choisir un horaire…" class="cursor-pointer">
                            @foreach ($this->creneauxDuJour as $creneau)
                                <flux:select.option wire:key="creneau-{{ $creneau->id }}" :value="$creneau->id">
                                    {{ $creneau->libelleHeureDebut() }} à {{ $creneau->libelleHeureFin() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                @if ($this->creneauxDuJour->isEmpty())
                    <flux:callout variant="warning" icon="calendar-days">
                        <flux:callout.text>
                            Aucun créneau libre le {{ \Illuminate\Support\Str::lcfirst($jourLibelle) }}.
                            @if ($this->prochainJourLibre)
                                Prochain jour disponible : <strong>{{ \Illuminate\Support\Str::lcfirst($this->prochainJourLibre->libelleDate()) }}</strong>.
                            @endif
                        </flux:callout.text>
                    </flux:callout>
                    @if ($this->prochainJourLibre)
                        <flux:button type="button" icon="arrow-right" wire:click="allerAuJour('{{ $this->prochainJourLibre->jourLocal() }}')">
                            Voir le {{ \Illuminate\Support\Str::lcfirst($this->prochainJourLibre->libelleDate()) }}
                        </flux:button>
                    @endif
                @else
                    <div class="flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-ink-2">
                            <span class="font-medium text-ink">{{ $jourLibelle }}</span>
                            · {{ $this->creneauxDuJour->count() }} horaire(s) libre(s)
                        </p>
                        <flux:button type="submit" variant="primary" icon-trailing="arrow-right" class="w-full sm:w-auto">Continuer</flux:button>
                    </div>
                @endif
            </form>
        @endif
    @endif

    {{-- Étape 3 : récapitulatif avant confirmation --}}
    @if ($this->etape === 3)
        <x-tn.surface>
            <h2 class="tn-display text-lg font-semibold text-ink">Vérifiez votre rendez-vous</h2>
            <p class="mt-1 text-ink-2">{{ $this->creneau->libelleComplet() }}</p>
            <x-rdv.recapitulatif :service="$this->service" :creneau="$this->creneau" class="mt-4" />
        </x-tn.surface>

        <form wire:submit="confirmer" class="space-y-4">
            <flux:textarea wire:model="motif" label="Motif du rendez-vous (facultatif)" rows="3" placeholder="Ex. : demande de copie d’acte de naissance" />

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="confirmer">
                    Confirmer le rendez-vous
                </flux:button>
                <flux:button type="button" variant="ghost" icon="arrow-left" wire:click="retourCreneaux">Choisir un autre créneau</flux:button>
            </div>
        </form>
    @endif
</section>
