{{--
    F31 : bandeau « Alerte canicule » affiché UNIQUEMENT aux habitants des quartiers touchés (pas aux autres, ni aux visiteurs).
    Inclus dans <x-tn.bandeau-annonces />, donc présent en haut de toutes les pages (liste mise en cache : AlerteCanicule::pourBandeau).
--}}
@php
    $alerteCanicule = \App\Models\AlerteCanicule::pourBandeau(auth()->user());
@endphp

@if ($alerteCanicule)
    <div role="{{ $alerteCanicule['niveau'] === 'vigilance' ? 'status' : 'alert' }}" data-test="bandeau-canicule" class="border-b border-orange-300 bg-orange-50 px-4 py-2 text-orange-950 lg:px-8 dark:border-orange-700 dark:bg-orange-950 dark:text-orange-50">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <flux:icon.sun class="size-4 shrink-0" aria-hidden="true" />
            <p class="flex-1">
                <strong class="font-semibold">{{ __('Canicule — :niveau dans votre quartier.', ['niveau' => __(\App\Models\AlerteCanicule::NIVEAU_LABELS[$alerteCanicule['niveau']] ?? $alerteCanicule['niveau'])]) }}</strong>
                {{ \Illuminate\Support\Str::limit($alerteCanicule['message'], 140) }}
            </p>
            <a href="{{ route('canicule') }}" wire:navigate class="font-semibold underline">{{ __('Conseils adaptés') }}</a>
        </div>
    </div>
@endif
