@props(['services', 'guide'])

{{-- Services recommandés (F72) : chaque carte mène directement à la démarche du service. --}}
@if ($services->isEmpty())
    <x-tn.empty icon="landmark" title="Aucun service disponible pour le moment" text="Les services de la mairie apparaîtront ici dès qu'ils seront ouverts.">
        <flux:button :href="route('services.index')" wire:navigate>Voir le catalogue des services</flux:button>
    </x-tn.empty>
@else
    <ol {{ $attributes->class('grid gap-3 md:grid-cols-2') }}>
        @foreach ($services as $service)
            <li wire:key="reco-{{ $service->id }}">
                <x-tn.surface class="flex h-full flex-col gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-cyan">{{ $loop->iteration }} · {{ \App\Models\Service::labelCategorie($service->categorie) ?? 'Service municipal' }}</p>
                        <h3 class="mt-1 font-medium text-ink">{{ $service->nom }}</h3>
                        <p class="mt-1 text-sm text-ink-2">{{ $guide->raison($service) }}</p>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <flux:button size="sm" variant="primary" icon="arrow-right" :href="route('demarches.create', ['service' => $service->id])" wire:navigate>
                            Commencer la démarche<span class="sr-only"> : {{ $service->nom }}</span>
                        </flux:button>
                        <flux:button size="sm" variant="ghost" :href="route('services.show', $service)" wire:navigate>
                            Voir le service<span class="sr-only"> {{ $service->nom }}</span>
                        </flux:button>
                    </div>
                </x-tn.surface>
            </li>
        @endforeach
    </ol>
@endif
