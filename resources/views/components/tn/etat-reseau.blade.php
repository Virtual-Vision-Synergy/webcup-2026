{{--
    F93 : bandeau d'état du réseau (hors ligne / lent / rétabli) et brouillons à envoyer au retour de la connexion.
    Logique dans resources/js/hors-ligne.js (Alpine « tnEtatReseau ») ; les changements sont annoncés au lecteur d'écran.
--}}
<div x-data="tnEtatReseau" class="pointer-events-none fixed inset-x-0 top-0 z-[65] flex flex-col items-center gap-2 px-2 pt-2 print:hidden" data-test="etat-reseau">
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true" x-text="annonce"></p>

    <p x-show="! enLigne" x-cloak aria-hidden="true" class="pointer-events-auto flex max-w-xl items-start gap-2 rounded-md bg-magenta px-4 py-2 text-sm text-white shadow-lg">
        <flux:icon.signal-slash class="mt-0.5 size-4 shrink-0" />
        <span>
            <strong>{{ __('Vous êtes hors ligne.') }}</strong>
            {{ __('Les pages déjà consultées restent lisibles ; vos formulaires sont gardés en brouillon sur cet appareil.') }}
            <a href="{{ route('hors-ligne') }}" class="underline">{{ __('Ce qui reste disponible') }}</a>
        </span>
    </p>

    <p x-show="enLigne && lent" x-cloak aria-hidden="true" class="pointer-events-auto flex max-w-xl items-start gap-2 rounded-md bg-amber-100 px-4 py-2 text-sm text-amber-950 shadow-lg dark:bg-amber-950 dark:text-amber-50">
        <flux:icon.signal class="mt-0.5 size-4 shrink-0" />
        <span>{{ __('Connexion lente : les pages peuvent mettre du temps à s’afficher. Le mode allégé les rend plus légères.') }}</span>
    </p>

    <p x-show="enLigne && retabli && brouillons.length === 0" x-cloak x-transition.opacity aria-hidden="true" class="pointer-events-auto rounded-md bg-green px-4 py-2 text-sm text-white shadow-lg">
        {{ __('Connexion rétablie.') }}
    </p>

    <p x-show="! enLigne && garde" x-cloak aria-hidden="true" class="pointer-events-auto max-w-xl rounded-md bg-surface px-4 py-2 text-sm text-ink shadow-lg ring-1 ring-line">
        {{ __('Formulaire gardé en brouillon : vous pourrez l’envoyer dès le retour de la connexion.') }}
    </p>

    <template x-if="enLigne && brouillons.length">
        <div role="alert" class="pointer-events-auto w-full max-w-xl space-y-2 rounded-md bg-surface px-4 py-3 text-sm text-ink shadow-lg ring-1 ring-line" data-test="brouillons-a-envoyer">
            <template x-for="brouillon in brouillons" :key="brouillon.cle">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="flex-1">
                        <strong>{{ __('Connexion rétablie — envoyer ?') }}</strong>
                        <span x-text="`« ${brouillon.libelle} », brouillon du ${date(brouillon.le)}`"></span>
                    </p>
                    <flux:button size="sm" variant="primary" x-on:click="envoyer(brouillon)">{{ __('Envoyer') }}</flux:button>
                    <flux:button size="sm" variant="ghost" x-on:click="oublier(brouillon)">{{ __('Effacer le brouillon') }}</flux:button>
                </div>
            </template>
        </div>
    </template>
</div>
