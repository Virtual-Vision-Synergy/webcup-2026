<?php

use App\Models\Message;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'sujet' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
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

        Flux::toast(variant: 'success', text: 'Message enregistré(e).');

        $this->redirectRoute('messages.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:link :href="route('messages.index')" wire:navigate class="text-sm">&larr; Messages</flux:link>
        <flux:heading size="xl" level="1" class="mt-2">
            {{ $record ? 'Modifier' : 'Ajouter' }} : Message
        </flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="nom" label="Nom" required />

        <flux:input wire:model="email" label="Email" required />

        <flux:input wire:model="sujet" label="Sujet" required />

        <flux:textarea wire:model="message" label="Message" rows="5" required />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('messages.index')" wire:navigate variant="ghost">Annuler</flux:button>
        </div>
    </form>
</section>
