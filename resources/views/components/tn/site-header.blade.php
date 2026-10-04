@php
    use Illuminate\Support\Facades\Route;

    $liens = array_filter(config('navigation.header'), fn (array $lien): bool => Route::has($lien['route']));
    $connecte = auth()->check();
@endphp

{{-- Header vitré : 72px sur desktop (logo, nav, état de l'API, thème, compte), minimal sur mobile (logo + compte). --}}
<header {{ $attributes->class('tn-glass sticky top-0 z-40 border-b') }}>
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 lg:h-[72px] lg:px-8">
        <x-app-logo href="{{ route('home') }}" class="shrink-0" />

        <nav class="ms-2 hidden h-full min-w-0 items-stretch overflow-x-auto [scrollbar-width:none] lg:flex xl:ms-6" aria-label="{{ __('Navigation principale') }}">
            @foreach ($liens as $lien)
                @php $actif = request()->routeIs($lien['match']); @endphp
                <a
                    href="{{ route($lien['route']) }}"
                    @if ($actif) aria-current="page" @endif
                    @class([
                        'flex shrink-0 items-center whitespace-nowrap px-2 text-sm font-medium transition-colors xl:px-3 xl:text-[0.9375rem]',
                        'tn-tab-active' => $actif,
                        'text-ink-2 hover:text-ink' => ! $actif,
                    ])
                >{{ __($lien['label']) }}</a>
            @endforeach
        </nav>

        <div class="ms-auto flex shrink-0 items-center gap-2">
            <x-tn.api-status class="whitespace-nowrap max-2xl:hidden" />
            {{-- Mobile et tablette : langue et aides d'affichage sont dans le menu du bas (menu-sheet). --}}
            <x-tn.preferences-affichage class="max-lg:hidden" />

            @if ($connecte)
                <flux:button :href="route('dashboard')" variant="primary" class="h-10! whitespace-nowrap max-lg:hidden">{{ __('Mon espace') }}</flux:button>
                <a href="{{ route('profile.edit') }}" class="flex size-11 items-center justify-center lg:hidden">
                    <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                    <span class="sr-only">{{ __('Mon compte :') }} {{ auth()->user()->name }}</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-11 shrink-0 items-center whitespace-nowrap rounded-sm px-3 text-[0.9375rem] font-medium text-ink-2 hover:text-ink">{{ __('Connexion') }}</a>
                @if (Route::has('register'))
                    <flux:button :href="route('register')" variant="primary" class="h-10! whitespace-nowrap max-xl:hidden">{{ __('Créer un compte') }}</flux:button>
                @endif
            @endif
        </div>
    </div>
</header>
