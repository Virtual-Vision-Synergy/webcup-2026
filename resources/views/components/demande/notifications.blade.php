{{--
    F49 : avis de changement d'état envoyés pour une démarche ou un signalement.
    Ne lit que les notifications de l'utilisateur connecté (même si un agent ouvre la page) : jamais celles d'un autre.
    Usage : <x-demande.notifications :demande="$record" />
--}}
@props(['demande'])
@use('App\Notifications\StatutDemandeChange')

@php
    $avis = auth()->check() ? StatutDemandeChange::notificationsDe(auth()->user(), $demande) : collect();
@endphp

<x-tn.surface id="avis" class="scroll-mt-24" data-test="avis-demande">
    <x-tn.section-label as="h2" class="mb-4">{{ __('Avis envoyés') }} ({{ $avis->count() }})</x-tn.section-label>

    @if ($avis->isEmpty())
        <flux:text>{{ __('Aucun avis pour cette demande pour l’instant.') }}</flux:text>
    @else
        <ul class="divide-y divide-line">
            @foreach ($avis as $notification)
                @php($data = (array) $notification->data)
                <li wire:key="avis-{{ $notification->id }}" class="space-y-1 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <time datetime="{{ $notification->created_at?->toIso8601String() }}" class="font-mono text-xs text-ink-2">
                            {{ $notification->created_at?->translatedFormat('j F Y, H:i') }}
                        </time>
                        <x-tn.status-badge :etat="$notification->read_at ? 'normal' : 'info'">
                            {{ $notification->read_at ? __('Lu') : __('Non lu') }}
                        </x-tn.status-badge>
                    </div>
                    <p class="font-medium text-ink">{{ StatutDemandeChange::sujetDepuis($data) }}</p>
                    <p class="text-sm text-ink-2">{{ StatutDemandeChange::ligneQuoiFaire((string) ($data['demande_type'] ?? ''), (string) ($data['statut_apres'] ?? '')) }}</p>
                    @if (in_array($data['statut_apres'] ?? null, StatutDemandeChange::ETATS_CONTACT, true))
                        <flux:link :href="route('messages.create')" wire:navigate class="text-sm">{{ __('Écrire à la mairie') }}</flux:link>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-tn.surface>
