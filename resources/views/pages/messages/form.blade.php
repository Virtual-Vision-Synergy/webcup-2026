<?php

use App\Models\Message;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Message')] class extends Component {
    #[Locked]
    public ?Message $record = null;

    public string $nom = '';
    public string $email = '';
    public string $sujet = '';
    public string $message = '';

    public bool $envoye = false;

    public function mount(?Message $message = null): void
    {
        if ($message?->exists) {
            $this->authorize('update', $message);
            $this->record = $message;
            $this->nom = (string) ($message->nom ?? '');
            $this->email = (string) ($message->email ?? '');
            $this->sujet = (string) ($message->sujet ?? '');
            $this->message = (string) ($message->message ?? '');
        } else {
            $this->authorize('create', Message::class);
            $this->nom = (string) auth()->user()->name;
            $this->email = (string) auth()->user()->email;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'sujet' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nom.required' => 'Indiquez votre nom.',
            'email.required' => 'Indiquez votre adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n\'est pas valide.',
            'sujet.required' => 'Indiquez le sujet de votre message.',
            'message.required' => 'Écrivez votre message.',
            'message.min' => 'Votre message est trop court (10 caractères minimum).',
            '*.max' => 'Ce champ est trop long (:max caractères maximum).',
        ];
    }

    public function save(): void
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Message::class);

        $validated = $this->validate();

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Message($validated);
            $record->user()->associate(auth()->user());
            $record->save();
        }

        if ($this->record) {
            Flux::toast(variant: 'success', text: 'Message modifié.');
            $this->redirectRoute('messages.show', $record, navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: 'Votre message a bien été envoyé à la mairie.');

        $this->reset('sujet', 'message');
        $this->envoye = true;
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="Contact"
        :title="$record ? 'Modifier le message' : 'Contacter la mairie'"
        :breadcrumb="['Messages' => route('messages.index'), ($record ? 'Modifier' : 'Nouveau') => null]"
    />

    @if ($envoye)
        <div role="status" class="rounded-md border border-line bg-surface p-5 md:p-6">
            <flux:heading size="lg">Message envoyé</flux:heading>
            <flux:text class="mt-1">Merci, votre message a bien été transmis à la mairie. Un agent vous répondra à l'adresse indiquée.</flux:text>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <flux:button :href="route('messages.index')" wire:navigate variant="primary">Voir mes messages</flux:button>
                <flux:button wire:click="$set('envoye', false)" variant="ghost">Écrire un autre message</flux:button>
            </div>
        </div>
    @else
    <form wire:submit="save" class="space-y-6 rounded-md border border-line bg-surface p-5 md:p-6" novalidate>
        <flux:input wire:model="nom" label="Nom" required />

        <flux:input wire:model="email" type="email" label="E-mail" required />

        <flux:input wire:model="sujet" label="Sujet" required />

        <flux:textarea wire:model="message" label="Message" rows="5" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" icon="paper-airplane">
                <span wire:loading.remove wire:target="save">{{ $record ? 'Enregistrer' : 'Envoyer' }}</span>
                <span wire:loading wire:target="save">Envoi…</span>
            </flux:button>
            <flux:button :href="route('messages.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
    @endif
</section>
