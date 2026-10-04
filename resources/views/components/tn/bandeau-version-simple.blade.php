{{--
    F62 : bandeau affiché sur toutes les pages quand la « Version simple » est active.
    Retour à la version complète en un clic (formulaire POST, fonctionne sans JavaScript).
--}}
@if (\App\Support\VersionSimple::actif())
    <div class="border-b border-line bg-surface" data-test="bandeau-version-simple">
        <form method="POST" action="{{ route('version-simple') }}" class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2 text-sm lg:px-8">
            @csrf
            <p class="text-ink">{{ __('Vous consultez la version simple : texte, liens et formulaires, sans images ni animations.') }}</p>
            <button type="submit" data-test="version-complete" class="min-h-11 font-semibold text-cyan underline">
                {{ __('Revenir à la version complète') }}
            </button>
        </form>
    </div>
@endif
