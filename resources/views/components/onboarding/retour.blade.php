@php
    use App\Services\OnboardingProgress;

    $user = auth()->user();
    // Seulement si l'habitant a ouvert le parcours (flag de session) : pas de requête pour les autres pages et utilisateurs.
    $progress = $user && ! request()->routeIs('onboarding.show') && session(OnboardingProgress::SESSION_DEPUIS_PARCOURS) === true
        ? OnboardingProgress::pour($user)
        : null;
@endphp

{{-- Bandeau de retour vers le parcours de prise en main, affiché sur les pages ouvertes depuis /bienvenue. --}}
@if ($progress?->doitRevenirAuParcours())
    <div class="mx-auto mb-6 flex w-full max-w-6xl flex-wrap items-center gap-3 rounded-md border border-cyan/30 bg-cyan/5 px-4 py-3" data-test="onboarding-retour">
        <flux:icon name="sparkles" class="size-5 shrink-0 text-cyan" />
        <p class="min-w-0 flex-1 text-sm text-ink">
            <span class="font-medium">{{ __('Prise en main') }}</span>
            <span class="text-ink-2">· {{ $progress->nombreFaites() }}/{{ OnboardingProgress::TOTAL_ETAPES }} étape(s) faite(s)</span>
        </p>
        <flux:button size="sm" variant="primary" icon:trailing="arrow-right" :href="route('onboarding.show')" wire:navigate>{{ __('Continuer la prise en main') }}</flux:button>
    </div>
@endif
