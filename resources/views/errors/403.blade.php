<x-layouts::public :title="__('Accès refusé')">
    <section class="mx-auto flex max-w-xl flex-col items-center gap-4 py-16 text-center">
        <flux:icon.lock-closed class="size-12 text-zinc-400" />

        <flux:text class="text-sm font-semibold uppercase tracking-wide">Erreur 403</flux:text>

        <flux:heading size="xl" level="1">Accès refusé</flux:heading>

        {{-- F70 : motif précis venant de la policy (Response::deny), sans rien révéler du contenu demandé. --}}
        @php($motif = isset($exception) ? trim((string) $exception->getMessage()) : '')
        @if ($motif !== '' && $motif !== 'This action is unauthorized.' && $motif !== 'Forbidden')
            <flux:text class="text-base text-ink" data-test="motif-refus">{{ $motif }}</flux:text>
        @else
            <flux:text>
                Vous n’avez pas les droits nécessaires pour consulter cette page ou effectuer cette action.
                Si vous pensez qu’il s’agit d’une erreur, contactez votre administrateur ou la Mairie de Nova Terra.
            </flux:text>
        @endif

        @auth
            <flux:text class="text-sm">Cette tentative a été enregistrée dans le journal de sécurité.</flux:text>
        @endauth

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

        {{-- F94 : consignes, numéros d'urgence et coordonnées utiles, toujours disponibles. --}}
        <flux:text class="text-sm" data-test="lien-infos-essentielles">
            <a href="{{ route('infos-essentielles') }}" class="font-medium text-cyan hover:underline">{{ __('Infos essentielles : consignes, urgences et mairie') }} →</a>
        </flux:text>
    </section>
</x-layouts::public>
