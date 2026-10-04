@php
    use App\Models\Onboarding;
    use App\Services\ParOuCommencer;

    $user = auth()->user();
    $guide = $user?->can('parOuCommencer', Onboarding::class) ? ParOuCommencer::pour($user) : null;
    // Ouvert tant que l'habitant n'a commencé aucune démarche ; ensuite replié (toujours accessible).
    $ouvert = $guide !== null && ! $guide->demarcheCommencee();
@endphp

{{-- Bloc « Par où commencer ? » du tableau de bord (F72), réservé aux habitants. --}}
@if ($guide)
    <details @if ($ouvert) open @endif data-ouvert="{{ $ouvert ? 'oui' : 'non' }}" class="group rounded-md border border-cyan/30 bg-cyan/5 p-4 md:p-5" data-test="par-ou-commencer">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <span>
                <span class="block font-medium text-ink">Par où commencer ?</span>
                <span class="block text-sm text-ink-2">
                    {{ $ouvert ? 'Les services utiles dans votre situation, pour bien démarrer à Nova Terra.' : 'Revoir les services recommandés pour votre situation.' }}
                </span>
            </span>
            <flux:icon name="chevron-down" class="size-5 shrink-0 text-cyan transition-transform group-open:rotate-180" aria-hidden="true" />
        </summary>

        <div class="mt-4 space-y-4">
            @if ($guide->aRepondu())
                <x-onboarding.recommandations :services="$guide->recommandations()" :guide="$guide" />
                <a href="{{ route('onboarding.par-ou-commencer') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">Modifier ma situation</a>
            @else
                <p class="text-ink-2">Répondez à 4 questions simples (logement, famille, emploi, santé) : nous vous indiquons 3 à 5 services par lesquels commencer.</p>
                <flux:button variant="primary" icon="sparkles" :href="route('onboarding.par-ou-commencer')" wire:navigate>Trouver mes services utiles</flux:button>
            @endif
        </div>
    </details>
@endif
