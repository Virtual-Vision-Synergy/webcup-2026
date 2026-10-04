<x-layouts::public :title="__('Lien invalide')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.shield-exclamation class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">Erreur 403</flux:text>

        <flux:heading size="xl" level="1">Ce lien n’est plus valable</flux:heading>

        <flux:text>
            Ce lien « Ce n’était pas moi » est invalide ou a expiré (il est valable 24 heures).
            Connectez-vous puis ouvrez « Mes appareils » dans vos paramètres, ou réinitialisez votre mot de passe.
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            <flux:button variant="primary" :href="route('password.request')">Réinitialiser mon mot de passe</flux:button>
            <flux:button variant="ghost" :href="route('login')">Me connecter</flux:button>
        </div>
    </section>
</x-layouts::public>
