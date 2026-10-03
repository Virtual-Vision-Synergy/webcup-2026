@props(['onglet'])

@php $actif = request()->routeIs($onglet['match']); @endphp

<a
    href="{{ route($onglet['route']) }}"
    @if ($actif) aria-current="page" @endif
    @class([
        'flex h-full min-h-12 flex-col items-center justify-center gap-1 text-[11px] font-medium',
        'text-cyan' => $actif,
        'text-ink-2' => ! $actif,
    ])
>
    <flux:icon :name="$onglet['icon']" class="size-[22px]" />
    {{ __($onglet['label']) }}
</a>
