<?php

use App\Models\Onboarding;
use App\Models\Service;
use App\Services\ParOuCommencer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Par où commencer ?')] class extends Component {
    /** @var array<int, string> Situations cochées (clés de ParOuCommencer::SITUATIONS). */
    public array $situations = [];

    /** Formulaire affiché : à la première visite, ou quand l'habitant modifie ses réponses. */
    public bool $modification = false;

    public function mount(): void
    {
        $this->authorize('parOuCommencer', Onboarding::class);

        $this->situations = $this->guide->situation() ?? [];
        $this->modification = ! $this->guide->aRepondu();
    }

    /**
     * Toujours la situation de l'utilisateur connecté : aucun identifiant ne vient du navigateur.
     */
    #[Computed]
    public function guide(): ParOuCommencer
    {
        return ParOuCommencer::pour(auth()->user());
    }

    /**
     * @return Collection<int, Service>
     */
    #[Computed]
    public function recommandations(): Collection
    {
        return $this->guide->recommandations();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'situations' => ['array', 'max:'.count(ParOuCommencer::SITUATIONS)],
            'situations.*' => ['string', Rule::in(array_keys(ParOuCommencer::SITUATIONS))],
        ];
    }

    public function enregistrer(): void
    {
        $this->authorize('parOuCommencer', Onboarding::class);
        $this->validate();

        $this->guide->enregistrer($this->situations);
        unset($this->guide, $this->recommandations);
        $this->modification = false;

        Flux::toast(variant: 'success', text: 'Vos réponses sont enregistrées : voici les services recommandés pour vous.');
    }

    public function modifier(): void
    {
        $this->authorize('parOuCommencer', Onboarding::class);

        $this->situations = $this->guide->situation() ?? [];
        $this->modification = true;
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Mon espace"
        title="Par où commencer ?"
        subtitle="Quelques questions simples pour vous indiquer les services utiles dans votre situation. Sans refaire votre inscription."
        :breadcrumb="['Mon espace' => route('dashboard'), 'Par où commencer ?' => null]"
    />

    @if ($modification)
        <x-tn.surface>
            <form wire:submit="enregistrer" class="space-y-6">
                <flux:checkbox.group wire:model="situations" label="Votre situation (cochez tout ce qui vous concerne)">
                    @foreach (ParOuCommencer::SITUATIONS as $cle => $situation)
                        <flux:checkbox :value="$cle" :label="$situation['label']" :description="$situation['aide']" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="situations" />
                <flux:error name="situations.*" />

                <p class="text-sm text-ink-2">Rien ne correspond ? Validez sans cocher : nous vous indiquerons les services utiles à tout nouvel habitant.</p>

                <div class="flex flex-wrap items-center gap-3">
                    <flux:button type="submit" variant="primary" icon="sparkles" wire:loading.attr="disabled" wire:target="enregistrer">
                        Voir mes services recommandés
                    </flux:button>
                    <span wire:loading wire:target="enregistrer" class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">Enregistrement…</span>
                </div>
            </form>
        </x-tn.surface>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-ink-2">
                @if ($this->guide->situation() === [])
                    Services utiles à tout nouvel habitant.
                @else
                    Selon votre situation :
                    @foreach ($this->guide->situation() as $cle)
                        <x-tn.status-badge etat="info" class="ms-1">{{ ParOuCommencer::SITUATIONS[$cle]['label'] }}</x-tn.status-badge>
                    @endforeach
                @endif
            </p>
            <flux:button size="sm" variant="outline" icon="pencil-square" wire:click="modifier">Modifier mes réponses</flux:button>
        </div>

        <x-onboarding.recommandations :services="$this->recommandations" :guide="$this->guide" />
    @endif
</section>
