<?php

use App\Auth\LoginThrottle;
use App\Models\ActionLog;
use App\Models\LoginAttempt;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
| F37 : journal des tentatives de connexion, en lecture seule (agents et admins).
| Déblocage d'un couple e-mail + IP : admins uniquement (LoginAttemptPolicy::unlock).
*/
new #[Layout('layouts::agent'), Title('Sécurité des connexions')] class extends Component {
    use WithPagination;

    /** Une IP qui a visé au moins ce nombre de comptes en 24 h est signalée. */
    public const SEUIL_MULTI_COMPTES = 3;

    #[Url(except: '24h')]
    public string $periode = '24h';

    #[Url(except: '')]
    public string $email = '';

    #[Url(except: '')]
    public string $ip = '';

    #[Url(except: '')]
    public string $motif = '';

    public function mount(): void
    {
        $this->authorize('viewAny', LoginAttempt::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorize('viewAny', LoginAttempt::class);

        $this->reset('periode', 'email', 'ip', 'motif');
        $this->resetPage();
    }

    public function unlock(int $attemptId): void
    {
        $this->authorize('unlock', LoginAttempt::class);

        $attempt = LoginAttempt::findOrFail($attemptId);

        app(LoginThrottle::class)->unlock($attempt->email, (string) $attempt->ip);
        ActionLog::record('login_unlocked', $attempt);

        Flux::toast(variant: 'success', text: 'Accès débloqué pour '.$attempt->email.' depuis '.$attempt->ip.'.');
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('viewAny', LoginAttempt::class);

        $jours = LoginAttempt::PERIOD_OPTIONS[$this->periode] ?? 1;

        return LoginAttempt::query()
            ->failed()
            ->since(now()->subDays($jours))
            ->when(trim($this->email) !== '', fn (Builder $q) => $q->where('email', 'like', '%'.addcslashes(trim($this->email), '%_\\').'%'))
            ->when(trim($this->ip) !== '', fn (Builder $q) => $q->where('ip', 'like', addcslashes(trim($this->ip), '%_\\').'%'))
            ->when(array_key_exists($this->motif, LoginAttempt::REASON_OPTIONS), fn (Builder $q) => $q->where('reason', $this->motif))
            ->latest('id')
            ->paginate(15);
    }

    /**
     * Résumé des dernières 24 h (quelques requêtes agrégées, pas de N+1).
     *
     * @return array{echecs: int, blocages: int, comptes: Collection<int, LoginAttempt>, ips: Collection<int, LoginAttempt>}
     */
    #[Computed]
    public function resume(): array
    {
        $this->authorize('viewAny', LoginAttempt::class);

        $depuis = now()->subDay();
        $echecs = fn () => LoginAttempt::query()->failed()->since($depuis);

        $blocages = DB::query()->fromSub(
            $echecs()->where('reason', LoginAttempt::REASON_LOCKED_OUT)->select('email', 'ip')->distinct(),
            'blocages'
        )->count();

        return [
            'echecs' => $echecs()->count(),
            'blocages' => $blocages,
            'comptes' => $echecs()
                ->selectRaw('email, MAX(user_id) as user_id, COUNT(*) as total')
                ->groupBy('email')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'ips' => $echecs()
                ->selectRaw('ip, COUNT(*) as total, COUNT(DISTINCT email) as comptes')
                ->groupBy('ip')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * IP ayant visé plusieurs comptes sur 24 h (une seule requête groupée).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function ipsMultiComptes(): array
    {
        $this->authorize('viewAny', LoginAttempt::class);

        return LoginAttempt::query()
            ->failed()
            ->since(now()->subDay())
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->havingRaw('COUNT(DISTINCT email) >= ?', [self::SEUIL_MULTI_COMPTES])
            ->pluck('ip')
            ->all();
    }
}; ?>

<section class="w-full space-y-6">
    <x-tn.page-header
        label="Espace agent"
        title="Sécurité des connexions"
        subtitle="Tentatives de connexion échouées et blocages temporaires. Les mots de passe ne sont jamais enregistrés."
        :breadcrumb="['Espace agent' => route('agent.index'), 'Sécurité des connexions' => null]"
    />

    @php($resume = $this->resume)

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="space-y-1">
            <flux:text size="sm">Échecs (24 h)</flux:text>
            <flux:heading size="xl" data-test="stat-echecs">{{ $resume['echecs'] }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Blocages (24 h)</flux:text>
            <flux:heading size="xl" data-test="stat-blocages">{{ $resume['blocages'] }}</flux:heading>
            <flux:text size="sm">couple(s) e-mail + IP bloqué(s)</flux:text>
        </flux:card>
        <flux:card class="space-y-2">
            <flux:text size="sm">Comptes les plus ciblés</flux:text>
            @forelse ($resume['comptes'] as $compte)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="truncate" title="{{ $compte->email }}">{{ $compte->email }}</span>
                    <span class="shrink-0 font-mono">{{ $compte->getAttribute('total') }}</span>
                </div>
            @empty
                <flux:text size="sm">Aucun échec.</flux:text>
            @endforelse
        </flux:card>
        <flux:card class="space-y-2">
            <flux:text size="sm">IP les plus actives</flux:text>
            @forelse ($resume['ips'] as $source)
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="truncate font-mono">{{ $source->ip ?? 'inconnue' }}</span>
                    <span class="shrink-0 font-mono" title="{{ $source->getAttribute('comptes') }} compte(s) visé(s)">
                        {{ $source->getAttribute('total') }} · {{ $source->getAttribute('comptes') }} cpt
                    </span>
                </div>
            @empty
                <flux:text size="sm">Aucun échec.</flux:text>
            @endforelse
        </flux:card>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:select wire:model.live="periode" label="Période" class="sm:max-w-40">
            <flux:select.option value="24h">24 dernières heures</flux:select.option>
            <flux:select.option value="7j">7 derniers jours</flux:select.option>
            <flux:select.option value="30j">30 derniers jours</flux:select.option>
        </flux:select>

        <flux:input wire:model.live.debounce.300ms="email" icon="magnifying-glass" label="E-mail" placeholder="ex. awa@…" class="sm:max-w-xs" />

        <flux:input wire:model.live.debounce.300ms="ip" label="Adresse IP" placeholder="ex. 41.188." class="sm:max-w-44" />

        <flux:select wire:model.live="motif" label="Motif" class="sm:max-w-52">
            <flux:select.option value="">Tous les motifs</flux:select.option>
            @foreach (\App\Models\LoginAttempt::REASON_OPTIONS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($periode !== '24h' || $email !== '' || $ip !== '' || $motif !== '')
            <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif

        <div wire:loading class="pb-2">
            <flux:icon.loading class="size-5" />
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="shield-check" title="Aucune tentative échouée" :text="$email !== '' || $ip !== '' || $motif !== '' ? 'Aucune tentative ne correspond à vos filtres.' : 'Aucune tentative de connexion échouée sur cette période.'" />
    @else
        <div class="overflow-x-auto">
            <flux:table :paginate="$this->items">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>E-mail saisi</flux:table.column>
                    <flux:table.column>Compte</flux:table.column>
                    <flux:table.column>Adresse IP</flux:table.column>
                    <flux:table.column>Motif</flux:table.column>
                    @can('unlock', \App\Models\LoginAttempt::class)
                        <flux:table.column></flux:table.column>
                    @endcan
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->items as $item)
                        <flux:table.row wire:key="tentative-{{ $item->id }}">
                            <flux:table.cell class="whitespace-nowrap">{{ $item->created_at?->format('d/m/Y H:i:s') }}</flux:table.cell>
                            <flux:table.cell class="max-w-56 truncate">{{ $item->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($item->user_id)
                                    <x-tn.status-badge etat="info">Existant</x-tn.status-badge>
                                @else
                                    <x-tn.status-badge etat="perturbe">Inconnu</x-tn.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                <span class="font-mono">{{ $item->ip ?? '—' }}</span>
                                @if (in_array($item->ip, $this->ipsMultiComptes, true))
                                    <x-tn.status-badge etat="alerte" class="ms-1">Plusieurs comptes</x-tn.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-tn.status-badge :etat="$item->reason === \App\Models\LoginAttempt::REASON_LOCKED_OUT ? 'alerte' : 'perturbe'">
                                    {{ $item->reasonLabel() }}
                                </x-tn.status-badge>
                            </flux:table.cell>
                            @can('unlock', \App\Models\LoginAttempt::class)
                                <flux:table.cell>
                                    @if ($item->reason === \App\Models\LoginAttempt::REASON_LOCKED_OUT)
                                        <div class="flex justify-end">
                                            <flux:button size="sm" variant="ghost" icon="lock-open"
                                                wire:click="unlock({{ $item->id }})"
                                                wire:confirm="Débloquer {{ $item->email }} depuis {{ $item->ip }} ?">
                                                Débloquer
                                            </flux:button>
                                        </div>
                                    @endif
                                </flux:table.cell>
                            @endcan
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</section>
