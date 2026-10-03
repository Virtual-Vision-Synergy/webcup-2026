{{-- Sélecteur de langue (FR / EN) : formulaire POST, fonctionne sans JavaScript, choix mémorisé en session et cookie. --}}
<div {{ $attributes->class('inline-flex items-center rounded-sm border border-line p-0.5') }} role="group" aria-label="{{ __('Language') }}">
    @foreach (config('app.available_locales') as $code => $nom)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button
                type="submit"
                lang="{{ $code }}"
                title="{{ $nom }}"
                aria-label="{{ $nom }}"
                @if (app()->getLocale() === $code) aria-current="true" @endif
                @class([
                    'inline-flex h-9 min-w-10 items-center justify-center rounded-xs px-2 font-mono text-xs font-medium uppercase tracking-[.06em] transition-colors',
                    'bg-cyan text-on-cyan' => app()->getLocale() === $code,
                    'text-ink-2 hover:text-ink' => app()->getLocale() !== $code,
                ])
            >{{ $code }}</button>
        </form>
    @endforeach
</div>
