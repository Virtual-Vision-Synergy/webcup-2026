@props([
    'compact' => false,
])

@php
    // F27 : repli sur le français quand la traduction manque ; la mention est écrite dans la langue de l'habitant.
    $langue = \App\Http\Middleware\DefinirLangue::langues()[app()->getLocale()] ?? app()->getLocale();
@endphp

@if ($compact)
    <span {{ $attributes->class('inline-flex items-center gap-1 text-xs text-ink-2') }} data-test="contenu-en-francais">
        <flux:icon name="language" class="size-3.5" aria-hidden="true" />{{ __('Affiché en français') }}
    </span>
@else
    <p {{ $attributes->class('flex items-start gap-2 rounded-sm border border-line bg-surface px-3 py-2 text-sm text-ink-2') }} role="note" data-test="contenu-en-francais">
        <flux:icon name="language" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ __('Ce contenu n\'est pas encore disponible en :langue et s\'affiche en français.', ['langue' => $langue]) }}</span>
    </p>
@endif
