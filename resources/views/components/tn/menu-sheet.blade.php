@php
    use Illuminate\Support\Facades\Route;

    $rubriques = array_filter(config('navigation.rubriques'), fn (array $r): bool => Route::has($r['route']));
    $user = auth()->user();
    // Compteur limité aux démarches de l'utilisateur connecté.
    $demarchesEnCours = $user ? $user->demarches()->whereIn('statut', ['deposee', 'en_cours'])->count() : 0;
    $ligne = 'flex min-h-12 w-full items-center gap-3 rounded-sm px-3 text-[0.9375rem] font-medium text-ink hover:bg-cyan/6';
@endphp

{{-- Menu mobile en feuille (bottom sheet) : piège du focus, Échap, tap sur le fond, glisser vers le bas. --}}
<div class="lg:hidden" x-data>
    {{-- Fond --}}
    <div
        x-show="$store.menu?.ouvert"
        x-cloak
        x-on:click="$store.menu?.fermer()"
        x-transition:enter="transition-opacity duration-[400ms] ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-300 ease-[cubic-bezier(.4,0,.6,1)]"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/50"
        aria-hidden="true"
    ></div>

    {{-- Feuille --}}
    <div
        id="tn-menu"
        x-show="$store.menu?.ouvert"
        x-cloak
        x-trap.inert.noscroll="$store.menu?.ouvert"
        x-on:keydown.escape.window="$store.menu?.ouvert && $store.menu?.fermer()"
        x-data="{
            depart: null,
            decalage: 0,
            instant: 0,
            debut(e) { this.depart = e.clientY; this.instant = Date.now(); this.decalage = 0; },
            glisser(e) { if (this.depart === null) return; this.decalage = Math.max(0, e.clientY - this.depart); },
            fin() {
                if (this.depart === null) return;
                const vitesse = this.decalage / Math.max(1, Date.now() - this.instant);
                if (this.decalage > 80 || vitesse > 0.5) { $store.menu?.fermer(); }
                this.depart = null;
                this.decalage = 0;
            },
        }"
        x-bind:style="decalage ? 'transform: translateY(' + decalage + 'px); transition: none' : ''"
        x-transition:enter="transition-transform duration-[480ms] ease-[cubic-bezier(.32,.72,0,1)]"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition-transform duration-300 ease-[cubic-bezier(.4,0,.6,1)]"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tn-menu-titre"
        class="fixed inset-x-2 bottom-2 z-50 flex max-h-[88dvh] flex-col overflow-hidden rounded-t-[28px] rounded-b-[20px] border border-line bg-surface shadow-2xl"
    >
        {{-- Poignée + titre : zone de glisser --}}
        <div
            class="touch-none select-none px-5 pt-2.5 pb-2"
            x-on:pointerdown="debut($event); $el.setPointerCapture($event.pointerId)"
            x-on:pointermove="glisser($event)"
            x-on:pointerup="fin()"
            x-on:pointercancel="fin()"
        >
            <div class="mx-auto h-[5px] w-[38px] rounded-full bg-ink-2/40" aria-hidden="true"></div>
            <div class="mt-2 flex items-center justify-between">
                <h2 id="tn-menu-titre" class="tn-label text-ink">{{ __('Menu') }}</h2>
                <button
                    type="button"
                    x-on:click="$store.menu?.fermer()"
                    x-on:pointerdown.stop
                    class="-me-2 flex size-11 items-center justify-center rounded-sm text-ink-2 hover:text-ink"
                    aria-label="{{ __('Fermer le menu') }}"
                >
                    <flux:icon name="x" class="size-5" />
                </button>
            </div>
        </div>

        <div class="overflow-y-auto px-3 pb-3" style="padding-bottom: max(.75rem, env(safe-area-inset-bottom))">
            @if (count($rubriques))
                <ul class="grid grid-cols-2 gap-2 px-2">
                    @foreach ($rubriques as $rubrique)
                        <li>
                            <a href="{{ route($rubrique['route']) }}" class="flex min-h-[88px] flex-col justify-between rounded-md border border-line bg-surface-2 p-3 hover:border-cyan/40">
                                <span class="flex size-9 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan" aria-hidden="true">
                                    <flux:icon :name="$rubrique['icon']" class="size-[18px]" />
                                </span>
                                <span class="mt-2 text-[0.9375rem] font-medium text-ink">{{ __($rubrique['label']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (isset($autres) && filled(trim(strip_tags((string) $autres))))
                <div class="tn-sheet-autres mt-3 border-t border-line px-2 pt-3">
                    <x-tn.section-label class="mb-1 px-1">{{ __('Autres rubriques') }}</x-tn.section-label>
                    {{ $autres }}
                </div>
            @endif

            <ul class="mt-3 border-t border-line pt-2">
                @auth
                    <li><a href="{{ route('dashboard') }}" class="{{ $ligne }}"><flux:icon name="house" class="size-5 text-ink-2" />{{ __('Mon espace') }}</a></li>
                    @if (Route::has('demarches.index'))
                        <li>
                            <a href="{{ route('demarches.index') }}" class="{{ $ligne }}">
                                <flux:icon name="file-text" class="size-5 text-ink-2" />{{ __('Mes démarches') }}
                                @if ($demarchesEnCours > 0)
                                    <span class="ms-auto rounded-xs bg-cyan px-1.5 font-mono text-xs leading-5 text-on-cyan">{{ $demarchesEnCours }}<span class="sr-only"> {{ __('en cours') }}</span></span>
                                @endif
                            </a>
                        </li>
                    @endif
                    @can('viewAgentSpace')
                        <li><a href="{{ route('agent.tableau-de-bord') }}" class="{{ $ligne }}"><flux:icon name="briefcase" class="size-5 text-ink-2" />{{ __('Espace agent') }}</a></li>
                    @endcan
                    <li><a href="{{ route('profile.edit') }}" class="{{ $ligne }}"><flux:icon name="settings" class="size-5 text-ink-2" />{{ __('Paramètres') }}</a></li>
                @else
                    <li><a href="{{ route('login') }}" class="{{ $ligne }}"><flux:icon name="log-out" class="size-5 rotate-180 text-ink-2" />{{ __('Connexion') }}</a></li>
                    @if (Route::has('register'))
                        <li><a href="{{ route('register') }}" class="{{ $ligne }}"><flux:icon name="users-round" class="size-5 text-ink-2" />{{ __('Créer un compte') }}</a></li>
                    @endif
                @endauth
                <li class="flex min-h-12 items-center justify-between gap-3 px-3">
                    <span class="flex items-center gap-3 text-[0.9375rem] font-medium text-ink">
                        <flux:icon name="language" class="size-5 text-ink-2" />{{ __('Langue') }}
                    </span>
                    <x-tn.langue class="-me-2" />
                </li>
                <li class="flex min-h-12 items-center justify-between gap-3 px-3">
                    <span class="flex items-center gap-3 text-[0.9375rem] font-medium text-ink">
                        <flux:icon name="eye"class="size-5 text-ink-2" />{{ __('Contraste élevé') }}
                    </span>
                    <x-tn.contrast-toggle class="-me-2" />
                </li>
                <li class="flex min-h-12 flex-wrap items-center justify-between gap-3 px-3 py-1">
                    <span class="flex items-center gap-3 text-[0.9375rem] font-medium text-ink">
                        <flux:icon name="magnifying-glass-plus" class="size-5 text-ink-2" />{{ __('Taille du texte') }}
                    </span>
                    <x-tn.text-size />
                </li>
                <li class="flex min-h-12 flex-wrap items-center justify-between gap-3 px-3 py-1">
                    <span class="flex items-center gap-3 text-[0.9375rem] font-medium text-ink">
                        <flux:icon name="language" class="size-5 text-ink-2" />{{ __('Langue') }}
                    </span>
                    <x-tn.langue />
                </li>
                <li class="flex min-h-12 items-center justify-between gap-3 px-3">
                    <span class="flex items-center gap-3 text-[0.9375rem] font-medium text-ink">
                        <flux:icon name="moon" class="size-5 text-ink-2" />{{ __('Apparence') }}
                    </span>
                    <x-tn.theme-toggle class="-me-2" />
                </li>
                <li><a href="{{ route('accessibility.show') }}" class="{{ $ligne }}"><flux:icon name="eye" class="size-5 text-ink-2" />{{ __('Accessibilité : toutes les aides') }}</a></li>
            </ul>

            @auth
                <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-2">
                    @csrf
                    <button type="submit" class="{{ $ligne }} text-magenta!" data-test="mobile-logout-button">
                        <flux:icon name="log-out" class="size-5" />{{ __('Se déconnecter') }}
                    </button>
                </form>
            @endauth
        </div>
    </div>
</div>
