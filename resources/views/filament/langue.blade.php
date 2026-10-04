{{-- D14 : choix de la langue dans l'administration (POST + CSRF, même route que le site). Styles en ligne :
     les classes utilitaires du site ne sont pas compilées dans le thème Filament. --}}
<nav aria-label="{{ __('Langue') }}" style="display: flex; align-items: center; gap: 0.25rem; margin-inline-end: 0.5rem;">
    @foreach (\App\Http\Middleware\DefinirLangue::langues() as $code => $libelle)
        <form method="POST" action="{{ route('langue', $code) }}">
            @csrf
            <button
                type="submit"
                lang="{{ $code }}"
                @if ($code === app()->getLocale()) aria-current="true" @endif
                style="padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: {{ $code === app()->getLocale() ? '700' : '500' }}; text-decoration: {{ $code === app()->getLocale() ? 'underline' : 'none' }}; cursor: pointer;"
            >{{ $libelle }}</button>
        </form>
    @endforeach
</nav>
