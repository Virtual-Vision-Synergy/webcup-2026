<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\AuditLog;
use App\Models\Demarche;
use App\Models\ReponseDemarche;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
| F84 : fil de messages d'une démarche, sur sa fiche.
| Lecture : DemarchePolicy::view (auteur, ou agent du service, ou admin).
| Écriture : DemarchePolicy::repondre ; auteur, demarche_id et de_agent assignés par Demarche::ajouterReponse.
| Les agents disposent de réponses types (ReponseDemarche::MODELES) modifiables avant envoi.
|
| Utilisation : <livewire:reponses-demarche :demarche="$demarche" />
*/
new class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public Demarche $demarche;

    public string $message = '';

    public string $modele = '';

    public function mount(Demarche $demarche): void
    {
        $this->authorize('view', $demarche);
        $this->demarche = $demarche;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:'.ReponseDemarche::MESSAGE_MAX],
            'modele' => ['nullable', Rule::in(array_keys(ReponseDemarche::MODELES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['message' => 'message', 'modele' => 'réponse type'];
    }

    /**
     * Vrai si l'utilisateur répond au nom de la mairie (et non en tant qu'auteur de la démarche).
     */
    #[Computed]
    public function estAgent(): bool
    {
        $user = auth()->user();

        return $this->demarche->user_id !== $user->id && ($user->isAgent() || $user->isAdmin());
    }

    /**
     * @return Collection<int, ReponseDemarche>
     */
    #[Computed]
    public function reponses(): Collection
    {
        $this->authorize('view', $this->demarche);

        return $this->demarche->reponses()->with('user:id,name')->get();
    }

    /**
     * Insère une réponse type dans le champ (l'agent peut la modifier avant d'envoyer).
     */
    public function updatedModele(string $cle): void
    {
        $this->authorize('repondre', $this->demarche);

        if ($this->estAgent && isset(ReponseDemarche::MODELES[$cle])) {
            $this->message = ReponseDemarche::MODELES[$cle]['contenu'];
        }
    }

    public function envoyer(): void
    {
        $this->authorize('repondre', $this->demarche);
        $this->validate();
        $this->throttlePerUser('reponse-demarche', maxAttempts: 10, decaySeconds: 60);

        $this->demarche->ajouterReponse(auth()->user(), trim($this->message));

        $this->reset('message', 'modele');
        unset($this->reponses);

        Flux::toast(variant: 'success', text: $this->estAgent ? 'Réponse envoyée à l’habitant.' : 'Message envoyé à la mairie.');
    }

    /**
     * Rafraîchissement léger (wire:poll) : une nouvelle réponse apparaît sans recharger la page.
     */
    public function rafraichir(): void
    {
        $this->authorize('view', $this->demarche);
        unset($this->reponses);
    }
}; ?>

<x-tn.surface>
    <div wire:poll.{{ \App\Support\ModeDegrade::poll(30) }}.visible="rafraichir">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <x-tn.section-label as="h2">Échanges avec la mairie</x-tn.section-label>
            @php($derniere = $this->reponses->last())
            @if ($derniere?->de_agent)
                <x-tn.status-badge etat="normal">Réponse envoyée</x-tn.status-badge>
            @else
                <x-tn.status-badge etat="perturbe">En attente de réponse</x-tn.status-badge>
            @endif
        </div>

        @if ($this->reponses->isEmpty())
            <flux:text>
                {{ $this->estAgent ? 'Aucune réponse pour l’instant. Écrivez à l’habitant ci-dessous.' : 'Aucun message pour l’instant. La réponse de la mairie apparaîtra ici.' }}
            </flux:text>
        @else
            <ol class="space-y-3" aria-label="Fil des messages">
                @foreach ($this->reponses as $reponse)
                    <li wire:key="reponse-{{ $reponse->id }}" @class([
                        'rounded-md border p-3',
                        'border-cyan/40 bg-cyan/5' => $reponse->de_agent,
                        'border-line' => ! $reponse->de_agent,
                    ])>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="inline-flex items-center gap-1 font-medium text-ink">
                                <flux:icon :name="$reponse->de_agent ? 'building-library' : 'user'" variant="micro" class="size-3.5" aria-hidden="true" />
                                {{ $reponse->auteurAffiche() }}
                            </span>
                            <time datetime="{{ $reponse->created_at->toIso8601String() }}" class="font-mono text-xs text-ink-2">
                                {{ $reponse->created_at->copy()->setTimezone(AuditLog::FUSEAU)->isoFormat('LLL') }}
                            </time>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $reponse->message }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @can('repondre', $demarche)
        <form wire:submit="envoyer" class="mt-4 space-y-3">
            @if ($this->estAgent)
                <flux:select wire:model.live="modele" label="Réponse type (facultatif)">
                    <flux:select.option value="">Choisir une réponse type…</flux:select.option>
                    @foreach (ReponseDemarche::MODELES as $cle => $modeleType)
                        <flux:select.option :value="$cle">{{ $modeleType['titre'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:textarea
                wire:model="message"
                :label="$this->estAgent ? 'Votre réponse à l’habitant' : 'Répondre à la mairie'"
                rows="5"
                maxlength="{{ ReponseDemarche::MESSAGE_MAX }}"
                required
            />

            @error('throttle')
                <flux:text class="text-red-600">{{ $message }}</flux:text>
            @enderror

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="envoyer">{{ $this->estAgent ? 'Envoyer la réponse' : 'Envoyer' }}</span>
                    <span wire:loading wire:target="envoyer">Envoi…</span>
                </flux:button>
            </div>
        </form>
    @endcan
</x-tn.surface>
