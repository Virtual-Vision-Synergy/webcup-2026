<?php

use App\Models\LigneTransport;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ligne de transport')] class extends Component {
    #[Locked]
    public ?LigneTransport $record = null;

    public string $numero = '';
    public string $nom = '';
    public string $mode = 'bus';
    public string $arrets = '';
    public string $horaires = '';
    public string $frequence = '';
    public string $etat = 'normal';
    public string $perturbation = '';

    public function mount(?LigneTransport $ligneTransport = null): void
    {
        if ($ligneTransport?->exists) {
            $this->authorize('update', $ligneTransport);
            $this->record = $ligneTransport;
            $this->numero = (string) $ligneTransport->numero;
            $this->nom = (string) $ligneTransport->nom;
            $this->mode = (string) $ligneTransport->mode;
            $this->arrets = (string) $ligneTransport->arrets;
            $this->horaires = (string) $ligneTransport->horaires;
            $this->frequence = (string) ($ligneTransport->frequence ?? '');
            $this->etat = (string) $ligneTransport->etat;
            $this->perturbation = (string) ($ligneTransport->perturbation ?? '');
        } else {
            $this->authorize('create', LigneTransport::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:20'],
            'nom' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(LigneTransport::MODE_OPTIONS)],
            'arrets' => ['required', 'string', 'max:5000'],
            'horaires' => ['required', 'string', 'max:2000'],
            'frequence' => ['nullable', 'string', 'max:255'],
            'etat' => ['required', Rule::in(LigneTransport::ETAT_OPTIONS)],
            'perturbation' => [Rule::requiredIf($this->etat !== 'normal'), 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'numero' => 'numéro',
            'arrets' => 'arrêts',
            'etat' => 'état du trafic',
            'frequence' => 'fréquence',
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', LigneTransport::class);

        $validated = $this->validate();

        $record = $this->record ?? new LigneTransport;
        $record->fill([
            'numero' => $validated['numero'],
            'nom' => $validated['nom'],
            'mode' => $validated['mode'],
            'arrets' => $validated['arrets'],
            'horaires' => $validated['horaires'],
            'frequence' => $validated['frequence'] ?: null,
        ]);
        // État du trafic : champ réservé, assigné explicitement (hors #[Fillable]).
        $record->etat = $validated['etat'];
        $record->perturbation = $validated['etat'] === 'normal' ? null : $validated['perturbation'];

        if (! $record->exists) {
            $record->user()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: 'Ligne enregistrée.');

        $this->redirectRoute('transports.show', $record, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Mobilité"
        :title="$record ? 'Modifier la ligne '.$record->numero : 'Ajouter une ligne'"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Transports' => route('transports.index'), 'Ligne '.$record->numero => route('transports.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Transports' => route('transports.index'), 'Nouvelle ligne' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
            <flux:input wire:model="numero" label="Numéro" placeholder="4, N1…" required />
            <flux:input wire:model="nom" label="Nom de la ligne" placeholder="Port – Hôpital régional" required />
        </div>

        <flux:select wire:model="mode" label="Mode de transport" required>
            @foreach (LigneTransport::MODE_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="arrets" label="Arrêts (un par ligne, dans l'ordre du parcours)" rows="6" required />

        <flux:textarea wire:model="horaires" label="Horaires" rows="3" placeholder="Lundi au vendredi : 5 h 30 – 21 h 00" required />

        <flux:input wire:model="frequence" label="Fréquence" placeholder="Toutes les 10 min" />

        <flux:select wire:model.live="etat" label="État du trafic" required>
            @foreach (LigneTransport::ETAT_LABELS as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($etat !== 'normal')
            <flux:textarea wire:model="perturbation" label="Message de perturbation" rows="3" placeholder="Cause, arrêts concernés, durée, itinéraire conseillé…" required />
        @endif

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </flux:button>
            <flux:button :href="$record ? route('transports.show', $record) : route('transports.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
