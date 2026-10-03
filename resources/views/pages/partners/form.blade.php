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
 * Ajout et modification d'un partenaire (F74) : agents et admins uniquement (PartnerPolicy).
 * Horaires saisis jour par jour (2 plages au plus) ; emplacement à la main ou par clic sur la carte.
 * created_by, slug et is_published sont assignés dans le code (hors #[Fillable]).
 */
new #[Layout('layouts::agent'), Title('Partenaire')] class extends Component {
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

    /** @var array<int, array<int, array{start: string, end: string}>> jour ISO => 2 plages */
    public array $hours = [];

    public function mount(?Partner $partner = null): void
    {
        foreach (array_keys(Partner::JOURS) as $jour) {
            $this->hours[$jour] = [['start' => '', 'end' => ''], ['start' => '', 'end' => '']];
        }

        if ($partner?->exists) {
            $this->authorize('update', $partner);
            $this->record = $partner;
            $this->fill($partner->only(['name', 'type', 'address', 'phone']));
            $this->description = (string) $partner->description;
            $this->email = (string) $partner->email;
            $this->website = (string) $partner->website;
            $this->latitude = (string) $partner->latitude;
            $this->longitude = (string) $partner->longitude;
            $this->is_published = $partner->is_published;

            foreach (array_keys(Partner::JOURS) as $jour) {
                foreach ($partner->hoursFor($jour) as $i => [$debut, $fin]) {
                    if ($i < 2) {
                        $this->hours[$jour][$i] = ['start' => $debut, 'end' => $fin];
                    }
                }
            }
        } else {
            $this->authorize('create', Partner::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
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
        ];

        foreach (array_keys(Partner::JOURS) as $jour) {
            foreach ([0, 1] as $i) {
                $rules["hours.{$jour}.{$i}.start"] = ['nullable', 'date_format:H:i', "required_with:hours.{$jour}.{$i}.end"];
                $rules["hours.{$jour}.{$i}.end"] = ['nullable', 'date_format:H:i', "required_with:hours.{$jour}.{$i}.start"];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.regex' => __('Numéro de téléphone invalide (chiffres, espaces et + uniquement).'),
            'website.url' => __('Adresse de site invalide : elle doit commencer par http:// ou https://.'),
            'latitude.between' => __('La latitude doit être comprise entre -90 et 90.'),
            'longitude.between' => __('La longitude doit être comprise entre -180 et 180.'),
            'hours.*.*.start.date_format' => __('Heure au format HH:MM (ex. 08:30).'),
            'hours.*.*.end.date_format' => __('Heure au format HH:MM (ex. 17:30).'),
            'hours.*.*.start.required_with' => __('Indiquez l\'heure d\'ouverture de cette plage.'),
            'hours.*.*.end.required_with' => __('Indiquez l\'heure de fermeture de cette plage.'),
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Partner::class);

        $validated = $this->validate();
        $validated['opening_hours'] = $this->openingHours();
        unset($validated['hours'], $validated['is_published']);

        foreach (['description', 'email', 'website'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $record = $this->record ?? new Partner;
        $record->fill($validated);
        $record->forceFill(['is_published' => $this->is_published]);

        if (! $record->exists) {
            $record->creator()->associate(auth()->user());
        }

        $record->save();

        Flux::toast(variant: 'success', text: __('Partenaire enregistré.'));

        $this->redirectRoute('agent.partners.index', navigate: true);
    }

    /**
     * Plages cohérentes (début < fin, 2e plage après la 1re) au format du modèle.
     *
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    private function openingHours(): array
    {
        $resultat = [];
        $erreurs = [];

        foreach (array_keys(Partner::JOURS) as $jour) {
            $plages = [];

            foreach ([0, 1] as $i) {
                $debut = (string) ($this->hours[$jour][$i]['start'] ?? '');
                $fin = (string) ($this->hours[$jour][$i]['end'] ?? '');

                if ($debut === '' && $fin === '') {
                    continue;
                }

                if ($fin <= $debut) {
                    $erreurs["hours.{$jour}.{$i}.end"] = __('L\'heure de fermeture doit être après l\'heure d\'ouverture.');

                    continue;
                }

                if ($plages !== [] && $debut < end($plages)[1]) {
                    $erreurs["hours.{$jour}.{$i}.start"] = __('La deuxième plage doit commencer après la fin de la première.');

                    continue;
                }

                $plages[] = [$debut, $fin];
            }

            if ($plages !== []) {
                $resultat[$jour] = $plages;
            }
        }

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        return $resultat;
    }
}; ?>

<section class="w-full max-w-3xl space-y-6">
    <x-tn.page-header
        :label="__('Espace agent')"
        :title="$record ? __('Modifier : :nom', ['nom' => $record->name]) : __('Ajouter un partenaire')"
        :breadcrumb="['Partenaires' => route('agent.partners.index'), ($record ? 'Modifier' : 'Ajouter') => null]"
    />

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Nom')" required class="sm:col-span-2" />
            <flux:select wire:model="type" :label="__('Type')">
                @foreach (\App\Models\Partner::TYPE_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ __($libelle) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="phone" :label="__('Téléphone')" type="tel" placeholder="+261 20 00 000 00" required />
            <flux:textarea wire:model="description" :label="__('Description courte')" rows="2" class="sm:col-span-2" />
            <flux:input wire:model="address" :label="__('Adresse')" required class="sm:col-span-2" />
            <flux:input wire:model="email" :label="__('E-mail (facultatif)')" type="email" />
            <flux:input wire:model="website" :label="__('Site web (facultatif)')" type="url" placeholder="https://" />
        </div>

        <fieldset class="space-y-2">
            <legend class="font-semibold text-ink">{{ __('Horaires (laisser vide = fermé)') }}</legend>
            <div class="divide-y divide-line rounded-md border border-line">
                @foreach (\App\Models\Partner::JOURS as $numero => $jour)
                    <div wire:key="jour-{{ $numero }}" class="grid gap-2 p-3 sm:grid-cols-[6rem_1fr_1fr] sm:items-start">
                        <span class="pt-2 text-sm font-medium capitalize text-ink">{{ $jour }}</span>
                        @foreach ([0, 1] as $i)
                            <div class="flex items-start gap-1">
                                <flux:input wire:model="hours.{{ $numero }}.{{ $i }}.start" type="time" size="sm" :aria-label="__(':jour, plage :n, ouverture', ['jour' => $jour, 'n' => $i + 1])" />
                                <span class="pt-1.5 text-ink-2" aria-hidden="true">–</span>
                                <flux:input wire:model="hours.{{ $numero }}.{{ $i }}.end" type="time" size="sm" :aria-label="__(':jour, plage :n, fermeture', ['jour' => $jour, 'n' => $i + 1])" />
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div class="space-y-2">
            <x-carte mode="choix" hauteur="16rem" :label="__('Choisir l\'emplacement du partenaire')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="latitude" :label="__('Latitude')" inputmode="decimal" required />
                <flux:input wire:model="longitude" :label="__('Longitude')" inputmode="decimal" required />
            </div>
        </div>

        <flux:checkbox wire:model="is_published" :label="__('Publié (visible sur la page publique)')" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </flux:button>
            <flux:button :href="route('agent.partners.index')" variant="ghost">{{ __('Annuler') }}</flux:button>
        </div>
    </form>
</section>
