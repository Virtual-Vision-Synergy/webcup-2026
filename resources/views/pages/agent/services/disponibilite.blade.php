<?php

use App\Models\Service;
use App\Models\ServiceInterruption;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Espace agent (F38) : marquer un service indisponible (maintenance, incident), mettre à jour l'interruption, la rétablir.
 * Le service vient de la route (#[Locked]) ; service_id, debut_at, created_by, retabli_at et retabli_par sont assignés ici.
 */
new #[Layout('layouts::agent'), Title('Espace agent — Disponibilité du service')] class extends Component {
    /** Format du champ « date et heure » du navigateur (heure de Nova Terra). */
    private const FORMAT_SAISIE = 'Y-m-d\TH:i';

    #[Locked]
    public Service $service;

    public string $type = 'maintenance';

    public string $motif = '';

    public string $retour_prevu_at = '';

    public string $alternative = '';

    public string $alternative_service_id = '';

    public function mount(Service $service): void
    {
        $this->authorize('manageAvailability', $service);
        $this->service = $service;
        $this->remplirDepuis($service->interruptionEnCours());
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ServiceInterruption::TYPE_OPTIONS)],
            'motif' => ['required', 'string', 'max:255'],
            'retour_prevu_at' => ['nullable', 'date_format:'.self::FORMAT_SAISIE, function (string $attribute, mixed $value, \Closure $fail): void {
                $date = $this->dateRetour((string) $value);
                if ($date !== null && $date->isPast()) {
                    $fail('La date de retour prévue doit être dans le futur.');
                }
            }],
            'alternative' => ['required', 'string', 'max:2000'],
            'alternative_service_id' => ['nullable', 'integer', Rule::exists(Service::class, 'id'), Rule::notIn([$this->service->id]), function (string $attribute, mixed $value, \Closure $fail): void {
                if (Service::query()->whereKey((int) $value)->whereHas('interruptionCourante')->exists()) {
                    $fail('Le service alternatif est lui aussi indisponible : choisissez-en un autre.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'alternative_service_id.not_in' => 'Le service alternatif doit être différent du service interrompu.',
            'retour_prevu_at.date_format' => 'La date de retour prévue n’est pas valide.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'type' => 'type d’interruption',
            'motif' => 'motif',
            'retour_prevu_at' => 'date de retour prévue',
            'alternative' => 'que faire en attendant',
            'alternative_service_id' => 'service alternatif',
        ];
    }

    #[Computed]
    public function interruption(): ?ServiceInterruption
    {
        return $this->service->interruptionCourante()->first();
    }

    /**
     * Autres services, disponibles, proposés comme alternative.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function servicesAlternatifs(): Collection
    {
        return Service::query()->disponibles()->whereKeyNot($this->service->id)->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * Dernières interruptions du service (historique, espace agent uniquement).
     *
     * @return Collection<int, ServiceInterruption>
     */
    #[Computed]
    public function historique(): Collection
    {
        return $this->service->interruptions()->with(['auteur:id,name', 'retablissement:id,name'])->limit(10)->get();
    }

    public function save(): void
    {
        $this->authorize('manageAvailability', $this->service);

        $validated = $this->validate();

        $donnees = [
            'type' => $validated['type'],
            'motif' => $validated['motif'],
            'retour_prevu_at' => $this->dateRetour((string) ($validated['retour_prevu_at'] ?? ''))?->utc(),
            'alternative' => $validated['alternative'],
            'alternative_service_id' => filled($validated['alternative_service_id'] ?? null) ? (int) $validated['alternative_service_id'] : null,
        ];

        // Une seule interruption en cours par service : on met à jour celle qui existe.
        $interruption = $this->interruption;

        if ($interruption !== null) {
            $motifAvant = $interruption->motif;
            $interruption->update($donnees);

            AuditLogger::log('updated', $this->service, ['interruption' => ['avant' => $motifAvant, 'apres' => $interruption->motif]]);
            Flux::toast(variant: 'success', text: 'Interruption mise à jour.');
        } else {
            $interruption = new ServiceInterruption($donnees);
            $interruption->service()->associate($this->service);
            $interruption->auteur()->associate(auth()->user());
            $interruption->debut_at = now();
            $interruption->save();

            AuditLogger::log('status_changed', $this->service, ['disponibilite' => ['avant' => 'Disponible', 'apres' => 'Indisponible ('.$interruption->libelleType().')']]);
            Flux::toast(variant: 'success', text: 'Service marqué indisponible. Les habitants sont prévenus.');
        }

        unset($this->interruption, $this->historique);
        $this->remplirDepuis($interruption);
    }

    public function retablir(): void
    {
        $this->authorize('manageAvailability', $this->service);

        $interruption = $this->interruption;

        Flux::modal('retablir')->close();

        if ($interruption === null) {
            Flux::toast(text: 'Ce service est déjà disponible.');

            return;
        }

        $interruption->retabli_at = now();
        $interruption->retablissement()->associate(auth()->user());
        $interruption->save();

        AuditLogger::log('status_changed', $this->service, ['disponibilite' => ['avant' => 'Indisponible ('.$interruption->libelleType().')', 'apres' => 'Disponible']]);

        unset($this->interruption, $this->historique, $this->servicesAlternatifs);
        $this->remplirDepuis(null);
        $this->resetValidation();

        Flux::toast(variant: 'success', text: 'Service rétabli : les démarches sont de nouveau possibles.');
    }

    private function remplirDepuis(?ServiceInterruption $interruption): void
    {
        $this->type = $interruption?->type ?? 'maintenance';
        $this->motif = (string) ($interruption?->motif ?? '');
        $this->retour_prevu_at = (string) $interruption?->retour_prevu_at?->setTimezone(ServiceInterruption::fuseau())->format(self::FORMAT_SAISIE);
        $this->alternative = (string) ($interruption?->alternative ?? '');
        $this->alternative_service_id = (string) ($interruption?->alternative_service_id ?? '');
    }

    /**
     * Saisie « date et heure » en heure de Nova Terra → date (null si vide ou invalide).
     */
    private function dateRetour(string $valeur): ?CarbonImmutable
    {
        if ($valeur === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.self::FORMAT_SAISIE, $valeur, ServiceInterruption::fuseau());
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date : null;
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Disponibilité du service"
        :title="$service->nom"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Services' => route('agent.services.index'), $service->nom => null]"
    >
        <x-slot:actions>
            <flux:button icon="eye" :href="route('services.show', $service)" wire:navigate>Voir la fiche publique</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.surface class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-ink-2">Statut actuel :</span>
        @if ($this->interruption)
            <x-tn.status-badge :etat="$this->interruption->etatBadge()">Indisponible · {{ $this->interruption->libelleType() }}</x-tn.status-badge>
            <span class="text-sm text-ink-2">depuis le {{ \App\Models\ServiceInterruption::libelleDate($this->interruption->debut_at) }} · {{ $this->interruption->libelleRetour() }}</span>
        @else
            <x-tn.status-badge etat="normal">Disponible</x-tn.status-badge>
        @endif
    </x-tn.surface>

    <form wire:submit="save" class="space-y-5 rounded-md border border-line bg-surface p-4 md:p-6">
        <h2 class="tn-display text-lg font-semibold text-ink">{{ $this->interruption ? 'Mettre à jour l’interruption' : 'Signaler une interruption' }}</h2>

        <flux:select wire:model="type" label="Type d’interruption">
            @foreach (\App\Models\ServiceInterruption::TYPE_LABELS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="motif" label="Motif" placeholder="Ex. Panne du logiciel de délivrance des actes" maxlength="255" required />

        <flux:input type="datetime-local" wire:model="retour_prevu_at" label="Retour prévu (facultatif)" description="Heure de Nova Terra. Laissez vide si la date n’est pas encore connue. Le service ne redevient disponible qu’avec « Rétablir le service »." />

        <flux:textarea wire:model="alternative" label="Que faire en attendant ?" placeholder="Ex. Rendez-vous possible à la mairie annexe, 12 rue du Port, 8 h – 12 h" rows="3" required />

        <flux:select wire:model="alternative_service_id" label="Service alternatif (facultatif)">
            <flux:select.option value="">Aucun</flux:select.option>
            @foreach ($this->servicesAlternatifs as $option)
                <flux:select.option value="{{ $option->id }}">{{ $option->nom }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
            <flux:button type="submit" variant="primary" icon="exclamation-triangle">
                <span wire:loading.remove wire:target="save">{{ $this->interruption ? 'Mettre à jour' : 'Marquer indisponible' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </flux:button>

            @if ($this->interruption)
                <flux:modal.trigger name="retablir">
                    <flux:button type="button" icon="check-circle">Rétablir le service</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    </form>

    <flux:modal name="retablir" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Rétablir le service ?</flux:heading>
                <flux:text class="mt-2">« {{ $service->nom }} » sera de nouveau affiché comme disponible et les démarches redeviendront possibles.</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Annuler</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" icon="check-circle" wire:click="retablir">Rétablir</flux:button>
            </div>
        </div>
    </flux:modal>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">Historique des interruptions</x-tn.section-label>
        @if ($this->historique->isEmpty())
            <flux:text>Aucune interruption enregistrée pour ce service.</flux:text>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->historique as $passee)
                    <li wire:key="interruption-{{ $passee->id }}" class="py-3 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tn.status-badge :etat="$passee->retabli_at ? 'normal' : $passee->etatBadge()">{{ $passee->libelleType() }} · {{ $passee->retabli_at ? 'Rétabli' : 'En cours' }}</x-tn.status-badge>
                            <span class="font-medium text-ink">{{ $passee->motif }}</span>
                        </div>
                        <p class="mt-1 text-ink-2">
                            Du {{ \App\Models\ServiceInterruption::libelleDate($passee->debut_at) }}
                            @if ($passee->retabli_at)
                                au {{ \App\Models\ServiceInterruption::libelleDate($passee->retabli_at) }}
                            @endif
                            · signalé par {{ $passee->auteur?->name ?? 'compte supprimé' }}
                            @if ($passee->retabli_at)
                                · rétabli par {{ $passee->retablissement?->name ?? 'compte supprimé' }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-tn.surface>
</section>
