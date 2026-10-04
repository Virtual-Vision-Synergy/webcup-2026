<x-layouts::auth :title="__('Activer mon compte')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Activer mon compte')" :description="__('Première connexion : saisissez l\'identifiant et le code d\'activation inscrits sur la fiche remise par un agent, puis choisissez votre code personnel.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <ol class="grid grid-cols-3 gap-2 text-center text-xs text-ink-2" aria-label="{{ __('Étapes') }}">
            <li class="flex flex-col items-center gap-1 rounded-sm border border-line p-2">
                <flux:icon name="identification" class="size-5 text-cyan" aria-hidden="true" />
                <span>1. {{ __('Identifiant') }}</span>
            </li>
            <li class="flex flex-col items-center gap-1 rounded-sm border border-line p-2">
                <flux:icon name="ticket" class="size-5 text-cyan" aria-hidden="true" />
                <span>2. {{ __('Code de la fiche') }}</span>
            </li>
            <li class="flex flex-col items-center gap-1 rounded-sm border border-line p-2">
                <flux:icon name="lock-closed" class="size-5 text-cyan" aria-hidden="true" />
                <span>3. {{ __('Code personnel') }}</span>
            </li>
        </ol>

        <form method="POST" action="{{ route('activation.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="identifiant"
                :label="__('Identifiant d\'habitant ou téléphone')"
                :aria-label="__('Identifiant d\'habitant ou téléphone')"
                :value="old('identifiant')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="HAB-7K3M9P"
                icon="identification"
            />

            <flux:input
                name="code"
                :label="__('Code d\'activation')"
                :aria-label="__('Code d\'activation')"
                type="text"
                required
                autocomplete="one-time-code"
                autocapitalize="characters"
                placeholder="XXXX-XXXX"
                icon="ticket"
            />

            <flux:input
                name="password"
                :label="__('Nouveau code personnel')"
                :aria-label="__('Nouveau code personnel')"
                type="password"
                required
                autocomplete="new-password"
                :description="__('Gardez-le pour vous : il remplace le code d\'activation à chaque connexion.')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('Confirmer le code personnel')"
                :aria-label="__('Confirmer le code personnel')"
                type="password"
                required
                autocomplete="new-password"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="activation-button">
                {{ __('Activer et me connecter') }}
            </flux:button>
        </form>

        <flux:text class="text-center text-sm">
            {{ __('Le code d\'activation ne fonctionne qu\'une seule fois. Fiche perdue ? Un agent de la mairie peut vous en imprimer une nouvelle.') }}
        </flux:text>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Compte déjà activé ?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
