<?php

use App\Models\Partner;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Espace agent (F74) : ajout et modification d'un partenaire (PartnerPolicy : agents et admins).
 * created_by et is_published sont assignés dans le code, jamais par assignation de masse.
 */
new #[Layout('layouts::agent'), Title('Espace agent — Partenaire')] class extends Component {
    #[Locked]
    public ?Partner $record = null;

    public string $name = '';
    public string $type = 'sante';
    public string $description = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';
    public string $latitude = '';
    public string $longitude = '';
    public bool $is_published = true;

    /** @var array<string, array<int, array{start: string, end: string}>> */
    public array $hours = [];

    public function mount(?Partner $partner = null): void
    {
        if ($partner?->exists) {
            $this->authorize('update', $partner);
            $this->record = $partner;
            $this->name = (string) $partner->name;
            $this->type = (string) $partner->type;
            $this->description = (string) ($partner->description ?? '');
            $this->address = (string) $partner->address;
            $this->phone = (string) $partner->phone;
            $this->email = (string) ($partner->email ?? '');
            $this->website = (string) ($partner->website ?? '');
            $this->latitude = (string) $partner->latitude;
            $this->longitude = (string) $partner->longitude;
            $this->is_published = (bool) $partner->is_published;
        } else {
            $this->authorize('create', Partner::class);
        }

        foreach (Partner::JOURS as $jour) {
            $plages = $partner?->exists ? $partner->hoursFor($jour) : [];

            for ($i = 0; $i < Partner::PLAGES_PAR_JOUR; $i++) {
                $this->hours[$jour][$i] = ['start' => $plages[$i]['start'] ?? '', 'end' => $plages[$i]['end'] ?? ''];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Partner::TYPE_OPTIONS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ().-]{6,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_published' => ['boolean'],
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
            'name' => 'nom',
            'address' => 'adresse',
            'phone' => 'téléphone',
            'website' => 'site web',
            'hours.*.*.start' => 'heure d\'ouverture',
            'hours.*.*.end' => 'heure de fermeture',
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Partner::class);

        $validated = $this->validate();
        $openingHours = $this->openingHours();

        foreach (['description', 'email', 'website'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $data = collect($validated)->except(['hours', 'is_published'])->all();
        $data['opening_hours'] = $openingHours;

        $record = $this->record ?? new Partner;
        $record->fill($data);
        $record->is_published = $this->is_published;

        if (! $record->exists) {
            $record->creator()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: 'Partenaire enregistré.');

        $this->redirectRoute('partners.show', $record);
    }

    /**
     * Plages saisies, débarrassées des lignes vides ; chaque plage doit commencer avant de finir
     * et ne pas chevaucher la précédente.
     *
     * @return array<string, array<int, array{start: string, end: string}>>
     */
    private function openingHours(): array
    {
        $semaine = [];
        $erreurs = [];

        foreach (Partner::JOURS as $jour) {
            $plages = [];

            foreach (array_slice((array) ($this->hours[$jour] ?? []), 0, Partner::PLAGES_PAR_JOUR) as $i => $plage) {
                $debut = (string) ($plage['start'] ?? '');
                $fin = (string) ($plage['end'] ?? '');

                if ($debut === '' && $fin === '') {
                    continue;
                }

                if ($debut >= $fin) {
                    $erreurs["hours.{$jour}.{$i}.end"] = "Le {$jour}, l'heure de fermeture doit être après l'heure d'ouverture.";
                } elseif ($plages !== [] && $debut < end($plages)['end']) {
                    $erreurs["hours.{$jour}.{$i}.start"] = "Le {$jour}, la seconde plage doit commencer après la fin de la première.";
                }

                $plages[] = ['start' => $debut, 'end' => $fin];
            }

            $semaine[$jour] = $plages;
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        return $semaine;
    }

    public function delete(): void
    {
        abort_if($this->record === null, 404);
        $this->authorize('delete', $this->record);

        $this->record->delete();

        Flux::toast(variant: 'success', text: 'Partenaire supprimé.');

        $this->redirectRoute('agent.partners.index');
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Partenaires' => route('agent.partners.index'), ($record ? 'Modifier' : 'Ajouter') => null]"
        :title="$record ? 'Modifier le partenaire' : 'Ajouter un partenaire'"
    />

    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Nom')" required />

            <flux:select wire:model="type" :label="__('Type')">
                @foreach (Partner::TYPE_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:textarea wire:model="description" :label="__('Description courte')" rows="2" />

        <flux:input wire:model="address" :label="__('Adresse')" required />

        <div class="grid gap-4 sm:grid-cols-3">
            <flux:input wire:model="phone" :label="__('Téléphone')" type="tel" placeholder="+261 20 00 000 00" required />
            <flux:input wire:model="email" :label="__('E-mail (facultatif)')" type="email" />
            <flux:input wire:model="website" :label="__('Site web (facultatif)')" type="url" placeholder="https://" />
        </div>

        <fieldset class="space-y-3">
            <legend class="font-medium">{{ __('Horaires (heure de Nova Terra, 2 plages maximum par jour)') }}</legend>
            <flux:text class="text-sm">{{ __('Laissez vide un jour de fermeture.') }}</flux:text>

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

        <div class="space-y-2">
            <flux:heading size="sm">{{ __('Emplacement sur la carte') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="latitude" :label="__('Latitude')" type="number" step="any" required />
                <flux:input wire:model="longitude" :label="__('Longitude')" type="number" step="any" required />
            </div>
            <x-carte mode="choix" :label="'Emplacement du partenaire'" />
        </div>

        <flux:checkbox wire:model="is_published" :label="__('Publié (visible sur la page publique)')" />

        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </flux:button>
            <flux:button :href="route('agent.partners.index')" variant="ghost">{{ __('Annuler') }}</flux:button>
            @if ($record)
                @can('delete', $record)
                <flux:button variant="danger" class="ms-auto" wire:click="delete" wire:confirm="{{ __('Supprimer ce partenaire ?') }}">{{ __('Supprimer') }}</flux:button>
                @endcan
            @endif
        </div>
    </form>
</section>
