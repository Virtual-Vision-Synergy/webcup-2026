@props([
    'progress',
    'compact' => false,
])

@php
    /** @var \App\Services\OnboardingProgress $progress */
    $etapes = $progress->etapes();
    $faites = $progress->nombreFaites();
    $total = \App\Services\OnboardingProgress::TOTAL_ETAPES;
    $courante = $progress->etapeCourante();
    // « Étape X sur 3 » : l'étape en cours, ou la dernière quand tout est fait.
    $etapeAffichee = $courante ?? $total;
@endphp

{{-- Indicateur de progression du parcours de prise en main : texte + barre accessible + statut écrit de chaque étape. --}}
<div {{ $attributes->class('w-full') }} data-test="onboarding-progression">
    <div class="flex items-center justify-between gap-3 font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">
        <span>{{ __('Étape') }} <span class="text-ink">{{ $etapeAffichee }}</span> {{ __('sur') }} {{ $total }}</span>
        <span><span class="text-ink">{{ $faites }}</span>/{{ $total }} {{ __('faite(s)') }}</span>
    </div>

    <div
        class="mt-2 h-2 w-full overflow-hidden rounded-full bg-line"
        role="progressbar"
        aria-label="{{ __('Progression de la prise en main') }}"
        aria-valuemin="0"
        aria-valuemax="{{ $total }}"
        aria-valuenow="{{ $faites }}"
        aria-valuetext="{{ __(':faites étape(s) faite(s) sur :total', ['faites' => $faites, 'total' => $total]) }}"
    >
        <div class="h-full rounded-full bg-cyan transition-all duration-500" style="width: {{ $progress->pourcentage() }}%"></div>
    </div>

    @unless ($compact)
        <ol class="mt-4 grid gap-2 sm:grid-cols-3">
            @foreach ($etapes as $index => $etape)
                @php
                    $numero = $index + 1;
                    $statut = $etape['faite'] ? 'faite' : ($numero === $courante ? 'en_cours' : 'a_faire');
                @endphp
                <li
                    wire:key="etape-statut-{{ $etape['cle'] }}"
                    @if ($statut === 'en_cours') aria-current="step" @endif
                    @class([
                        'flex items-center gap-2 rounded-sm border px-3 py-2 text-sm',
                        'border-green/35 bg-green/8 text-ink' => $statut === 'faite',
                        'border-cyan bg-cyan/8 text-ink' => $statut === 'en_cours',
                        'border-line text-ink-2' => $statut === 'a_faire',
                    ])
                >
                    <flux:icon
                        :name="match ($statut) { 'faite' => 'check-circle', 'en_cours' => 'arrow-right-circle', default => 'ellipsis-horizontal-circle' }"
                        @class(['size-4 shrink-0', 'text-green' => $statut === 'faite', 'text-cyan' => $statut === 'en_cours'])
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ $numero }}. {{ __($etape['titre']) }}</span>
                        <span class="block font-mono text-[0.65625rem] uppercase tracking-[.06em]">
                            {{ __(match ($statut) { 'faite' => 'Faite', 'en_cours' => 'En cours', default => 'À faire' }) }}
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endunless
</div>
