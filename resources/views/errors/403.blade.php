<x-layouts::public :title="__('Accès refusé')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.lock-closed class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">Erreur 403</flux:text>

        <flux:heading size="xl" level="1">Accès refusé</flux:heading>

        <flux:text>
            Vous n’avez pas les droits nécessaires pour consulter cette page ou effectuer cette action.
            Si vous pensez qu’il s’agit d’une erreur, contactez la Mairie de Nova Terra.
        </flux:text>

        <div class="flex flex-wrap justify-center gap-3">
            @php($precedente = url()->previous())
            @if ($precedente !== url()->current())
                <flux:button variant="primary" icon="arrow-left" :href="$precedente">Retour</flux:button>
            @endif
            @auth
                <flux:button :variant="$precedente !== url()->current() ? 'ghost' : 'primary'" :href="route('dashboard')">Mon tableau de bord</flux:button>
            @else
                <flux:button variant="ghost" :href="route('home')">Accueil</flux:button>
                <flux:button variant="ghost" :href="route('login')">Se connecter</flux:button>
            @endauth
        </div>
    </section>
</x-layouts::public>
