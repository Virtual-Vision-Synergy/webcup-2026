@php
    use App\Http\Middleware\DefinirLangue;

    $courante = app()->getLocale();
@endphp

{{-- D14 : choix de la langue, chaque langue écrite dans sa propre langue. POST + CSRF (route publique décidée) :
     de simples formulaires HTML, sans interférence avec Livewire. --}}
<nav {{ $attributes->class('flex flex-wrap items-center gap-1') }} aria-label="{{ __('Langue') }}">
    @foreach (DefinirLangue::langues() as $code => $libelle)
        <form method="POST" action="{{ route('langue', $code) }}" data-navigate-ignore>
            @csrf
            <button
                type="submit"
                lang="{{ $code }}"
                @if ($code === $courante) aria-current="true" @endif
                @class([
                    'inline-flex h-9 items-center justify-center rounded-sm px-2 text-xs font-medium transition-colors',
                    'bg-cyan/12 text-cyan' => $code === $courante,
                    'text-ink-2 hover:bg-cyan/8 hover:text-ink' => $code !== $courante,
                ])
            >{{ $libelle }}</button>
        </form>
    @endforeach
</nav>
