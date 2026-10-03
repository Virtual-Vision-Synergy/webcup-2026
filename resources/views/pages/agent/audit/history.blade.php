<?php

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * F48 : historique complet d'un élément (qui a modifié quoi, avant / après), en lecture seule.
 * {type} est traduit par la liste blanche AuditLog::HISTORY_TYPES : jamais de nom de classe pris dans l'URL.
 * Droits : lire le journal (agent / admin) ET avoir le droit d'ouvrir l'élément lui-même (F34 pour les comptes).
 */
new #[Layout('layouts::agent'), Title('Historique')] class extends Component {
    use WithPagination;

    #[Locked]
    public Model $subject;

    #[Url(except: '')]
    public string $auteur = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    public function mount(string $type, string $id): void
    {
        $cle = AuditLog::HISTORY_TYPES[$type] ?? abort(404);
        $classe = AuditLog::SUBJECTS[$cle]['classe'];

        $this->subject = $classe::query()->findOrFail((int) $id);
        $this->autoriser();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->autoriser();

        $this->reset('auteur', 'du', 'au');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->auteur !== '' || $this->du !== '' || $this->au !== '';
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->autoriser();

        $du = $this->parseDate($this->du)?->startOfDay()->utc();
        $au = $this->parseDate($this->au)?->endOfDay()->utc();

        return $this->entreesDeLElement()
            ->latest('id')
            ->when(ctype_digit($this->auteur), fn (Builder $q) => $q->where('actor_id', (int) $this->auteur))
            ->when($du, fn (Builder $q) => $q->where('created_at', '>=', $du))
            ->when($au, fn (Builder $q) => $q->where('created_at', '<=', $au))
            ->paginate(20);
    }

    /**
     * Auteurs ayant modifié cet élément (dernier nom connu de chaque compte).
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function auteurs(): Collection
    {
        $this->autoriser();

        return $this->entreesDeLElement()
            ->whereNotNull('actor_id')
            ->selectRaw('actor_id, MAX(actor_name) as actor_name')
            ->groupBy('actor_id')
            ->orderBy('actor_name')
            ->pluck('actor_name', 'actor_id');
    }

    /**
     * Lien vers la fiche de l'élément (le droit d'ouverture est déjà vérifié par autoriser()).
     */
    public function lienFiche(): ?string
    {
        $route = AuditLog::SUBJECTS[class_basename($this->subject)]['route'] ?? null;

        return $route === null ? null : route($route, $this->subject);
    }

    public function nomElement(): string
    {
        return AuditLogger::labelFor($this->subject);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function entreesDeLElement(): Builder
    {
        return AuditLog::query()
            ->where('subject_type', class_basename($this->subject))
            ->where('subject_id', $this->subject->getKey());
    }

    private function autoriser(): void
    {
        $this->authorize('viewAny', AuditLog::class);
        $this->authorize(AuditLog::SUBJECTS[class_basename($this->subject)]['droit'], $this->subject);
    }

    /**
     * Date saisie (AAAA-MM-JJ) interprétée à l'heure de Madagascar ; null si absente ou invalide.
     */
    private function parseDate(string $date): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date, AuditLog::FUSEAU) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-6">
    <x-tn.page-header
        label="Historique des modifications"
        :title="$this->nomElement()"
        :subtitle="$this->items->total().' modification(s) enregistrée(s), de la plus récente à la plus ancienne. Lecture seule.'"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Journal' => route('agent.audit.index'), 'Historique' => null]"
    >
        <x-slot:actions>
            @if ($this->lienFiche())
                <flux:button :href="$this->lienFiche()" wire:navigate variant="ghost" icon="arrow-left">Retour à la fiche</flux:button>
            @endif
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto] lg:items-end">
        <flux:select wire:model.live="auteur" label="Auteur">
            <flux:select.option value="">Tous</flux:select.option>
            @foreach ($this->auteurs as $id => $nom)
                <flux:select.option value="{{ $id }}">{{ $nom }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model.live="du" label="Du" />
        <flux:input type="date" wire:model.live="au" label="Au" />

        <div class="flex items-center gap-2">
            @if ($this->hasFilters())
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Réinitialiser les filtres</flux:button>
            @endif
            <div wire:loading>
                <flux:icon.loading class="size-5" />
            </div>
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="clock" title="Aucune modification trouvée" :text="$this->hasFilters() ? 'Aucune modification ne correspond à ces filtres.' : 'Les modifications de cette fiche apparaîtront ici.'">
            @if ($this->hasFilters())
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Réinitialiser les filtres</flux:button>
            @endif
        </x-tn.empty>
    @else
        <x-tn.surface>
            <ul class="divide-y divide-line">
                @foreach ($this->items as $log)
                    <x-audit-history.entree :log="$log" wire:key="historique-{{ $log->id }}" />
                @endforeach
            </ul>
        </x-tn.surface>

        {{ $this->items->links() }}
    @endif
</section>
