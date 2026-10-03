<?php

use App\Models\Remontee;
use App\Notifications\Avis;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Espace agent — Remontée')] class extends Component {
    #[Locked]
    public Remontee $record;

    public string $reponse = '';

    public function mount(Remontee $remontee): void
    {
        Gate::authorize('viewAgentSpace');
        $this->authorize('traiter', $remontee);
        $this->record = $remontee->loadMissing(['user', 'agentPriseEnCharge', 'agentReponse']);
    }

    public function prendreEnCompte(): void
    {
        $this->authorize('traiter', $this->record);

        $this->appliquer(fn () => $this->record->prendreEnCompte(auth()->user()), 'Remontée prise en compte. L’habitant est prévenu.', [
            'Votre remontée '.$this->record->reference.' a été prise en compte par le service municipal.',
            'Un agent s’en occupe : vous recevrez sa réponse dans votre espace.',
        ]);
    }

    public function repondre(): void
    {
        $this->authorize('traiter', $this->record);

        $this->validate(
            ['reponse' => ['required', 'string', 'min:10', 'max:5000']],
            [
                'reponse.required' => 'Écrivez une réponse pour l’habitant.',
                'reponse.min' => 'La réponse doit faire au moins :min caractères.',
                'reponse.max' => 'La réponse ne doit pas dépasser :max caractères.',
            ],
        );

        $this->appliquer(fn () => $this->record->repondre(auth()->user(), $this->reponse), 'Réponse publiée. L’habitant est prévenu.', [
            'La mairie a répondu à votre remontée '.$this->record->reference.'.',
            'Consultez la réponse dans votre espace.',
        ]);

        $this->reset('reponse');
    }

    public function cloturer(): void
    {
        $this->authorize('traiter', $this->record);

        $this->appliquer(fn () => $this->record->cloturer(), 'Remontée clôturée.', [
            'Votre remontée '.$this->record->reference.' est clôturée.',
            'Si vous avez une nouvelle question, vous pouvez faire une nouvelle remontée.',
        ]);
    }

    /**
     * Transition validée par le modèle ; en cas de refus, message en français sans rien changer.
     *
     * @param  list<string>  $lignes
     */
    private function appliquer(Closure $transition, string $succes, array $lignes): void
    {
        try {
            $transition();
        } catch (DomainException $e) {
            $this->addError('transition', $e->getMessage());

            return;
        }

        $this->record->refresh()->load(['user', 'agentPriseEnCharge', 'agentReponse']);

        // Compte supprimé : la remontée est anonymisée, il n'y a plus personne à prévenir.
        $this->record->user?->notify(new Avis(
            'Remontée '.$this->record->reference.' : '.mb_strtolower(Remontee::libelleStatut($this->record->statut)),
            $lignes,
            'Voir ma remontée',
            route('concerns.show', $this->record),
        ));

        Flux::toast(variant: 'success', text: $succes);
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6">
    <x-tn.page-header
        label="Espace agent"
        :title="$record->objet"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Remontées sur les données' => route('agent.concerns.index'), $record->reference => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ Remontee::libelleStatut($record->statut) }}</x-tn.status-badge>
                <span class="font-mono text-xs">{{ $record->reference }}</span>
                <span>{{ Remontee::libelleCategorie($record->categorie) }}</span>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    @error('transition')
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
    @enderror

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">Habitant</x-tn.section-label>
                <dl>
                    @if ($record->user)
                        <x-tn.field label="Nom">{{ $record->user->name }}</x-tn.field>
                        <x-tn.field label="E-mail">{{ $record->user->email }}</x-tn.field>
                    @else
                        <x-tn.field label="Nom">Compte supprimé (remontée anonymisée)</x-tn.field>
                    @endif
                    <x-tn.field label="Envoyée le">{{ Remontee::dateLocale($record->envoyee_le) }}</x-tn.field>
                </dl>
            </x-tn.surface>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-2">Message</x-tn.section-label>
                <p class="whitespace-pre-line leading-relaxed text-ink" data-test="message">{{ $record->message }}</p>
            </x-tn.surface>

            @if ($record->reponse !== null)
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-2">Réponse envoyée</x-tn.section-label>
                    <p class="whitespace-pre-line leading-relaxed text-ink">{{ $record->reponse }}</p>
                    <p class="mt-3 font-mono text-xs text-ink-2">Par {{ $record->agentReponse?->name ?? 'un agent' }} le {{ Remontee::dateLocale($record->repondue_le) }}</p>
                </x-tn.surface>
            @endif

            @if (in_array($record->statut, Remontee::STATUTS_EN_ATTENTE, true))
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-3">Répondre à l’habitant</x-tn.section-label>
                    <form wire:submit="repondre" class="space-y-4">
                        <flux:textarea wire:model="reponse" label="Votre réponse" description="Phrases courtes, sans jargon. L’habitant la lira dans son espace et recevra un e-mail." rows="5" maxlength="5000" />
                        <flux:button type="submit" variant="primary" icon="paper-airplane">Publier la réponse</flux:button>
                    </form>
                </x-tn.surface>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <x-tn.panel label="Suivi">
                <x-tn.timeline :items="$record->frise()" />
                @if ($record->agentPriseEnCharge)
                    <p class="mt-4 text-sm text-ink-2">Prise en compte par {{ $record->agentPriseEnCharge->name }}.</p>
                @endif
            </x-tn.panel>

            <x-tn.surface>
                <x-tn.section-label as="h2" class="mb-3">Actions</x-tn.section-label>
                <div class="flex flex-col gap-2">
                    @if ($record->statut === 'recue')
                        <flux:button wire:click="prendreEnCompte" icon="hand-raised">Prendre en compte</flux:button>
                    @endif
                    @if ($record->statut === 'repondue')
                        <flux:button wire:click="cloturer" wire:confirm="Clôturer cette remontée ?" icon="lock-closed">Clôturer</flux:button>
                    @endif
                    @if ($record->statut === 'prise_en_compte')
                        <p class="text-sm text-ink-2">Répondez à l’habitant pour pouvoir clôturer.</p>
                    @endif
                    @if ($record->statut === 'cloturee')
                        <p class="text-sm text-ink-2">Remontée clôturée le {{ Remontee::dateLocale($record->cloturee_le) }}.</p>
                    @endif
                </div>
            </x-tn.surface>
        </div>
    </div>

    <x-audit-history :subject="$record" />
</section>
