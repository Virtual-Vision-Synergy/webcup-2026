{{--
    F94 : consigne d'incident publiée par un admin (Filament → « Infos essentielles »), affichée à tous (invités compris).
    Inclus en tête de <x-tn.bandeau-annonces />, donc présent en haut de toutes les pages. Non refermable :
    la consigne reste visible tant que l'admin ne l'a pas retirée.
--}}
@php($consigne = \App\Support\InfosEssentielles::consigne())

@if ($consigne)
    <div
        role="alert"
        data-test="bandeau-consigne-incident"
        @class([
            'border-b px-4 py-2 lg:px-8',
            'border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950 dark:text-red-50' => $consigne['niveau'] === 'alerte',
            'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-50' => $consigne['niveau'] !== 'alerte',
        ])
    >
        <div class="mx-auto flex max-w-7xl flex-wrap items-start gap-x-2 gap-y-1 text-sm">
            <flux:icon.megaphone class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p class="min-w-0 flex-1">
                <strong class="font-semibold">{{ $consigne['titre'] }}</strong>
                <span class="whitespace-pre-line">{{ $consigne['message'] }}</span>
            </p>
            <a href="{{ route('infos-essentielles') }}" class="shrink-0 font-semibold underline underline-offset-2">{{ __('Infos essentielles') }} →</a>
        </div>
    </div>
@endif
