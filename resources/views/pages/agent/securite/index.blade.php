<?php

use App\Models\SecurityEvent;
use App\Services\FilSecurite;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
| F100 : derniers événements de sécurité pour le suivi quotidien des agents.
| Lecture seule, e-mails et IP masqués (FilSecurite). Agents et admins : SecurityEventPolicy::consulterFil.
*/
new #[Layout('layouts::agent'), Title('Sécurité — derniers événements')] class extends Component {
    use WithPagination;

    public const PAR_PAGE = 15;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $gravite = '';

    public function mount(): void
    {
        $this->authorize('consulterFil', SecurityEvent::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->authorize('consulterFil', SecurityEvent::class);

        $this->reset('type', 'gravite');
        $this->resetPage();
    }

    public function marquerLus(): void
    {
        $this->authorize('consulterFil', SecurityEvent::class);

        app(FilSecurite::class)->marquerLus(auth()->user());
        unset($this->nonLus);

        Flux::toast(variant: 'success', text: 'Tous les événements sont marqués comme lus.');
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $this->authorize('consulterFil', SecurityEvent::class);

        $source = array_key_exists($this->type, FilSecurite::SOURCE_OPTIONS) ? $this->type : '';
        $gravite = array_key_exists($this->gravite, FilSecurite::GRAVITE_OPTIONS) ? $this->gravite : '';
        $evenements = app(FilSecurite::class)->evenements($source, $gravite);
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $evenements->forPage($page, self::PAR_PAGE)->values(),
            $evenements->count(),
            self::PAR_PAGE,
            $page,
            ['path' => route('agent.securite.index')],
        );
    }

    #[Computed]
    public function nonLus(): int
    {
        $this->authorize('consulterFil', SecurityEvent::class);

        return app(FilSecurite::class)->nonLus(auth()->user());
    }
}; ?>

<section class="w-full space-y-6" wire:poll.{{ \App\Support\ModeDegrade::poll(60) }}.visible>
    <x-tn.page-header
        label="Espace agent"
        title="Sécurité : derniers événements"
        subtitle="Connexions suspectes, robots bloqués et activité inhabituelle des {{ \App\Services\FilSecurite::JOURS }} derniers jours. Les e-mails et adresses IP sont masqués."
        :breadcrumb="['Espace agent' => route('agent.tableau-de-bord'), 'Sécurité' => null]"
    >
        <x-slot:actions>
            <flux:button :href="route('agent.security.index')" variant="ghost" icon="key" wire:navigate>
                Journal des connexions
            </flux:button>
        </x-slot:actions>
    </x-tn.page-header>

    @php($nonLus = $this->nonLus)
    @php($fil = app(\App\Services\FilSecurite::class))

    <div @class([
        'flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between',
        'border-magenta bg-magenta/8' => $nonLus > 0,
        'border-line bg-surface' => $nonLus === 0,
    ])>
        <div class="flex items-center gap-3">
            <flux:icon name="bell-alert" @class(['size-6 shrink-0', 'text-magenta' => $nonLus > 0, 'text-ink-2' => $nonLus === 0]) aria-hidden="true" />
            <div>
                <p class="font-semibold text-ink" data-test="securite-non-lus">
                    {{ $nonLus > 0 ? $nonLus.' événement(s) non lu(s)' : 'Aucun nouvel événement' }}
                </p>
                <p class="text-sm text-ink-2">
                    @if ($fil->derniereLecture(auth()->user()))
                        Dernière lecture : {{ $fil->derniereLecture(auth()->user())->timezone(\App\Models\Annonce::FUSEAU)->format('d/m/Y à H:i') }}
                    @else
                        Les événements des {{ \App\Services\FilSecurite::JOURS_NON_LUS_PAR_DEFAUT }} derniers jours sont comptés comme non lus.
                    @endif
                </p>
            </div>
        </div>
        @if ($nonLus > 0)
            <flux:button wire:click="marquerLus" icon="check" size="sm">Tout marquer comme lu</flux:button>
        @endif
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <flux:select wire:model.live="type" label="Type" class="sm:max-w-56">
            <flux:select.option value="">Tous les types</flux:select.option>
            @foreach (\App\Services\FilSecurite::SOURCE_OPTIONS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="gravite" label="Gravité" class="sm:max-w-44">
            <flux:select.option value="">Toutes les gravités</flux:select.option>
            @foreach (\App\Services\FilSecurite::GRAVITE_OPTIONS as $valeur => $libelle)
                <flux:select.option value="{{ $valeur }}">{{ $libelle }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($type !== '' || $gravite !== '')
            <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">Effacer les filtres</flux:button>
        @endif

        <div wire:loading class="pb-2">
            <flux:icon.loading class="size-5" />
        </div>
    </div>

    @if ($this->items->isEmpty())
        <x-tn.empty icon="shield-check" title="Aucun événement" :text="$type !== '' || $gravite !== '' ? 'Aucun événement ne correspond à vos filtres.' : 'Rien à signaler sur les '.\App\Services\FilSecurite::JOURS.' derniers jours.'" />
    @else
        <ul class="divide-y divide-line rounded-md border border-line bg-surface" data-test="securite-liste">
            @foreach ($this->items as $evenement)
                <li wire:key="evt-{{ $evenement['cle'] }}">
                    <a href="{{ route('agent.securite.show', ['source' => $evenement['source'], 'id' => $evenement['id']]) }}" wire:navigate
                        class="flex flex-col gap-2 p-4 transition hover:bg-surface-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-tn.status-badge :etat="\App\Services\FilSecurite::GRAVITE_ETATS[$evenement['gravite']]">
                                    {{ \App\Services\FilSecurite::GRAVITE_OPTIONS[$evenement['gravite']] }}
                                </x-tn.status-badge>
                                <span class="font-medium text-ink">{{ $evenement['titre'] }}</span>
                                @if ($fil->estNonLu(auth()->user(), $evenement['date']))
                                    <span class="rounded-full bg-cyan px-2 text-xs font-semibold text-on-cyan">Nouveau</span>
                                @endif
                            </div>
                            <p class="text-sm text-ink">{{ $evenement['phrase'] }}</p>
                            <p class="text-xs text-ink-2">{{ \App\Services\FilSecurite::SOURCE_OPTIONS[$evenement['source']] }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-xs text-ink-2" title="{{ $evenement['date']->copy()->timezone(\App\Models\Annonce::FUSEAU)->format('d/m/Y à H:i') }}">
                            {{ $evenement['date']->copy()->timezone(\App\Models\Annonce::FUSEAU)->format('d/m H:i') }}
                            · {{ $evenement['date']->locale('fr')->diffForHumans() }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <flux:pagination :paginator="$this->items" />
    @endif
</section>
