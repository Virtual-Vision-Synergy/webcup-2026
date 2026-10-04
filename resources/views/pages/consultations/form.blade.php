<?php

use App\Models\ActionLog;
use App\Models\Annonce;
use App\Models\Consultation;
use App\Models\Quartier;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F65 : création d'une consultation des habitants, agents et admins uniquement (ConsultationPolicy::create).
 * Les dates sont saisies en heure de Madagascar (Annonce::FUSEAU) et stockées en UTC.
 */
new #[Title('Nouvelle consultation')] class extends Component {
    public string $question = '';
    public string $explication = '';
    public string $options = "Pour\nContre\nSans avis";
    public string $quartier_id = '';
    public string $ouverture_le = '';
    public string $cloture_le = '';

    public function mount(): void
    {
        $this->authorize('create', Consultation::class);

        $maintenant = now(Annonce::FUSEAU);
        $this->ouverture_le = $maintenant->format('Y-m-d\TH:i');
        $this->cloture_le = $maintenant->addWeek()->format('Y-m-d\TH:i');
    }

    /**
     * @return Collection<int, Quartier>
     */
    #[Computed]
    public function quartiers(): Collection
    {
        return Quartier::query()->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'explication' => ['required', 'string', 'max:5000'],
            'options' => ['required', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                $options = Consultation::decouperOptions((string) $value);

                if (count($options) < Consultation::OPTIONS_MIN || count($options) > Consultation::OPTIONS_MAX) {
                    $fail(__('Indiquez entre :min et :max options de réponse, une par ligne.', ['min' => Consultation::OPTIONS_MIN, 'max' => Consultation::OPTIONS_MAX]));
                } elseif (collect($options)->contains(fn (string $option): bool => mb_strlen($option) > 150)) {
                    $fail(__('Chaque option de réponse doit faire au plus 150 caractères.'));
                } elseif (count(array_unique(array_map('mb_strtolower', $options))) !== count($options)) {
                    $fail(__('Deux options de réponse sont identiques.'));
                }
            }],
            'quartier_id' => ['nullable', 'integer', Rule::exists('quartiers', 'id')],
            'ouverture_le' => ['required', 'date'],
            'cloture_le' => ['required', 'date', 'after:ouverture_le'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'cloture_le.after' => __('La clôture doit être après l’ouverture.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'explication' => __('explication'),
            'options' => __('options de réponse'),
            'quartier_id' => __('public'),
            'ouverture_le' => __('date d’ouverture'),
            'cloture_le' => __('date de clôture'),
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Consultation::class);

        $validated = $this->validate();

        $consultation = new Consultation([
            'question' => $validated['question'],
            'explication' => $validated['explication'],
            'options' => implode("\n", Consultation::decouperOptions($validated['options'])),
            'quartier_id' => $validated['quartier_id'] ?: null,
            'ouverture_le' => Carbon::parse($validated['ouverture_le'], Annonce::FUSEAU)->utc(),
            'cloture_le' => Carbon::parse($validated['cloture_le'], Annonce::FUSEAU)->utc(),
        ]);
        $consultation->user()->associate(auth()->user());
        $consultation->save();

        ActionLog::record('consultation_creee', $consultation);

        Flux::toast(variant: 'success', text: __('Consultation créée : les habitants concernés la voient dès son ouverture.'));

        $this->redirectRoute('consultations.show', $consultation, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Vie de la ville') }}"
        title="{{ __('Nouvelle consultation') }}"
        :breadcrumb="['Mon espace' => route('dashboard'), 'Consultations' => route('consultations.index'), 'Nouvelle consultation' => null]"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <x-tn.mention-obligatoire />

        <flux:input wire:model="question" label="{{ __('Question posée aux habitants') }}" placeholder="{{ __('Faut-il rendre la rue du Marché piétonne le samedi ?') }}" maxlength="255" required />

        <flux:textarea wire:model="explication" label="{{ __('Explication') }}" description="{{ __('Le contexte en langage simple : ce qui est envisagé, pourquoi, ce que cela change pour les habitants.') }}" rows="5" required />

        <flux:textarea wire:model="options" label="{{ __('Options de réponse (une par ligne)') }}" description="{{ __('Entre 2 et 8 options. L’habitant en choisit une seule.') }}" rows="4" required />

        <flux:select wire:model="quartier_id" label="{{ __('Public') }}">
            <flux:select.option value="">{{ __('Tous les habitants') }}</flux:select.option>
            @foreach ($this->quartiers as $q)
                <flux:select.option :value="$q->id">{{ __('Quartier :nom', ['nom' => $q->nom]) }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="ouverture_le" type="datetime-local" label="{{ __('Ouverture') }}" required />
            <flux:input wire:model="cloture_le" type="datetime-local" label="{{ __('Clôture') }}" required />
        </div>
        <flux:text class="text-xs">{{ __('Heure de Madagascar. À la clôture, la répartition des réponses est publiée aux participants.') }}</flux:text>

        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Créer la consultation') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </flux:button>
            <flux:button :href="route('consultations.index')" variant="ghost" wire:navigate>{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
