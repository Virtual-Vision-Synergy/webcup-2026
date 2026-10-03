<div class="flex w-full max-w-5xl items-start gap-8 max-md:flex-col">
    <nav class="w-full md:sticky md:top-24 md:w-[220px]" aria-label="{{ __('Settings') }}">
        <ul class="flex flex-wrap gap-1 md:flex-col">
            @foreach ([['profile.edit', __('Profile'), 'users-round'], ['security.edit', __('Security'), 'shield-check'], ['appearance.edit', __('Appearance'), 'moon']] as [$route, $libelle, $icone])
                @php $actif = request()->routeIs($route); @endphp
                <li class="shrink-0">
                    <a
                        href="{{ route($route) }}"
                        wire:navigate
                        @if ($actif) aria-current="page" @endif
                        @class([
                            'flex min-h-11 items-center gap-2.5 rounded-sm border px-3 text-[0.9375rem] font-medium transition-colors',
                            'border-cyan/30 bg-cyan/8 text-cyan' => $actif,
                            'border-transparent text-ink-2 hover:bg-surface-2 hover:text-ink' => ! $actif,
                        ])
                    >
                        <flux:icon :name="$icone" class="size-4" />
                        {{ __($libelle) }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="w-full min-w-0 flex-1 rounded-md border border-line bg-surface p-5 md:p-6">
        <h2 class="tn-display text-xl font-semibold text-ink">{{ $heading ?? '' }}</h2>
        <p class="mt-1 text-ink-2">{{ $subheading ?? '' }}</p>

        <div class="mt-6 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
