<?php

use App\Models\Partner;
use App\Models\PartnerOffering;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Espace partenaire (F99) : ajout et modification d'un service partenaire.
 * partner_id : celui du compte partenaire connecté (toute valeur envoyée est ignorée), ou choisi et validé par un admin.
 * created_by et slug sont assignés dans le code, jamais par assignation de masse.
 */
new #[Title('Espace partenaire — Service')] class extends Component {
    #[Locked]
    public ?PartnerOffering $record = null;

    public string $title = '';
    public string $description = '';
    public string $conditions = '';
    public string $status = PartnerOffering::STATUS_AVAILABLE;
    public string $unavailable_until = '';
    public string $booking_url = '';
    public string $contact_phone = '';
    public string $contact_email = '';
    public string $alternative_text = '';
    public string $alternative_url = '';
    public bool $is_published = true;

    /** Utilisé uniquement pour un admin (PartnerOfferingPolicy::choosePartner). */
    public string $partner_id = '';

    /** @var array<string, array<int, array{start: string, end: string}>> */
    public array $hours = [];

    public function mount(?PartnerOffering $partnerOffering = null): void
    {
        if ($partnerOffering?->exists) {
            $this->authorize('update', $partnerOffering);
            $this->record = $partnerOffering;
            $this->title = (string) $partnerOffering->title;
            $this->description = (string) $partnerOffering->description;
            $this->conditions = (string) ($partnerOffering->conditions ?? '');
            $this->status = (string) $partnerOffering->status;
            $this->unavailable_until = $partnerOffering->unavailable_until?->format('Y-m-d') ?? '';
            $this->booking_url = (string) ($partnerOffering->booking_url ?? '');
            $this->contact_phone = (string) ($partnerOffering->contact_phone ?? '');
            $this->contact_email = (string) ($partnerOffering->contact_email ?? '');
            $this->alternative_text = (string) ($partnerOffering->alternative_text ?? '');
            $this->alternative_url = (string) ($partnerOffering->alternative_url ?? '');
            $this->is_published = (bool) $partnerOffering->is_published;
            $this->partner_id = (string) $partnerOffering->partner_id;
        } else {
            $this->authorize('create', PartnerOffering::class);
        }

        $planning = $partnerOffering?->exists && $partnerOffering->aDesHorairesPropres() ? $partnerOffering->horaires() : null;

        foreach (Partner::JOURS as $jour) {
            $plages = $planning?->hoursFor($jour) ?? [];

            for ($i = 0; $i < Partner::PLAGES_PAR_JOUR; $i++) {
                $this->hours[$jour][$i] = ['start' => $plages[$i]['start'] ?? '', 'end' => $plages[$i]['end'] ?? ''];
            }
        }
    }

    public function choisitPartenaire(): bool
    {
        return auth()->user()->can('choosePartner', PartnerOffering::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:3000'],
            'conditions' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(PartnerOffering::STATUS_OPTIONS)],
            'unavailable_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            // URLs externes : https uniquement (pas de javascript:, data:, ni http en clair).
            'booking_url' => ['nullable', 'url:https', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ().-]{6,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'alternative_text' => ['nullable', 'string', 'max:255'],
            'alternative_url' => ['nullable', 'url:https', 'max:255'],
            'is_published' => ['boolean'],
            'partner_id' => $this->choisitPartenaire() ? ['required', Rule::exists(Partner::class, 'id')] : ['nullable'],
            'hours' => ['array'],
            'hours.*' => ['array', 'max:'.Partner::PLAGES_PAR_JOUR],
            'hours.*.*.start' => ['nullable', 'date_format:H:i', 'required_with:hours.*.*.end'],
            'hours.*.*.end' => ['nullable', 'date_format:H:i', 'required_with:hours.*.*.start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => 'titre',
            'status' => 'état',
            'unavailable_until' => 'date de retour',
            'booking_url' => 'lien de réservation',
            'contact_phone' => 'téléphone',
            'contact_email' => 'e-mail',
            'alternative_text' => 'alternative',
            'alternative_url' => 'lien de l\'alternative',
            'partner_id' => 'partenaire',
            'hours.*.*.start' => 'heure d\'ouverture',
            'hours.*.*.end' => 'heure de fermeture',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'booking_url.url' => 'Le lien de réservation doit être une adresse sécurisée commençant par https://.',
            'alternative_url.url' => 'Le lien de l\'alternative doit être une adresse sécurisée commençant par https://.',
        ];
    }

    /**
     * Admin : liste des partenaires proposés.
     *
     * @return Collection<int, Partner>
     */
    #[Computed]
    public function partnerOptions(): Collection
    {
        return $this->choisitPartenaire() ? Partner::query()->orderBy('name')->get(['id', 'name']) : new Collection;
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', PartnerOffering::class);

        $validated = $this->validate();
        $openingHours = Partner::horairesDepuisSaisie($this->hours);

        foreach (['conditions', 'unavailable_until', 'booking_url', 'contact_phone', 'contact_email', 'alternative_text', 'alternative_url'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        if ($validated['status'] !== PartnerOffering::STATUS_UNAVAILABLE) {
            $validated['unavailable_until'] = null;
        }

        $record = $this->record ?? new PartnerOffering;
        $record->fill(collect($validated)->except(['partner_id', 'hours'])->all());
        // Aucun jour renseigné : le service reprend les horaires du partenaire.
        $record->opening_hours = collect($openingHours)->flatten()->isEmpty() ? null : $openingHours;

        // partner_id n'est jamais pris de la requête pour un compte partenaire : c'est toujours le sien.
        if ($this->choisitPartenaire()) {
            $record->partner()->associate(Partner::findOrFail((int) $validated['partner_id']));
        } elseif (! $record->exists) {
            $record->partner()->associate(Partner::findOrFail((int) auth()->user()->partner_id));
        }

        if (! $record->exists) {
            $record->creator()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: __('Service partenaire enregistré.'));

        $this->redirectRoute('partner.offerings.index', navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        :label="__('Espace partenaire')"
        :breadcrumb="[__('Espace partenaire') => route('partner.offerings.index'), ($record ? __('Modifier') : __('Ajouter')) => null]"
        :title="$record ? __('Modifier le service') : __('Ajouter un service')"
        :subtitle="__('Les champs marqués * sont obligatoires. Un service publié apparaît dans le catalogue des habitants.')"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        @if ($this->choisitPartenaire())
            <flux:select wire:model="partner_id" :label="__('Partenaire *')" :placeholder="__('Choisir…')" required>
                @foreach ($this->partnerOptions as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:input wire:model="title" :label="__('Titre *')" required maxlength="150" />

        <flux:textarea wire:model="description" :label="__('Description *')" rows="4" required />

        <flux:textarea wire:model="conditions" :label="__('Conditions (public visé, tarif, pièces à apporter…)')" rows="3" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="status" :label="__('État *')">
                @foreach (PartnerOffering::STATUS_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ __($libelle) }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($status === PartnerOffering::STATUS_UNAVAILABLE)
                <flux:input wire:model="unavailable_until" type="date" :label="__('Indisponible jusqu\'au (facultatif)')" />
            @endif
        </div>

        <fieldset class="space-y-3">
            <legend class="font-medium">{{ __('Prochaine action proposée aux habitants') }}</legend>
            <flux:input wire:model="booking_url" type="url" :label="__('Lien de réservation en ligne (https://)')" placeholder="https://" :description="__('Renseigné : le bouton « Réserver » s\'affiche quand le service est disponible. Sinon : « Contacter ».')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="contact_phone" type="tel" :label="__('Téléphone de contact')" placeholder="+261 20 00 000 00" :description="__('Vide : téléphone du partenaire.')" />
                <flux:input wire:model="contact_email" type="email" :label="__('E-mail de contact')" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="alternative_text" :label="__('Alternative (texte)')" :placeholder="__('Ex. : l\'épicerie solidaire accueille le samedi')" />
                <flux:input wire:model="alternative_url" type="url" :label="__('Lien de l\'alternative (https://)')" placeholder="https://" />
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="font-medium">{{ __('Horaires du service (heure de Nova Terra, 2 plages maximum par jour)') }}</legend>
            <flux:text class="text-sm">{{ __('Laissez tout vide pour reprendre les horaires du partenaire.') }}</flux:text>

            @foreach (Partner::JOURS as $jour)
                <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-[6rem_minmax(0,1fr)]" wire:key="jour-{{ $jour }}">
                    <span class="text-sm font-medium sm:pt-2">{{ ucfirst($jour) }}</span>
                    <div class="grid min-w-0 gap-2 md:grid-cols-2">
                        @for ($i = 0; $i < Partner::PLAGES_PAR_JOUR; $i++)
                            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-start gap-1">
                                <flux:input type="time" size="sm" wire:model="hours.{{ $jour }}.{{ $i }}.start" :aria-label="ucfirst($jour).', plage '.($i + 1).', ouverture'" />
                                <span class="pt-1.5 text-ink-2" aria-hidden="true">–</span>
                                <flux:input type="time" size="sm" wire:model="hours.{{ $jour }}.{{ $i }}.end" :aria-label="ucfirst($jour).', plage '.($i + 1).', fermeture'" />
                            </div>
                        @endfor
                    </div>
                </div>
            @endforeach
        </fieldset>

        <flux:checkbox wire:model="is_published" :label="__('Publié (visible dans le catalogue des habitants)')" />

        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </flux:button>
            <flux:button :href="route('partner.offerings.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
