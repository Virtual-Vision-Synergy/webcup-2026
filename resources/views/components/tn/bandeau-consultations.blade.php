{{--
    F65 : bandeau des consultations ouvertes qui concernent l'habitant connecté (toute la ville ou son quartier)
    et auxquelles il n'a pas encore répondu. Disparaît dès qu'il a participé.
--}}
@php
    $habitant = auth()->user();
    $aRepondre = $habitant !== null && $habitant->isCitoyen()
        ? \App\Models\Consultation::query()
            ->ouvertes()
            ->pourHabitant($habitant)
            ->whereDoesntHave('participations', fn ($query) => $query->where('user_id', $habitant->id))
            ->orderBy('cloture_le')
            ->get(['id', 'question', 'cloture_le'])
        : collect();
    $premiere = $aRepondre->first();
@endphp

@if ($premiere)
    <div {{ $attributes->class('border-b border-cyan/35 bg-cyan/8 px-4 py-3 lg:px-8') }} role="region" aria-label="{{ __('Consultation en cours') }}">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2">
            <flux:icon.chat-bubble-left-right class="size-5 shrink-0 text-cyan" aria-hidden="true" />
            <p class="min-w-0 flex-1 text-sm text-ink">
                <span class="font-semibold">{{ __('La ville vous consulte :') }}</span>
                {{ $premiere->question }}
                <span class="font-mono text-xs text-ink-2">· {{ __('jusqu’au :date', ['date' => \App\Models\Remontee::dateLocale($premiere->cloture_le)]) }}</span>
                @if ($aRepondre->count() > 1)
                    <span class="text-ink-2">{{ __('(et :n autre(s))', ['n' => $aRepondre->count() - 1]) }}</span>
                @endif
            </p>
            <flux:button size="sm" variant="primary" :href="$aRepondre->count() > 1 ? route('consultations.index') : route('consultations.show', $premiere)" wire:navigate>
                {{ __('Donner mon avis') }}
            </flux:button>
        </div>
    </div>
@endif
