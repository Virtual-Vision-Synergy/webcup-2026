<?php

use App\Models\AuditLog;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::agent'), Title('Compte citoyen')] class extends Component {
    #[Locked]
    public User $account;

    public function mount(User $user): void
    {
        $this->authorize('viewAccount', $user);
        $this->account = $user->loadMissing('role');
    }

    public function deactivate(): void
    {
        $this->authorize('deactivate', $this->account);

        $this->account->deactivate();

        Flux::modal('confirmer-desactivation')->close();
        Flux::toast(variant: 'success', text: 'Compte désactivé : '.$this->account->name.' ne peut plus se connecter.');
    }

    public function reactivate(): void
    {
        $this->authorize('reactivate', $this->account);

        $this->account->reactivate();

        Flux::toast(variant: 'success', text: 'Compte réactivé : '.$this->account->name.' peut de nouveau se connecter.');
    }

    /**
     * Historique des désactivations / réactivations de ce compte (qui, quand), lu dans le journal d'audit (F47).
     *
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function journal(): Collection
    {
        $this->authorize('viewAccount', $this->account);

        return AuditLog::query()
            ->where('subject_type', class_basename(User::class))
            ->where('subject_id', $this->account->id)
            ->whereIn('action', ['deactivated', 'reactivated'])
            ->latest('id')
            ->limit(20)
            ->get();
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Fiche du compte"
        :title="$account->name"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Comptes citoyens' => route('agent.citizens.index'), $account->name => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                @if ($account->isActive())
                    <x-tn.status-badge etat="normal">Actif</x-tn.status-badge>
                @else
                    <x-tn.status-badge etat="alerte">Désactivé</x-tn.status-badge>
                @endif
                <span>Inscrit le {{ $account->created_at?->format('d/m/Y') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('deactivate', $account)
                <flux:modal.trigger name="confirmer-desactivation">
                    <flux:button variant="danger" icon="no-symbol">Désactiver le compte</flux:button>
                </flux:modal.trigger>
            @endcan
            @can('reactivate', $account)
                <flux:button variant="primary" icon="arrow-path" wire:click="reactivate" wire:loading.attr="disabled">Réactiver le compte</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-2">Informations du compte</x-tn.section-label>
        <dl>
            <x-tn.field label="Nom">{{ $account->name }}</x-tn.field>
            <x-tn.field label="E-mail">{{ $account->email }}</x-tn.field>
            <x-tn.field label="Profil">{{ $account->role?->label ?? '—' }}</x-tn.field>
            <x-tn.field label="Inscription">{{ $account->created_at?->format('d/m/Y à H:i') }}</x-tn.field>
            <x-tn.field label="Statut">
                @if ($account->isActive())
                    Actif : la personne peut se connecter à son espace.
                @else
                    Désactivé le {{ $account->deactivated_at->format('d/m/Y à H:i') }} : la connexion est bloquée.
                @endif
            </x-tn.field>
        </dl>
    </x-tn.surface>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">Journal des actions</x-tn.section-label>
        @if ($this->journal->isEmpty())
            <flux:text>Aucune désactivation ni réactivation pour ce compte.</flux:text>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->journal as $entree)
                    <li wire:key="journal-{{ $entree->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <span>
                            <x-tn.status-badge :etat="$entree->etatAction()">{{ $entree->libelleAction() }}</x-tn.status-badge>
                            <span class="ms-2 text-ink-2">par {{ $entree->actor_name }}</span>
                        </span>
                        <span class="font-mono text-xs text-ink-2">{{ $entree->dateLocale('d/m/Y à H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-tn.surface>

    @can('deactivate', $account)
        <flux:modal name="confirmer-desactivation" class="max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Désactiver le compte de {{ $account->name }} ?</flux:heading>
                    <flux:text class="mt-2">Ce citoyen ne pourra plus se connecter. Confirmer ? Vous pourrez réactiver le compte à tout moment.</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Annuler</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Confirmer la désactivation</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</section>
