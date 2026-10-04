<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\Idea;
use App\Notifications\Avis;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Proposer une idée')] class extends Component {
    use ThrottlesPerUser;

    /** Au plus 5 idées par heure et par habitant (contre l'abus). */
    public const MAX_PAR_HEURE = 5;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public function mount(): void
    {
        $this->authorize('create', Idea::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:20', 'max:3000'],
            'category' => ['required', Rule::in(Idea::CATEGORY_OPTIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre à votre idée.',
            'title.min' => 'Le titre doit faire au moins :min caractères.',
            'title.max' => 'Le titre ne doit pas dépasser :max caractères.',
            'description.required' => 'Décrivez votre idée.',
            'description.min' => 'La description doit faire au moins :min caractères.',
            'description.max' => 'La description ne doit pas dépasser :max caractères.',
            'category.required' => 'Choisissez une catégorie.',
            'category.in' => 'Choisissez une catégorie dans la liste.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Idea::class);

        /** @var array{title: string, description: string, category: string} $validated */
        $validated = $this->validate();

        $this->throttlePerUser('idee', self::MAX_PAR_HEURE, 3600);

        $idea = Idea::proposer(auth()->user(), $validated);

        // Accusé de réception : « Avis » dans l'application + e-mail, avec le numéro de suivi.
        auth()->user()->notify(new Avis(
            'Idée '.$idea->reference.' bien reçue',
            [
                'Merci ! Votre idée « '.$idea->title.' » a bien été reçue le '.Idea::dateLocale($idea->created_at).'.',
                'Votre numéro de suivi : '.$idea->reference.'.',
                'Un agent de la ville l’étudiera. Vous recevrez sa réponse ici et dans vos avis.',
            ],
            'Suivre mon idée',
            route('ideas.show', $idea),
        ));

        $this->redirectRoute('ideas.received', $idea, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Boîte à idées"
        title="Proposer une idée"
        subtitle="Une idée pour améliorer la colonie ? Partagez-la. Vous recevrez un numéro de suivi et la réponse de la ville."
        :breadcrumb="['Boîte à idées' => route('ideas.index'), 'Proposer une idée' => null]"
    />

    <x-tn.surface>
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="title" label="Titre" placeholder="Ex. : Des bancs ombragés près du marché" maxlength="150" required />

            <flux:textarea
                wire:model="description"
                label="Description"
                description="Décrivez votre idée et ce qu’elle améliorerait."
                rows="6"
                maxlength="3000"
                required
            />

            <flux:select wire:model="category" label="Catégorie" placeholder="Choisissez une catégorie" required>
                @foreach (Idea::CATEGORY_LABELS as $valeur => $libelle)
                    <flux:select.option :value="$valeur">{{ $libelle }}</flux:select.option>
                @endforeach
            </flux:select>

            @error('throttle')
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
            @enderror

            <div class="flex flex-wrap items-center gap-3">
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="save">Envoyer mon idée</span>
                    <span wire:loading wire:target="save">Envoi…</span>
                </flux:button>
                <flux:button :href="route('ideas.index')" wire:navigate variant="ghost">Annuler</flux:button>
            </div>
        </form>
    </x-tn.surface>

    <p class="text-sm text-ink-2">
        Votre idée est publiée tout de suite sur la page des idées, sans votre nom. Les autres habitants pourront la soutenir.
    </p>
</section>
