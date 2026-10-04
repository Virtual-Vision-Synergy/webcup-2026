<x-layouts::public :title="__('Trop de demandes')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.clock class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">{{ __('Erreur 429') }}</flux:text>

        <flux:heading size="xl" level="1">{{ __('Trop de demandes en peu de temps') }}</flux:heading>

        @isset($message)
            {{-- F81 : délai précis transmis par le limiteur (inscription, mot de passe oublié). --}}
            <flux:callout variant="warning" icon="clock" class="text-start" data-test="message-429">
                <flux:callout.text>{{ $message }}</flux:callout.text>
            </flux:callout>
        @endisset

        <flux:text>
            {{ __('Par sécurité et pour que la plateforme reste disponible pour tous les habitants, cette action est limitée.') }}
            @unless (isset($message))
                {{ __('Patientez une minute, puis réessayez : vos informations ne sont pas perdues.') }}
            @endunless
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" icon="arrow-left" :href="url()->previous(route('home'))">
                {{ __('Revenir à la page précédente') }}
            </flux:button>
            <flux:button variant="ghost" :href="route('home')">{{ __('Accueil') }}</flux:button>
        </div>

        {{-- F94 : consignes, numéros d'urgence et coordonnées utiles, toujours disponibles. --}}
        <flux:text class="text-sm" data-test="lien-infos-essentielles">
            <a href="{{ route('infos-essentielles') }}" class="font-medium text-cyan hover:underline">{{ __('Infos essentielles : consignes, urgences et mairie') }} →</a>
        </flux:text>
    </section>
</x-layouts::public>
