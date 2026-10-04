<x-layouts::public :title="__('Page introuvable')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.map class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">{{ __('Erreur 404') }}</flux:text>

        <flux:heading size="xl" level="1">{{ __('Page introuvable') }}</flux:heading>

        <flux:text>
            {{ __('Le service ou la page que vous cherchez n\'existe pas ou a été déplacé.') }}
            {{ __('Consultez l\'annuaire pour retrouver le bon interlocuteur à la Mairie de Nova Terra.') }}
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" icon="arrow-left" :href="route('services.index')">
                {{ __('Retour à la liste des services') }}
            </flux:button>
            <flux:button variant="ghost" :href="route('home')">{{ __('Accueil') }}</flux:button>
        </div>

        {{-- F94 : consignes, numéros d'urgence et coordonnées utiles, toujours disponibles. --}}
        <flux:text class="text-sm" data-test="lien-infos-essentielles">
            <a href="{{ route('infos-essentielles') }}" class="font-medium text-cyan hover:underline">{{ __('Infos essentielles : consignes, urgences et mairie') }} →</a>
        </flux:text>
    </section>
</x-layouts::public>
