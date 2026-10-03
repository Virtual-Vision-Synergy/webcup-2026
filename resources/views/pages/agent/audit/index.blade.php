<?php

use App\Concerns\ExportsCsv;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * F47 : journal d'audit, en lecture seule. Filtres dans l'URL (GET), conservés dans la pagination.
 * Les valeurs de filtre inconnues sont ignorées (jamais injectées telles quelles dans la requête).
 */
new #[Layout('layouts::agent'), Title('Journal')] class extends Component {
    use ExportsCsv, WithPagination;

    /** Valeur du filtre « auteur » pour les actions sans utilisateur connecté (console, système). */
    public const AUTEUR_SYSTEME = 'systeme';

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $auteur = '';

    #[Url(except: '')]
    public string $element = '';

    #[Url(except: '')]
    public string $du = '';

    #[Url(except: '')]
    public string $au = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorize('viewAny', AuditLog::class);

        $this->reset('action', 'auteur', 'element', 'du', 'au');
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->action !== '' || $this->auteur !== '' || $this->element !== '' || $this->du !== '' || $this->au !== '';
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', AuditLog::class);

        return $this->filteredQuery()->latest('id')->paginate(20);
    }

    /**
     * Auteurs présents dans le journal (dernier nom connu de chaque compte).
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function auteurs(): Collection
    {
        $this->authorize('viewAny', AuditLog::class);

        return AuditLog::query()
            ->whereNotNull('actor_id')
            ->selectRaw('actor_id, MAX(actor_name) as actor_name')
            ->groupBy('actor_id')
            ->orderBy('actor_name')
            ->pluck('actor_name', 'actor_id');
    }

    /**
     * Export CSV des résultats filtrés (lui-même journalisé).
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = $this->filteredQuery();

        AuditLogger::log('exported', new AuditLog, [
            'filtres' => ['avant' => null, 'apres' => $this->hasFilters() ? http_build_query(array_filter($this->only(['action', 'auteur', 'element', 'du', 'au']))) : 'aucun'],
            'lignes' => ['avant' => null, 'apres' => (clone $query)->count()],
        ]);

        return $this->streamCsv('viewAny', AuditLog::class, $query, [
            __('Date') => fn (AuditLog $log) => $log->dateLocale(),
            __('Auteur') => fn (AuditLog $log) => $log->actor_name,
            __('Profil') => fn (AuditLog $log) => $log->actor_role,
            __('Action') => fn (AuditLog $log) => $log->libelleAction(),
            __('Élément') => fn (AuditLog $log) => $log->subject_label,
            __('Description') => fn (AuditLog $log) => $log->phrase(),
            __('Adresse IP') => fn (AuditLog $log) => $log->ip,
        ], 'journal-audit');
    }

    /**
     * @return Builder<AuditLog>
     */
    private function filteredQuery(): Builder
    {
        $du = $this->parseDate($this->du)?->startOfDay()->utc();
        $au = $this->parseDate($this->au)?->endOfDay()->utc();

        return AuditLog::query()
            ->when(array_key_exists($this->action, AuditLog::ACTION_LABELS), fn (Builder $q) => $q->where('action', $this->action))
            ->when(array_key_exists($this->element, AuditLog::SUBJECTS), fn (Builder $q) => $q->where('subject_type', $this->element))
            ->when($this->auteur === self::AUTEUR_SYSTEME, fn (Builder $q) => $q->whereNull('actor_id'))
            ->when(ctype_digit($this->auteur), fn (Builder $q) => $q->where('actor_id', (int) $this->auteur))
            ->when($du, fn (Builder $q) => $q->where('created_at', '>=', $du))
            ->when($au, fn (Builder $q) => $q->where('created_at', '<=', $au));
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

<section class="w-full space-y-6">
    <x-tn.page-header
        label="{{ __('Espace agent') }}"
        title="{{ __('Journal') }}"
        :subtitle="__(':n opération(s) tracée(s). Journal en lecture seule : aucune entrée ne peut être modifiée ni supprimée.', ['n' => $this->items->total()])"
        :breadcrumb="['Espace agent' => route('agent.index'), 'Journal' => null]"
    >
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" wire:click="export" wire:loading.attr="disabled">{{ __('Exporter (CSV)') }}</flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto] lg:items-end">
        <flux:select wire:model.live="action" label="{{ __('Type d\'action') }}">
            <flux:select.option value="">{{ __('Toutes') }}</flux:select.option>
            @foreach (AuditLog::ACTION_LABELS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ __($libelle) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="auteur" label="{{ __('Auteur') }}">
            <flux:select.option value="">{{ __('Tous') }}</flux:select.option>
            @foreach ($this->auteurs as $id => $nom)
                <flux:select.option value="{{ $id }}">{{ $nom }}</flux:select.option>
            @endforeach
            <flux:select.option value="{{ $this::AUTEUR_SYSTEME }}">{{ __('Système') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="element" label="{{ __('Type d\'élément') }}">
            <flux:select.option value="">{{ __('Tous') }}</flux:select.option>
            @foreach (AuditLog::SUBJECTS as $valeur => $type)
                <flux:select.option value="{{ $valeur }}">{{ $type['libelle'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input type="date" wire:model.live="du" label="Du" />
        <flux:input type="date" wire:model.live="au" label="Au" />

        <div class="flex items-center gap-2">
            @if ($this->hasFilters())
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('Réinitialiser les filtres') }}</flux:button>
            @endif
            <div wire:loading>
                <flux:icon.loading class="size-5" />
            </div>
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="document-text" title="{{ __('Aucune opération trouvée') }}" :text="$this->hasFilters() ? __('Aucune entrée ne correspond à ces filtres.') : __('Les opérations sensibles apparaîtront ici dès qu’elles seront réalisées.')">
            @if ($this->hasFilters())
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">{{ __('Réinitialiser les filtres') }}</flux:button>
            @endif
        </x-tn.empty>
    @else
        <x-tn.surface padding="p-0">
            <ul class="divide-y divide-line">
                @foreach ($this->items as $log)
                    <li wire:key="audit-{{ $log->id }}">
                        <a href="{{ route('agent.audit.show', $log) }}" wire:navigate class="flex flex-col gap-2 px-4 py-3 hover:bg-cyan/5 sm:flex-row sm:items-center sm:gap-4 md:px-5">
                            <span class="shrink-0 font-mono text-xs text-ink-2 sm:w-32">{{ $log->dateLocale() }}</span>
                            <span class="shrink-0 sm:w-44">
                                <x-tn.status-badge :etat="$log->etatAction()">{{ $log->libelleAction() }}</x-tn.status-badge>
                            </span>
                            <span class="min-w-0 flex-1 text-sm text-ink">{{ $log->phrase() }}</span>
                            <flux:icon.chevron-right class="hidden size-4 shrink-0 text-ink-2 sm:block" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-tn.surface>

        {{ $this->items->links() }}
    @endif
</section>
