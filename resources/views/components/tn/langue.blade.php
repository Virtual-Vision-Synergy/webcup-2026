@php
    use App\Models\Traduction;

    $courante = Traduction::langueCourante();
@endphp

{{-- Choix de la langue des contenus (services, démarches). Le français est la langue de repli. --}}
<div {{ $attributes->class('flex items-center gap-1') }} aria-label="Langue des contenus" @wire:ignore>
    @foreach (Traduction::LANGUES as $code => $libelle)
        <a
            href="{{ route('langue', $code) }}"
            lang="{{ $code }}"
            title="{{ $libelle }}"
            @if ($code === $courante) aria-current="page" @endif
            @class([
                'inline-flex h-9 min-w-9 items-center justify-center rounded-sm px-2 font-mono text-xs uppercase transition-colors',
                'bg-cyan/12 text-cyan' => $code === $courante,
                'text-ink-2 hover:bg-cyan/8 hover:text-ink' => $code !== $courante,
            ])
        >{{ $code }}<span class="sr-only"> {{ $libelle }}</span></a>
    @endforeach
</div>
