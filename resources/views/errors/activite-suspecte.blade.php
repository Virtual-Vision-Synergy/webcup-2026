<x-layouts::public :title="__('Activité inhabituelle')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.shield-exclamation class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">Erreur 429</flux:text>

        <flux:heading size="xl" level="1">Activité inhabituelle détectée</flux:heading>

        <flux:text>
            Plusieurs actions refusées ont été détectées depuis votre compte ou votre connexion.
            Par sécurité, l’accès est suspendu pendant {{ $minutes }} minutes. Réessayez ensuite : l’usage normal reprend automatiquement.
        </flux:text>

        <flux:text class="text-sm">Si vous pensez qu’il s’agit d’une erreur, contactez la mairie.</flux:text>
    </section>
</x-layouts::public>
