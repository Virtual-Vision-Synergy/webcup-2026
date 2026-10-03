@php
    $user = auth()->user();
    // Uniquement les démarches de l'utilisateur connecté (jamais d'ID venant du navigateur).
    $demarches = $user->demarches()->with('service')->latest()->limit(5)->get();
    $totalDemarches = $user->demarches()->count();
@endphp

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

            @if ($demarches->isEmpty())
                <flux:card class="flex flex-col items-center justify-center gap-2 text-center md:col-span-2">
                    <flux:icon.inbox class="size-10 text-zinc-400" />
                    <flux:heading size="lg">Aucune démarche pour le moment</flux:heading>
                    <flux:text>Vos démarches et leur suivi apparaîtront ici.</flux:text>
                    <flux:button variant="primary" icon="plus" :href="route('demarches.create')" size="sm" class="mt-2" wire:navigate>
                        Déposer une démarche
                    </flux:button>
                </flux:card>
            @else
                <flux:card class="flex flex-col gap-4 md:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <flux:heading size="lg">Mes démarches</flux:heading>
                            <flux:text>{{ $totalDemarches }} démarche(s) déposée(s)</flux:text>
                        </div>
                        <flux:button variant="primary" icon="plus" :href="route('demarches.create')" size="sm" wire:navigate>
                            Nouvelle démarche
                        </flux:button>
                    </div>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($demarches as $demarche)
                            <li wire:key="demarche-{{ $demarche->id }}" class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <div class="min-w-0">
                                    <flux:link :href="route('demarches.show', $demarche)" class="font-medium" wire:navigate>{{ $demarche->titre }}</flux:link>
                                    <flux:text class="truncate">
                                        {{ $demarche->service?->nom ?? 'Service non précisé' }} · {{ $demarche->created_at->format('d/m/Y') }}
                                    </flux:text>
                                </div>
                                <flux:badge size="sm" :color="$demarche->couleurStatut()" class="self-start sm:self-center">
                                    {{ \App\Models\Demarche::libelleStatut($demarche->statut) }}
                                </flux:badge>
                            </li>
                        @endforeach
                    </ul>

                    <flux:link :href="route('demarches.index')" class="text-sm" wire:navigate>Voir toutes mes démarches &rarr;</flux:link>
                </flux:card>
            @endif
        </div>
    </div>
</x-layouts::app>
