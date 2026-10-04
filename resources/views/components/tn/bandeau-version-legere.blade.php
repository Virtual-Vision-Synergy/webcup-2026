{{--
    F96 : bandeau discret quand la version légère a été activée automatiquement (Save-Data, connexion lente,
    petit écran). Rendu caché tant que la détection est possible : le script de partials/head l'affiche
    dès la première page détectée. Formulaire POST : le choix explicite est mémorisé et prime ensuite.
--}}
@if (\App\Support\ModeAllege::detectionPossible())
    <div
        class="border-b border-line bg-surface"
        data-test="bandeau-version-legere"
        data-bandeau-version-legere
        @unless (\App\Support\ModeAllege::automatique()) hidden @endunless
    >
        <form method="POST" action="{{ route('mode-allege') }}" class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-1 text-sm lg:px-8">
            @csrf
            <input type="hidden" name="actif" value="0" />
            <p class="text-ink">{{ __('Version légère activée pour votre connexion.') }}</p>
            <button type="submit" data-test="version-legere-complete" class="min-h-11 font-semibold text-cyan underline">
                {{ __('Revenir à la version complète') }}
            </button>
        </form>
    </div>
@endif
