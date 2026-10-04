<?php

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F47 : fiche d'une entrée du journal (qui, quoi, quand, sur quel élément, avant / après). Lecture seule.
 */
new #[Layout('layouts::agent'), Title('Entrée du journal')] class extends Component {
    #[Locked]
    public AuditLog $log;

    public function mount(AuditLog $auditLog): void
    {
        $this->authorize('view', $auditLog);
        $this->log = $auditLog;
    }

    /**
     * Lien vers l'élément s'il existe encore et si l'agent a le droit de l'ouvrir.
     */
    #[Computed]
    public function lienElement(): ?string
    {
        $this->authorize('view', $this->log);

        $config = AuditLog::SUBJECTS[$this->log->subject_type] ?? null;
        $subject = $this->elementExistant();

        if ($config === null || $config['route'] === null || $subject === null || ! auth()->user()->can($config['droit'], $subject)) {
            return null;
        }

        return route($config['route'], $subject);
    }

    /**
     * Lien vers l'historique complet de l'élément (F48), si l'agent a le droit de le consulter.
     */
    #[Computed]
    public function lienHistorique(): ?string
    {
        $this->authorize('view', $this->log);

        $subject = $this->elementExistant();

        if ($subject === null || ! AuditLog::peutVoirHistorique(auth()->user(), $subject)) {
            return null;
        }

        return route('agent.history.show', ['type' => AuditLog::slugFor($subject), 'id' => $subject->getKey()]);
    }

    #[Computed]
    public function elementExistant(): ?Model
    {
        $this->authorize('view', $this->log);

        return $this->log->subject();
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Journal"
        :title="$log->libelleAction()"
        :subtitle="$log->phrase()"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Journal' => route('agent.audit.index'), 'Entrée n° '.$log->id => null]"
    >
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-ink-2">
                <x-tn.status-badge :etat="$log->etatAction()">{{ $log->libelleAction() }}</x-tn.status-badge>
                <span>{{ $log->dateLocale('d/m/Y à H:i:s') }} (heure de Madagascar)</span>
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if ($this->lienHistorique)
                <flux:button :href="$this->lienHistorique" wire:navigate icon="clock">Historique de cet élément</flux:button>
            @endif
            <flux:button :href="route('agent.audit.index')" wire:navigate variant="ghost" icon="arrow-left">Retour au journal</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-2">Qui, quoi, quand</x-tn.section-label>
        <dl>
            <x-tn.field label="Auteur">
                {{ $log->actor_name }}
                @if ($log->actor_role)
                    <span class="text-ink-2">— {{ $log->actor_role }}</span>
                @endif
                @if ($log->actor_id !== null && $log->actor === null)
                    <span class="text-ink-2">(compte supprimé depuis)</span>
                @endif
            </x-tn.field>
            <x-tn.field label="Action">{{ $log->libelleAction() }}</x-tn.field>
            <x-tn.field label="Date">{{ $log->dateLocale('d/m/Y à H:i:s') }}</x-tn.field>
            <x-tn.field label="Élément">
                @if ($this->lienElement)
                    <flux:link :href="$this->lienElement" wire:navigate>{{ $log->subject_label }}</flux:link>
                @else
                    {{ $log->subject_label }}
                    @if ($log->subject_id !== null && $this->elementExistant === null)
                        <span class="text-ink-2">(élément supprimé)</span>
                    @endif
                @endif
            </x-tn.field>
            <x-tn.field label="Adresse IP">{{ $log->ip ?? '—' }}</x-tn.field>
            <x-tn.field label="Navigateur"><span class="break-words text-sm text-ink-2">{{ $log->user_agent ?? '—' }}</span></x-tn.field>
        </dl>
    </x-tn.surface>

    <x-tn.surface>
        <x-tn.section-label as="h2" class="mb-3">Avant / après</x-tn.section-label>
        @if (empty($log->changes))
            <flux:text>Aucun détail de valeur pour cette opération.</flux:text>
        @else
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Champ</flux:table.column>
                        <flux:table.column>Avant</flux:table.column>
                        <flux:table.column>Après</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($log->changes as $champ => $valeurs)
                            <flux:table.row wire:key="champ-{{ $champ }}">
                                <flux:table.cell class="font-medium">{{ AuditLog::libelleChamp($champ) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal! break-words text-ink-2">{{ $log->valeurAffichee((string) $champ, $valeurs['avant'] ?? null) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal! break-words">{{ $log->valeurAffichee((string) $champ, $valeurs['apres'] ?? null) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </x-tn.surface>

    <flux:text class="text-sm">Ce journal est en lecture seule : cette entrée ne peut être ni modifiée ni supprimée.</flux:text>
</section>
