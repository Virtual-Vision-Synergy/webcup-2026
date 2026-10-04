<x-layouts::auth title="Connexion par lien">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Recevoir un lien de connexion" description="Saisissez votre adresse e-mail : vous recevrez un lien pour vous connecter sans mot de passe." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login-link.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-link-button">
                Recevoir le lien
            </flux:button>
        </form>

        <flux:text class="text-center text-sm">
            Le lien est valable 15 minutes et ne fonctionne qu'une seule fois. Si la vérification en deux étapes est activée sur votre compte, votre code vous sera demandé ensuite.
        </flux:text>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>Vous préférez votre mot de passe ?</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
