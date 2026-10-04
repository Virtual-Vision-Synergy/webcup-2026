<?php

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\User;
use App\Services\ComptesHabitants;
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

    /** @var list<array{nom: string, identifiant: string, telephone: string|null, code: string}> F71 : fiche affichée une seule fois après émission. */
    #[Locked]
    public array $fiches = [];

    /** @var list<string> F70 : services couverts par l'agent (cases cochées par l'admin). */
    public array $servicesCouverts = [];

    public function mount(User $user): void
    {
        $this->authorize('viewAccount', $user);
        $this->account = $user->loadMissing('role');

        if (auth()->user()->can('assignServices', $this->account)) {
            $this->servicesCouverts = array_map('strval', $this->account->serviceIds());
        }
    }

    /**
     * F70 : services de la ville (affectation des agents, réservée à l'admin).
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        $this->authorize('assignServices', $this->account);

        return Service::query()->orderBy('nom')->get(['id', 'nom']);
    }

    /**
     * F70 : rattache l'agent aux services cochés (admin uniquement, journalisé dans F47).
     */
    public function enregistrerServices(): void
    {
        $this->authorize('assignServices', $this->account);

        $this->validate([
            'servicesCouverts' => ['array'],
            'servicesCouverts.*' => ['integer', 'exists:services,id'],
        ]);

        $this->account->affecterServices(array_map('intval', $this->servicesCouverts));

        Flux::toast(variant: 'success', text: __('Services de l’agent mis à jour.'));
    }

    public function deactivate(): void
    {
        $this->authorize('deactivate', $this->account);

        $this->account->deactivate();

        Flux::modal('confirmer-desactivation')->close();
        Flux::toast(variant: 'success', text: __('Compte désactivé : :nom ne peut plus se connecter.', ['nom' => $this->account->name]));
    }

    public function reactivate(): void
    {
        $this->authorize('reactivate', $this->account);

        $this->account->reactivate();

        Flux::toast(variant: 'success', text: __('Compte réactivé : :nom peut de nouveau se connecter.', ['nom' => $this->account->name]));
    }

    /**
     * F71 : nouveau code d'activation à usage unique (l'ancien code et le code personnel actuel cessent de servir
     * une fois le nouveau code utilisé). Utile si l'habitant a perdu sa fiche ou oublié son code personnel.
     */
    public function issueActivationCode(ComptesHabitants $comptes): void
    {
        $this->authorize('issueActivationCode', $this->account);

        $code = $comptes->nouveauCode($this->account);

        $this->fiches = [['nom' => $this->account->name, 'identifiant' => (string) $this->account->identifiant, 'telephone' => $this->account->telephone, 'code' => $code]];

        Flux::toast(variant: 'success', text: __('Nouveau code d\'activation émis : imprimez la fiche.'));
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
        label="{{ __('Fiche du compte') }}"
        :title="$account->name"
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Comptes citoyens' => route('agent.citizens.index'), $account->name => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                @if ($account->isActive())
                    <x-tn.status-badge etat="normal">{{ __('Actif') }}</x-tn.status-badge>
                @else
                    <x-tn.status-badge etat="alerte">{{ __('Désactivé') }}</x-tn.status-badge>
                @endif
                <span>Inscrit le {{ $account->created_at?->format('d/m/Y') }}</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('deactivate', $account)
                <flux:modal.trigger name="confirmer-desactivation">
                    <flux:button variant="danger" icon="no-symbol">{{ __('Désactiver le compte') }}</flux:button>
                </flux:modal.trigger>
            @endcan
            @can('issueActivationCode', $account)
                <flux:button icon="ticket" wire:click="issueActivationCode" wire:loading.attr="disabled" wire:confirm="{{ __('Émettre un nouveau code d\'activation ? L\'ancien ne fonctionnera plus.') }}">{{ __('Nouveau code d\'activation') }}</flux:button>
            @endcan
            @can('reactivate', $account)
                <flux:button variant="primary" icon="arrow-path" wire:click="reactivate" wire:loading.attr="disabled">{{ __('Réactiver le compte') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-tn.page-header>

    @if ($fiches !== [])
        <x-habitants.fiches-activation :fiches="$fiches" />
    @endif

    <x-audit-history :subject="$account" variant="resume" />

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-2">{{ __('Informations du compte') }}</x-tn.section-label>
        <dl>
            <x-tn.field label="{{ __('Nom') }}">{{ $account->name }}</x-tn.field>
            {{-- F70 : coordonnées masquées par défaut (motif + journal pour les afficher). --}}
            <x-tn.field label="{{ __('E-mail') }}">
                @if ($account->aUnEmail())
                    <livewire:donnee-confidentielle :subject="$account" champ="email" wire:key="confidentiel-email" />
                @else
                    {{ __('Aucune (compte sans e-mail)') }}
                @endif
            </x-tn.field>
            @if ($account->identifiant)
                <x-tn.field label="{{ __('Identifiant d\'habitant') }}"><span class="font-mono">{{ $account->identifiant }}</span>@if ($account->aActiverCompte()) <flux:badge size="sm" color="amber" class="ms-1">{{ __('À activer') }}</flux:badge>@endif</x-tn.field>
            @endif
            <x-tn.field label="{{ __('Téléphone') }}">
                @if ($account->telephone)
                    <livewire:donnee-confidentielle :subject="$account" champ="telephone" wire:key="confidentiel-telephone" />
                @else
                    —
                @endif
            </x-tn.field>
            <x-tn.field label="{{ __('Profil') }}">{{ $account->role?->label ?? '—' }}</x-tn.field>
            <x-tn.field label="{{ __('Inscription') }}">{{ $account->created_at?->format('d/m/Y à H:i') }}</x-tn.field>
            <x-tn.field label="{{ __('Statut') }}">
                @if ($account->isActive())
                    {{ __('Actif : la personne peut se connecter à son espace.') }}
                @else
                    Désactivé le {{ $account->deactivated_at->format('d/m/Y à H:i') }} : la connexion est bloquée.
                @endif
            </x-tn.field>
        </dl>
    </x-tn.surface>

    @can('assignServices', $account)
        <x-tn.surface data-test="services-agent">
            <x-tn.section-label as="h2" class="mb-1">{{ __('Services couverts') }}</x-tn.section-label>
            <flux:text class="mb-4">{{ __('L’agent n’accède qu’aux demandes et rendez-vous de ces services. Chaque changement est inscrit au journal.') }}</flux:text>
            <form wire:submit="enregistrerServices" class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($this->services as $service)
                        <flux:checkbox wire:model="servicesCouverts" :value="(string) $service->id" :label="$service->nom" wire:key="service-{{ $service->id }}" />
                    @endforeach
                </div>
                <flux:error name="servicesCouverts.*" />
                <flux:button type="submit" variant="primary" icon="check">
                    <span wire:loading.remove wire:target="enregistrerServices">{{ __('Enregistrer les services') }}</span>
                    <span wire:loading wire:target="enregistrerServices">{{ __('Enregistrement…') }}</span>
                </flux:button>
            </form>
        </x-tn.surface>
    @endcan

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">{{ __('Journal des actions') }}</x-tn.section-label>
        @if ($this->journal->isEmpty())
            <flux:text>{{ __('Aucune désactivation ni réactivation pour ce compte.') }}</flux:text>
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
                    <flux:text class="mt-2">{{ __('Ce citoyen ne pourra plus se connecter. Confirmer ? Vous pourrez réactiver le compte à tout moment.') }}</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">{{ __('Confirmer la désactivation') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan

    <x-audit-history :subject="$account" />
</section>
