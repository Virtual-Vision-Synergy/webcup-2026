@props(['titre' => null])

{{-- F86 : numéros d'urgence (F46) affichés en haut dès qu'une demande relève d'une urgence médicale. --}}
<section {{ $attributes->class('rounded-md border border-magenta/40 bg-magenta/8 p-4 sm:p-5') }} role="alert" aria-labelledby="titre-urgence-numeros">
    <h2 id="titre-urgence-numeros" class="flex items-center gap-2 font-semibold text-ink">
        <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-magenta" aria-hidden="true" />
        {{ $titre ?? __('Urgence médicale : appelez d’abord les secours') }}
    </h2>
    <p class="mt-1 text-sm text-ink-2">
        {{ __('Cette plateforme ne remplace pas le 15 ni le 112. Si une vie est en danger, appelez immédiatement l’un de ces numéros ; votre demande est transmise en priorité aux agents en parallèle.') }}
    </p>
    <ul class="mt-3 grid grid-cols-2 gap-2 lg:grid-cols-4">
        @foreach (\App\Models\Service::NUMEROS_URGENCE as $urgence)
            <li>
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $urgence['numero']) }}" class="flex h-full min-h-11 flex-col gap-1 rounded-md border border-line bg-surface p-3 transition-colors hover:border-magenta/50">
                    <span class="flex items-center gap-2 text-sm font-medium text-ink">
                        <flux:icon :name="$urgence['icon']" class="size-4 shrink-0 text-magenta" aria-hidden="true" />
                        {{ __($urgence['label']) }}
                    </span>
                    <span @class(['font-mono font-semibold text-magenta', 'text-2xl' => strlen($urgence['numero']) <= 4, 'text-sm' => strlen($urgence['numero']) > 4])>
                        <span class="sr-only">{{ __('Appeler le') }}</span> {{ $urgence['numero'] }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 text-sm"><a href="{{ route('urgences.index') }}" wire:navigate class="font-medium text-cyan hover:underline">{{ __('Hôpitaux et services de garde') }} →</a></p>
</section>
