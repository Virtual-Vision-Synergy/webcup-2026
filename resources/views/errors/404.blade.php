<x-layouts::public :title="__('Page introuvable')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.map class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">Erreur 404</flux:text>

        <flux:heading size="xl" level="1">Page introuvable</flux:heading>

        <flux:text>
            Le service ou la page que vous cherchez n'existe pas ou a été déplacé.
            Consultez l'annuaire pour retrouver le bon interlocuteur à la Mairie de Nova Terra.
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" icon="arrow-left" :href="route('services.index')">
                Retour à la liste des services
            </flux:button>
            <flux:button variant="ghost" :href="route('home')">Accueil</flux:button>
        </div>
    </section>
</x-layouts::public>
