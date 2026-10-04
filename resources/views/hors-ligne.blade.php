{{--
    F93 : page « Vous êtes hors ligne », publique (décision : elle doit s'afficher à tous, connectés ou non, sans réseau).
    Mise en cache par le service worker (public/sw.js) à l'installation ; aucune donnée personnelle rendue côté serveur.
    La liste des pages consultables et des brouillons est lue dans ce navigateur (Alpine « tnPagesHorsLigne »).
--}}
<x-layouts::site :title="__('Vous êtes hors ligne')">
    <div class="mx-auto max-w-3xl space-y-8" x-data="tnPagesHorsLigne">
        <header class="space-y-2">
            <flux:icon.signal-slash class="size-10 text-magenta" aria-hidden="true" />
            <flux:heading size="xl" level="1">{{ __('Vous êtes hors ligne') }}</flux:heading>
            <flux:text>
                {{ __('La connexion au réseau est coupée ou très lente. Pas d’inquiétude : les pages que vous avez déjà consultées restent lisibles, et un formulaire rempli maintenant est gardé sur cet appareil jusqu’au retour du réseau.') }}
            </flux:text>
            <flux:button variant="primary" icon="arrow-path" x-on:click="window.location.reload()">{{ __('Réessayer') }}</flux:button>
        </header>

        <section aria-labelledby="hl-urgences" class="space-y-3 rounded-md border border-line bg-surface p-5">
            <flux:heading size="lg" level="2" id="hl-urgences">{{ __('En cas d’urgence') }}</flux:heading>
            <flux:text>{{ __('Ces numéros fonctionnent même sans internet : appelez-les depuis votre téléphone.') }}</flux:text>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Models\Service::NUMEROS_URGENCE as $numero)
                    <li class="rounded-md bg-surface-2 p-3">
                        <a href="tel:{{ preg_replace('/\s+/', '', $numero['numero']) }}" class="text-2xl font-semibold text-cyan">{{ $numero['numero'] }}</a>
                        <p class="font-medium">{{ __($numero['label']) }}</p>
                        <p class="text-sm text-ink-2">{{ __($numero['detail']) }}</p>
                    </li>
                @endforeach
            </ul>
            {{-- F94 : page allégée gardée par le service worker (consignes, mairie, état des services). --}}
            <p data-test="lien-infos-essentielles">
                <a href="{{ route('infos-essentielles') }}" class="font-medium text-cyan hover:underline">{{ __('Infos essentielles : consignes en cours, coordonnées de la mairie, horaires et état des services') }} →</a>
            </p>
        </section>

        <section aria-labelledby="hl-pages" class="space-y-3">
            <flux:heading size="lg" level="2" id="hl-pages">{{ __('Pages consultables sans réseau') }}</flux:heading>
            <template x-if="pages.length === 0">
                <flux:text>{{ __('Aucune page enregistrée pour l’instant : les pages essentielles (accueil, urgences, services, mes demandes) sont gardées automatiquement quand vous les consultez avec du réseau.') }}</flux:text>
            </template>
            <ul class="divide-y divide-line rounded-md border border-line bg-surface">
                <template x-for="page in pages" :key="page.url">
                    <li class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                        <a x-bind:href="page.url" class="font-medium text-cyan hover:underline" x-text="page.titre"></a>
                        <span class="text-xs text-ink-2" x-text="@js(__('Mis à jour le')) + ' ' + date(page.le)"></span>
                    </li>
                </template>
            </ul>
        </section>

        <section aria-labelledby="hl-brouillons" class="space-y-3" x-show="brouillons.length" x-cloak>
            <flux:heading size="lg" level="2" id="hl-brouillons">{{ __('Brouillons en attente d’envoi') }}</flux:heading>
            <ul class="space-y-2">
                <template x-for="brouillon in brouillons" :key="brouillon.cle">
                    <li class="rounded-md border border-line bg-surface px-4 py-3">
                        <a x-bind:href="brouillon.chemin" class="font-medium text-cyan hover:underline" x-text="brouillon.libelle"></a>
                        <span class="block text-xs text-ink-2" x-text="@js(__('Enregistré le')) + ' ' + date(brouillon.le)"></span>
                    </li>
                </template>
            </ul>
            <flux:text>{{ __('Au retour du réseau, un bandeau vous proposera de les envoyer. Un même formulaire n’est jamais enregistré deux fois.') }}</flux:text>
        </section>

        <flux:text class="text-xs">{{ __('Informations de cette page mises à jour le :date.', ['date' => now()->translatedFormat('j F Y à H:i')]) }}</flux:text>
    </div>
</x-layouts::site>
