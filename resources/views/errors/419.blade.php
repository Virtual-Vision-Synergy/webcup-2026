<x-layouts::public :title="__('Session expirée')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.clock class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">{{ __('Erreur 419') }}</flux:text>

        <flux:heading size="xl" level="1">{{ __('Votre session a expiré') }}</flux:heading>

        <flux:text>{{ __('Par sécurité, la page est restée ouverte trop longtemps sans activité. Rechargez-la puis recommencez : rien n\'a été envoyé.') }}</flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" icon="arrow-path" :href="url()->previous(route('home'))">
                {{ __('Recharger la page précédente') }}
            </flux:button>
            <flux:button variant="ghost" :href="route('home')">{{ __('Accueil') }}</flux:button>
        </div>
    </section>
</x-layouts::public>
