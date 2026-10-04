<x-layouts::auth title="Connexion par lien">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Confirmer la connexion" description="Votre lien est valide. Cliquez sur le bouton pour vous connecter." />

        {{-- La connexion n'a lieu qu'au clic (POST) : un logiciel qui ouvre automatiquement les liens ne consomme pas celui-ci. --}}
        <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-6">
            @csrf

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-link-confirm-button">
                Me connecter
            </flux:button>
        </form>

        <div class="text-center text-sm">
            <flux:link :href="route('login')" wire:navigate>Annuler</flux:link>
        </div>
    </div>
</x-layouts::auth>
