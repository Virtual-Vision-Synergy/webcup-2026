@php($user = auth()->user())

<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">Bonjour {{ $user->name }}</flux:heading>
                <flux:text class="mt-1">Bienvenue dans votre espace personnel.</flux:text>
            </div>
            <flux:badge color="lime" icon="user">{{ $user->role->label }}</flux:badge>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <flux:card class="flex flex-col gap-3">
                <flux:heading size="lg">Mon compte</flux:heading>
                <div>
                    <flux:text>Nom</flux:text>
                    <flux:text variant="strong">{{ $user->name }}</flux:text>
                </div>
                <div>
                    <flux:text>Adresse e-mail</flux:text>
                    <flux:text variant="strong" class="break-all">{{ $user->email }}</flux:text>
                </div>
                <div>
                    <flux:text>Membre depuis le</flux:text>
                    <flux:text variant="strong">{{ $user->created_at?->translatedFormat('d F Y') }}</flux:text>
                </div>
                <flux:button :href="route('profile.edit')" icon="cog-6-tooth" size="sm" class="mt-auto" wire:navigate>
                    Modifier mon profil
                </flux:button>
            </flux:card>

            <flux:card class="flex flex-col items-center justify-center gap-2 text-center md:col-span-2">
                <flux:icon.inbox class="size-10 text-zinc-400" />
                <flux:heading size="lg">Rien pour le moment</flux:heading>
                <flux:text>Vos démarches et activités apparaîtront ici.</flux:text>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
