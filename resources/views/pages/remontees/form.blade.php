<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Remontee;
use App\Notifications\Avis;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Faire remonter une inquiétude')] class extends Component {
    use ThrottlesPerUser;

    /** Au plus 5 remontées par heure et par habitant (contre l'abus). */
    public const MAX_PAR_HEURE = 5;

    public string $categorie = 'comprendre';

    public string $objet = '';

    public string $message = '';

    public function mount(): void
    {
        $this->authorize('create', Remontee::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'categorie' => ['required', Rule::in(Remontee::CATEGORIE_OPTIONS)],
            'objet' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'categorie.required' => 'Choisissez le sujet de votre remontée.',
            'categorie.in' => 'Choisissez un sujet dans la liste.',
            'objet.required' => 'Indiquez l’objet de votre remontée en quelques mots.',
            'objet.min' => 'L’objet doit faire au moins :min caractères.',
            'objet.max' => 'L’objet ne doit pas dépasser :max caractères.',
            'message.required' => 'Expliquez ce qui vous inquiète.',
            'message.min' => 'Votre message doit faire au moins :min caractères.',
            'message.max' => 'Votre message ne doit pas dépasser :max caractères.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Remontee::class);

        /** @var array{categorie: string, objet: string, message: string} $validated */
        $validated = $this->validate();

        $this->throttlePerUser('remontee-donnees', self::MAX_PAR_HEURE, 3600);

        $remontee = Remontee::envoyer(auth()->user(), $validated);

        // Accusé de réception : « Avis » dans l'application + e-mail, avec le numéro de suivi.
        auth()->user()->notify(new Avis(
            'Remontée '.$remontee->reference.' bien reçue',
            [
                'Nous avons bien reçu votre remontée « '.$remontee->objet.' » le '.Remontee::dateLocale($remontee->envoyee_le).'.',
                'Votre numéro de suivi : '.$remontee->reference.'.',
                'Un agent municipal va la prendre en compte. Vous serez prévenu à chaque étape.',
            ],
            'Suivre ma remontée',
            route('concerns.show', $remontee),
        ));

        $this->redirectRoute('concerns.received', $remontee, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Vos données"
        title="Faire remonter une inquiétude"
        subtitle="Une question ou un doute sur l’usage de vos données ? Écrivez-nous. Vous recevrez un numéro de suivi."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Mes remontées' => route('concerns.index'), 'Nouvelle remontée' => null]"
    />

    <x-tn.surface>
        <form wire:submit="save" class="space-y-6">
            <x-tn.mention-obligatoire />

            <flux:select wire:model="categorie" label="Sujet" required>
                @foreach (Remontee::CATEGORIE_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="objet" label="Objet" placeholder="Ex. : Qui peut voir mon numéro de téléphone ?" maxlength="150" required />

            <flux:textarea
                wire:model="message"
                label="Votre message"
                description="Expliquez ce qui vous inquiète, avec vos mots. Inutile d’indiquer un mot de passe ou un numéro de pièce d’identité."
                rows="6"
                maxlength="5000"
                required
            />

            @error('throttle')
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" role="alert" />
            @enderror

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="save">Envoyer ma remontée</span>
                    <span wire:loading wire:target="save">Envoi…</span>
                </flux:button>
                <flux:button :href="route('concerns.index')" wire:navigate variant="ghost">Annuler</flux:button>
            </div>
        </form>
    </x-tn.surface>

    <p class="text-sm text-ink-2">
        Seuls vous et les agents municipaux chargés des données pouvez lire cette remontée.
        <a href="{{ route('privacy.show') }}" wire:navigate class="text-cyan underline">Comment vos données sont utilisées</a>
    </p>
</section>
