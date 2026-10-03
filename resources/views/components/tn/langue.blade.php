@php
    use App\Http\Middleware\DefinirLangue;

    $courante = app()->getLocale();
@endphp

{{-- Choix de la langue (fichiers lang/{code}.json). Simples liens GET : aucune interférence avec Livewire. --}}
<nav {{ $attributes->class('flex items-center gap-1') }} aria-label="{{ __('Langue') }}">
    @foreach (DefinirLangue::LANGUES as $code => $libelle)
        <a
            href="{{ route('langue', $code) }}"
            data-navigate-ignore
            lang="{{ $code }}"
            title="{{ $libelle }}"
            @if ($code === $courante) aria-current="true" @endif
            @class([
                'inline-flex h-9 min-w-9 items-center justify-center rounded-sm px-2 font-mono text-xs uppercase transition-colors',
                'bg-cyan/12 text-cyan' => $code === $courante,
                'text-ink-2 hover:bg-cyan/8 hover:text-ink' => $code !== $courante,
            ])
        >{{ $code }}<span class="sr-only"> {{ $libelle }}</span></a>
    @endforeach
</nav>
