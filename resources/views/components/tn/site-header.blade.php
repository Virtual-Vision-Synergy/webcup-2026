@php
    use Illuminate\Support\Facades\Route;

    $liens = array_filter(config('navigation.header'), fn (array $lien): bool => Route::has($lien['route']));
    $connecte = auth()->check();
@endphp

{{-- Header vitré : 72px sur desktop (logo, nav, état de l'API, thème, compte), minimal sur mobile (logo + compte). --}}
<header {{ $attributes->class('tn-glass sticky top-0 z-40 border-b') }}>
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 lg:h-[72px] lg:px-8">
        <x-app-logo href="{{ route('home') }}" />

        <nav class="ms-6 hidden h-full items-stretch lg:flex" aria-label="Navigation principale">
            @foreach ($liens as $lien)
                @php $actif = request()->routeIs($lien['match']); @endphp
                <a
                    href="{{ route($lien['route']) }}"
                    @if ($actif) aria-current="page" @endif
                    @class([
                        'flex items-center px-3 text-[0.9375rem] font-medium transition-colors',
                        'tn-tab-active' => $actif,
                        'text-ink-2 hover:text-ink' => ! $actif,
                    ])
                >{{ $lien['label'] }}</a>
            @endforeach
        </nav>

        <div class="ms-auto flex items-center gap-2">
            <x-tn.api-status class="max-lg:hidden" />
<<<<<<< HEAD
            <x-tn.langue />
=======
            <x-tn.contrast-toggle class="max-lg:hidden" />
            <x-tn.text-size class="max-lg:hidden" />
>>>>>>> origin/main
            <x-tn.theme-toggle class="max-lg:hidden" />

            @if ($connecte)
                <flux:button :href="route('dashboard')" variant="primary" class="h-10! max-lg:hidden">Mon espace</flux:button>
                <a href="{{ route('profile.edit') }}" class="flex size-11 items-center justify-center lg:hidden">
                    <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                    <span class="sr-only">Mon compte : {{ auth()->user()->name }}</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-11 items-center rounded-sm px-3 text-[0.9375rem] font-medium text-ink-2 hover:text-ink">Connexion</a>
                @if (Route::has('register'))
                    <flux:button :href="route('register')" variant="primary" class="h-10! max-lg:hidden">Créer un compte</flux:button>
                @endif
            @endif
        </div>
    </div>
</header>
