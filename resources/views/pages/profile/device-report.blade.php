<x-layouts::auth title="Ce n’était pas moi">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Ce n’était pas vous ?" description="Nous allons sécuriser votre compte." />

        <div class="rounded-md border border-line bg-surface-2 p-4 text-sm text-ink-2" data-test="appareil-signale">
            <p class="font-medium text-ink">{{ $device->libelleAppareil() }}</p>
            <p>Adresse approximative : {{ $device->ipAffichee() }}</p>
            <p>Première connexion : {{ \App\Models\KnownDevice::dateLisible($device->first_seen_at) }} (heure de Nova Terra)</p>
        </div>

        <ul class="list-disc space-y-1 ps-5 text-sm text-ink-2">
            <li>cet appareil ne sera plus reconnu ;</li>
            <li>tous les autres appareils connectés à votre compte seront déconnectés ;</li>
            <li>vous serez ensuite invité à changer votre mot de passe.</li>
        </ul>

        {{-- L'action n'a lieu qu'au clic (POST) : un logiciel qui ouvre automatiquement les liens ne déclenche rien. --}}
        <form method="POST" action="{{ $action }}" class="flex flex-col gap-6">
            @csrf

            <flux:button variant="danger" type="submit" class="w-full" data-test="pas-moi-confirmer">
                Déconnecter les autres appareils
            </flux:button>
        </form>

        <div class="text-center text-sm">
            <flux:link :href="auth()->check() ? route('profile.devices.index') : route('login')">Annuler, c’était bien moi</flux:link>
        </div>
    </div>
</x-layouts::auth>
