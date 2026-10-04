<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Pas d\'adresse e-mail ? Utilisez votre téléphone ou votre identifiant d\'habitant.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />


        <form method="POST" action="{{ route('login.store') }}" class="relative flex flex-col gap-6">
            @csrf
            <x-anti-robot formulaire="connexion" />

            <!-- F71 : e-mail, identifiant d'habitant ou téléphone -->
            <flux:input
                name="email"
                :label="__('E-mail, téléphone ou identifiant d\'habitant')"
                :value="old('email')"
                type="text"
                required
                autofocus
                autocomplete="username"
                icon="user"
                placeholder="HAB-7K3M9P, 034 12 345 67…"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Mot de passe ou code personnel')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <flux:error name="formulaire" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('activation.create'))
            <flux:button :href="route('activation.create')" variant="outline" icon="ticket" class="w-full" data-test="activation-link">
                {{ __('Première connexion avec un code d\'activation') }}
            </flux:button>
        @endif

        @if (Route::has('login-link.create'))
            <flux:button :href="route('login-link.create')" variant="outline" icon="envelope" class="w-full" wire:navigate data-test="login-link">
                {{ __('Recevoir un lien de connexion') }}
            </flux:button>
        @endif

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
