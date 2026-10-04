<?php

use App\Concerns\EmpecheEnvoiEnDouble;
use App\Concerns\ProtegeContreRobots;
use App\Models\Message;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Contacter la mairie')] class extends Component {
    use EmpecheEnvoiEnDouble, ProtegeContreRobots;

    #[Locked]
    public ?Message $record = null;

    public string $nom = '';
    public string $email = '';
    public string $sujet = '';
    public string $message = '';

    /** Message envoyé : affiche l'écran de confirmation. */
    #[Locked]
    public ?int $envoyeId = null;

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
            // F92 : sujet et message pré-remplis depuis l'orientation « Je ne sais pas à qui m'adresser ».
            $this->sujet = Str::limit(trim(request()->string('sujet')->toString()), 255, '');
            $this->message = Str::limit(trim(request()->string('message')->toString()), 5000, '');
            $this->initialiserAntiRobot('contact');
            $this->initialiserJetonEnvoi();
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
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nom' => 'nom',
            'email' => __('adresse e-mail'),
            'sujet' => 'sujet',
            'message' => 'message',
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

            Flux::toast(variant: 'success', text: __('Message modifié.'));

            $this->redirectRoute('messages.show', $this->record, navigate: true);

            return;
        }

        // F81 : champ piège, délai minimal, 5 envois par minute par compte et 15 par IP (journalisés si bloqués).
        $this->verifierAntiRobot(
            'contact',
            parCompte: (int) config('security.formulaires.limites.contact.compte'),
            parIp: (int) config('security.formulaires.limites.contact.ip'),
        );

        // F82 : même message renvoyé (double clic, retour arrière) → rien n'est créé, message avec lien.
        $record = $this->envoyerUneSeuleFois(
            'contact',
            ['sujet' => $validated['sujet'], 'message' => $validated['message']],
            function () use ($validated): Message {
                $record = new Message($validated);
                $record->user()->associate(auth()->user());
                $record->save();

                return $record;
            },
            fn (Message $message): string => route('messages.show', $message),
        );

        if ($record === null) {
            return;
        }

        $this->envoyeId = $record->id;
        $this->reset('sujet', 'message');

        Flux::toast(variant: 'success', text: __('Votre message a bien été envoyé à la mairie.'));
    }

    public function nouveau(): void
    {
        $this->authorize('create', Message::class);

        $this->envoyeId = null;
    }
}; ?>

<section class="mx-auto w-full max-w-2xl space-y-6">
    <x-tn.page-header
        label="{{ __('Contact') }}"
        :title="$record ? __('Modifier le message') : __('Contacter la mairie')"
        :subtitle="$record ? null : __('Le Service des Relations Citoyennes vous répondra dans les meilleurs délais.')"
        :breadcrumb="$record
            ? ['Mon espace' => route('dashboard'), 'Messages' => route('messages.index'), ($record->sujet ?: 'Message') => route('messages.show', $record), 'Modifier' => null]
            : ['Mon espace' => route('dashboard'), 'Messages' => route('messages.index'), 'Nouveau' => null]"
    />

    @if ($envoyeId)
        <div role="status" class="space-y-4 rounded-md border border-line bg-surface p-5 md:p-6">
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.heading>{{ __('Message envoyé à la mairie') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Merci ! Votre message a bien été transmis au Service des Relations Citoyennes. Un agent le traitera prochainement.') }}</flux:callout.text>
            </flux:callout>

            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="primary" :href="route('messages.show', $envoyeId)" wire:navigate>{{ __('Voir mon message') }}</flux:button>
                <flux:button variant="ghost" wire:click="nouveau">{{ __('Écrire un autre message') }}</flux:button>
            </div>
        </div>
    @else
        <form wire:submit="save" @if (! $record) data-brouillon="contact" data-brouillon-libelle="{{ __('Message à la mairie') }}" @endif class="relative space-y-6 rounded-md border border-line bg-surface p-5 md:p-6" novalidate>
            <x-tn.mention-obligatoire />

            @unless ($record)
                <x-anti-robot-livewire />
            @endunless

            <flux:input wire:model="nom" label="{{ __('Nom') }}" required />

            <flux:input wire:model="email" type="email" label="{{ __('Adresse e-mail') }}" required />

            <flux:input wire:model="sujet" label="{{ __('Sujet') }}" required />

            <flux:textarea wire:model="message" label="{{ __('Message') }}" rows="5" required />

            <flux:error name="throttle" />

            <x-envoi-deja-fait :le="$envoiDejaFaitLe" :url="$envoiDejaFaitUrl" message="Ce message a déjà été envoyé" lien="voir mon message" />

            <div class="flex items-center gap-3">
                <x-submit-button variant="primary">{{ $record ? __('Enregistrer') : __('Envoyer à la mairie') }}</x-submit-button>
                <flux:button :href="route('messages.index')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
            </div>
        </form>
    @endif
</section>
