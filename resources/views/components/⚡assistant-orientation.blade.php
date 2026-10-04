<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\ConversationAssistant;
use App\Models\Service;
use App\Services\AssistantOrientation;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * F91 : bulle « Besoin d'aide ? » présente sur toutes les pages de l'espace connecté.
 * Assistant automatisé sans IA (App\Services\AssistantOrientation) : chaque réponse finit par une action concrète.
 */
new class extends Component {
    use ThrottlesPerUser;

    /** Nombre d'échanges gardés à l'écran : une conversation courte. */
    private const ECHANGES_MAX = 6;

    public string $message = '';

    /**
     * Fil de la conversation, construit uniquement par le serveur (verrouillé : le navigateur ne peut pas injecter de lien).
     *
     * @var list<array{question: string, texte: string, actions: list<array{libelle: string, url: string, icone: string}>, choix: list<array{id: int, nom: string}>}>
     */
    #[Locked]
    public array $echanges = [];

    /** Identifiant aléatoire de la conversation (anonyme : non relié au compte). */
    #[Locked]
    public string $conversation = '';

    public function mount(): void
    {
        $this->conversation = (string) Str::uuid();
    }

    public function envoyer(): void
    {
        $this->authorize('create', ConversationAssistant::class);

        $this->validate(
            ['message' => ['required', 'string', 'max:300']],
            ['message.required' => __('Écrivez votre question en quelques mots.')],
        );

        $this->throttlePerUser('assistant', maxAttempts: 10, decaySeconds: 60);

        $assistant = app(AssistantOrientation::class);
        $message = trim($this->message);
        $reponse = $assistant->repondre($message);
        $assistant->journaliser($this->conversation, $message, $reponse);

        $this->ajouter($message, $reponse);
        $this->reset('message');
    }

    /**
     * Réponse à une question de précision : l'habitant choisit l'un des services proposés.
     */
    public function choisir(int $serviceId): void
    {
        $this->authorize('create', ConversationAssistant::class);

        $service = Service::query()->findOrFail($serviceId);
        $this->authorize('view', $service);

        $this->throttlePerUser('assistant', maxAttempts: 10, decaySeconds: 60);

        $assistant = app(AssistantOrientation::class);
        $reponse = $assistant->pourService($service);
        $assistant->journaliser($this->conversation, 'Choix : '.$service->nom, $reponse);

        $this->ajouter($service->nom, $reponse);
    }

    public function recommencer(): void
    {
        $this->authorize('create', ConversationAssistant::class);

        $this->echanges = [];
        $this->conversation = (string) Str::uuid();
        $this->resetValidation();
    }

    /**
     * @param  array{texte: string, actions: list<array{libelle: string, url: string, icone: string}>, choix: list<array{id: int, nom: string}>}  $reponse
     */
    private function ajouter(string $question, array $reponse): void
    {
        $this->echanges[] = ['question' => $question, 'texte' => $reponse['texte'], 'actions' => $reponse['actions'], 'choix' => $reponse['choix']];
        $this->echanges = array_slice($this->echanges, -self::ECHANGES_MAX);

        $this->dispatch('assistant-repondu');
    }
}; ?>

<div
    x-data="{ ouvert: false }"
    x-on:keydown.escape.window="if (ouvert) { ouvert = false; $refs.bouton.focus() }"
    x-on:assistant-repondu.window="$nextTick(() => { $refs.fil.scrollTop = $refs.fil.scrollHeight })"
    class="print:hidden"
>
    <button
        type="button"
        x-ref="bouton"
        x-on:click="ouvert = ! ouvert; ouvert && $nextTick(() => $refs.champ.focus())"
        x-bind:aria-expanded="ouvert.toString()"
        aria-controls="assistant-panneau"
        class="fixed end-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-40 flex items-center gap-2 rounded-full bg-cyan px-4 py-3 text-sm font-semibold text-on-cyan shadow-lg focus:outline-none focus-visible:ring-4 focus-visible:ring-cyan/40 lg:bottom-6"
        data-test="assistant-bulle"
    >
        <flux:icon.chat-bubble-oval-left-ellipsis class="size-5" />
        <span>{{ __('Besoin d’aide ?') }}</span>
    </button>

    <section
        id="assistant-panneau"
        x-show="ouvert"
        x-cloak
        x-transition.opacity
        role="dialog"
        aria-labelledby="assistant-titre"
        class="fixed inset-x-2 bottom-[calc(9rem+env(safe-area-inset-bottom))] z-40 flex max-h-[70vh] flex-col overflow-hidden rounded-md border border-line bg-surface text-ink shadow-2xl sm:inset-x-auto sm:end-4 sm:w-96 lg:bottom-20"
    >
        <header class="flex items-start justify-between gap-2 border-b border-line px-4 py-3">
            <div>
                <h2 id="assistant-titre" class="font-semibold">{{ __('Besoin d’aide ?') }}</h2>
                <p class="text-xs text-ink-2">
                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Assistant automatisé') }}</flux:badge>
                    {{ __('Réponses issues des services de la mairie.') }}
                </p>
            </div>
            <div class="flex items-center">
                @if ($echanges !== [])
                    <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="recommencer" :tooltip="__('Recommencer')" :aria-label="__('Recommencer la conversation')" />
                @endif
                <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="ouvert = false; $refs.bouton.focus()" :aria-label="__('Fermer l’assistant')" />
            </div>
        </header>

        <div x-ref="fil" class="flex-1 space-y-3 overflow-y-auto px-4 py-3 text-sm" aria-live="polite">
            <p class="rounded-md bg-surface-2 px-3 py-2">
                {{ __('Bonjour ! Dites-moi ce que vous cherchez, avec vos mots : « papier de naissance », « ma poubelle n’est pas ramassée »…') }}
            </p>

            @foreach ($echanges as $index => $echange)
                <div wire:key="echange-{{ $conversation }}-{{ $index }}" class="space-y-2">
                    <p class="ms-auto w-fit max-w-[85%] rounded-md bg-cyan px-3 py-2 text-on-cyan">{{ $echange['question'] }}</p>

                    <div class="max-w-[95%] space-y-2 rounded-md bg-surface-2 px-3 py-2" data-test="assistant-reponse">
                        <p>{{ $echange['texte'] }}</p>

                        @if ($echange['choix'] !== [])
                            <div class="flex flex-wrap gap-2">
                                @foreach ($echange['choix'] as $choix)
                                    <flux:button size="sm" wire:click="choisir({{ $choix['id'] }})" wire:loading.attr="disabled">{{ $choix['nom'] }}</flux:button>
                                @endforeach
                            </div>
                        @endif

                        <ul class="space-y-1">
                            @foreach ($echange['actions'] as $action)
                                <li>
                                    <a href="{{ $action['url'] }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-cyan underline-offset-2 hover:underline">
                                        <flux:icon :icon="$action['icone']" variant="micro" />
                                        {{ $action['libelle'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach

            <p wire:loading.flex wire:target="envoyer, choisir" class="items-center gap-2 text-ink-2">
                <flux:icon.loading class="size-4" /> {{ __('L’assistant cherche…') }}
            </p>
        </div>

        <form wire:submit="envoyer" class="space-y-1 border-t border-line px-4 py-3">
            <div class="flex gap-2">
                <flux:input
                    x-ref="champ"
                    wire:model="message"
                    maxlength="300"
                    autocomplete="off"
                    :placeholder="__('Votre question…')"
                    :aria-label="__('Votre question pour l’assistant')"
                    class="flex-1"
                />
                <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="envoyer" :aria-label="__('Envoyer')" />
            </div>
            @error('message') <p class="text-xs text-magenta">{{ $message }}</p> @enderror
            @error('throttle') <p class="text-xs text-magenta">{{ $message }}</p> @enderror
        </form>
    </section>
</div>
