<x-layouts::public :title="__('Trop de demandes')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.clock class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">{{ __('Erreur 429') }}</flux:text>

        <flux:heading size="xl" level="1">{{ __('Trop de demandes en peu de temps') }}</flux:heading>

        <flux:text>
            {{ __('Par sécurité et pour que la plateforme reste disponible pour tous les habitants, cette action est limitée.') }}
            {{ __('Patientez une minute, puis réessayez : vos informations ne sont pas perdues.') }}
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" icon="arrow-left" :href="url()->previous(route('home'))">
                {{ __('Revenir à la page précédente') }}
            </flux:button>
            <flux:button variant="ghost" :href="route('home')">{{ __('Accueil') }}</flux:button>
        </div>
    </section>
</x-layouts::public>
