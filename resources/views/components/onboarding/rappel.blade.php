@php
    use App\Services\OnboardingProgress;

    $user = auth()->user();
    $progress = $user ? OnboardingProgress::pour($user) : null;
@endphp

{{-- Rappel discret du tableau de bord tant que le parcours de prise en main est en cours (ni passé, ni terminé). --}}
@if ($progress?->doitAfficher())
    <section aria-labelledby="titre-rappel-onboarding" class="rounded-md border border-cyan/30 bg-cyan/5 p-4 md:p-5" data-test="onboarding-rappel">
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <div class="min-w-0 flex-1">
                <h2 id="titre-rappel-onboarding" class="font-medium text-ink">
                    Reprendre la prise en main — {{ $progress->nombreFaites() }}/{{ OnboardingProgress::TOTAL_ETAPES }}
                </h2>
                <p class="mt-1 text-sm text-ink-2">{{ __('Quelques minutes pour découvrir les services de la ville et déposer votre première démarche.') }}</p>
                <x-onboarding.progression :progress="$progress" compact class="mt-3 max-w-md" />
            </div>
            <flux:button variant="primary" icon:trailing="arrow-right" :href="route('onboarding.show')" wire:navigate>{{ __('Reprendre') }}</flux:button>
        </div>
    </section>
@endif
