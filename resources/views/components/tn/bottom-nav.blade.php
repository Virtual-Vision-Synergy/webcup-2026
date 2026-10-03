@php
    use Illuminate\Support\Facades\Route;

    $connecte = auth()->check();
    $action = config('navigation.action');

    // Connecté : Accueil (mon espace), Démarches, [Déposer], Actus, Menu. Invité : Accueil, Services, [Inscription], Actus, Menu.
    $onglets = $connecte
        ? [
            ['label' => 'Accueil', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'house'],
            ['label' => 'Démarches', 'route' => 'demarches.index', 'match' => 'demarches.index', 'icon' => 'file-text'],
        ]
        : [
            ['label' => 'Accueil', 'route' => 'home', 'match' => 'home', 'icon' => 'house'],
            ['label' => 'Services', 'route' => 'services.index', 'match' => 'services.*', 'icon' => 'landmark'],
        ];
    $actus = ['label' => 'Actus', 'route' => 'actualites.index', 'match' => 'actualites.*', 'icon' => 'newspaper'];
    $centre = $connecte
        ? $action
        : ['label' => 'Inscription', 'route' => Route::has('register') ? 'register' : 'login', 'icon' => 'plus'];
@endphp

{{-- Barre d'onglets mobile (< lg) : 5 colonnes, onglet central mis en avant. --}}
<nav
    {{ $attributes->class('tn-glass fixed inset-x-0 bottom-0 z-40 border-t lg:hidden') }}
    style="padding-bottom: env(safe-area-inset-bottom)"
    aria-label="Navigation mobile"
>
    <ul class="mx-auto grid h-16 max-w-md grid-cols-5 items-stretch">
        @foreach ($onglets as $onglet)
            @continue(! Route::has($onglet['route']))
            <li>
                <x-tn.tab :onglet="$onglet" />
            </li>
        @endforeach

        <li class="flex justify-center">
            @if (Route::has($centre['route']))
                <a
                    href="{{ route($centre['route']) }}"
                    class="-mt-[22px] flex flex-col items-center gap-1 text-[0.6875rem] font-medium text-cyan"
                >
                    <span class="tn-cta flex size-14 items-center justify-center rounded-full bg-cyan text-on-cyan ring-[5px] ring-night">
                        <flux:icon :name="$centre['icon']" class="size-6" />
                    </span>
                    {{ $centre['label'] }}
                </a>
            @endif
        </li>

        <li>
            <x-tn.tab :onglet="$actus" />
        </li>

        <li>
            <button
                type="button"
                x-ref="boutonMenu"
                x-on:click="$store.menu?.ouvrir()"
                x-bind:aria-expanded="$store.menu?.ouvert ? 'true' : 'false'"
                aria-expanded="false"
                aria-controls="tn-menu"
                class="flex h-full min-h-12 w-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium text-ink-2"
                x-bind:class="$store.menu?.ouvert && 'text-cyan!'"
            >
                <flux:icon name="layout-grid" class="size-[22px]" />
                Menu
            </button>
        </li>
    </ul>
</nav>
