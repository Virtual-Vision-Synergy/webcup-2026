<?php

use App\Models\KnownDevice;
use App\Models\LoginAttempt;
use App\Services\DeviceRecognizer;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F54 : mes appareils et mes connexions récentes (uniquement ceux de l'utilisateur connecté).
 * Retirer un appareil : KnownDevicePolicy::delete (403 pour l'appareil d'un autre).
 */
new #[Title('Mes appareils et connexions récentes')] class extends Component {
    /** Appareil courant (cookie d'appareil), calculé une fois au chargement. */
    #[Locked]
    public ?int $currentDeviceId = null;

    public function mount(DeviceRecognizer $recognizer): void
    {
        $this->authorize('viewAny', KnownDevice::class);

        $this->currentDeviceId = $recognizer->currentDevice(auth()->user(), request())?->id;
    }

    /**
     * @return Collection<int, KnownDevice>
     */
    #[Computed]
    public function devices(): Collection
    {
        return auth()->user()->knownDevices()
            ->orderByRaw('revoked_at is null desc')
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * Dix dernières connexions réussies (journal F37), affichées sans IP complète ni user-agent brut.
     *
     * @return Collection<int, array{date: string, appareil: string, ip: string}>
     */
    #[Computed]
    public function connexions(): Collection
    {
        $recognizer = app(DeviceRecognizer::class);

        return LoginAttempt::query()
            ->where('user_id', auth()->id())
            ->where('successful', true)
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (LoginAttempt $attempt) use ($recognizer): array {
                $appareil = $recognizer->parse($attempt->user_agent);
                $ip = $recognizer->maskIp($attempt->ip);

                return [
                    'date' => KnownDevice::dateLisible($attempt->created_at),
                    'appareil' => $appareil['browser'].' sur '.$appareil['os'].' ('.$appareil['device_type'].')',
                    'ip' => $ip === null ? 'inconnue' : KnownDevice::ipPourAffichage($ip),
                ];
            });
    }

    public function remove(int $deviceId): void
    {
        // Recherche non filtrée puis Policy : l'appareil d'un autre donne 403 (et non une fuite d'existence via 404 distinct).
        $device = KnownDevice::query()->findOrFail($deviceId);
        $this->authorize('delete', $device);

        app(DeviceRecognizer::class)->revoke($device);
        unset($this->devices);

        Flux::toast(variant: 'success', text: 'Appareil retiré : une prochaine connexion depuis celui-ci déclenchera une alerte.');
    }

    public function logoutOthers(): void
    {
        $this->authorize('logoutOthers', KnownDevice::class);

        app(DeviceRecognizer::class)->logoutOtherSessions(auth()->user(), session()->getId());

        Flux::toast(variant: 'success', text: 'Tous les autres appareils ont été déconnectés.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Mes appareils et connexions récentes" subheading="Vérifiez que vous reconnaissez chaque appareil. Sinon, cliquez sur « Ce n’était pas moi ».">
        <div class="space-y-10">
            <section aria-labelledby="titre-appareils" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading level="3" id="titre-appareils">Mes appareils</flux:heading>
                    <flux:button size="sm" icon="arrow-right-start-on-rectangle" wire:click="logoutOthers" wire:loading.attr="disabled"
                        wire:confirm="Déconnecter tous les autres appareils ? Vous resterez connecté ici." data-test="deconnecter-autres">
                        Déconnecter les autres appareils
                    </flux:button>
                </div>

                @if ($this->devices->isEmpty())
                    <x-tn.empty icon="device-phone-mobile" title="Aucun appareil enregistré" text="Vos appareils apparaîtront ici à votre prochaine connexion." />
                @else
                    <ul class="space-y-3">
                        @foreach ($this->devices as $device)
                            <li wire:key="appareil-{{ $device->id }}" data-test="appareil" class="rounded-md border border-line p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :name="$device->device_type === 'ordinateur' ? 'computer-desktop' : 'device-phone-mobile'" class="size-5 text-ink-2" aria-hidden="true" />
                                        <span class="font-medium text-ink">{{ $device->browser }} sur {{ $device->os }}</span>
                                        <span class="text-sm text-ink-2">({{ $device->device_type }})</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($device->id === $currentDeviceId)
                                            <x-tn.status-badge etat="normal">Cet appareil</x-tn.status-badge>
                                        @endif
                                        @if ($device->isRevoked())
                                            <x-tn.status-badge etat="alerte">Révoqué</x-tn.status-badge>
                                        @endif
                                    </div>
                                </div>

                                <dl class="mt-2 grid gap-x-4 gap-y-1 text-sm text-ink-2 sm:grid-cols-2">
                                    <div><dt class="inline">Adresse approximative :</dt> <dd class="inline">{{ $device->ipAffichee() }}</dd></div>
                                    <div><dt class="inline">Première connexion :</dt> <dd class="inline">{{ \App\Models\KnownDevice::dateLisible($device->first_seen_at) }}</dd></div>
                                    <div><dt class="inline">Dernière connexion :</dt> <dd class="inline">{{ \App\Models\KnownDevice::dateLisible($device->last_seen_at) }}</dd></div>
                                </dl>

                                @unless ($device->isRevoked() || $device->id === $currentDeviceId)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <flux:button size="sm" variant="danger" :href="route('profile.devices.confirm', $device)" data-test="pas-moi">Ce n’était pas moi</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="remove({{ $device->id }})" wire:loading.attr="disabled"
                                            wire:confirm="Retirer cet appareil ? Une prochaine connexion depuis celui-ci déclenchera une alerte.">
                                            Retirer cet appareil
                                        </flux:button>
                                    </div>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="titre-connexions" class="space-y-3">
                <flux:heading level="3" id="titre-connexions">Connexions récentes</flux:heading>

                @if ($this->connexions->isEmpty())
                    <flux:text>Aucune connexion enregistrée pour le moment.</flux:text>
                @else
                    <ul class="divide-y divide-line rounded-md border border-line" data-test="connexions">
                        @foreach ($this->connexions as $connexion)
                            <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 px-4 py-2.5 text-sm">
                                <span class="text-ink">{{ $connexion['appareil'] }}</span>
                                <span class="text-ink-2">{{ $connexion['date'] }} · {{ $connexion['ip'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <flux:text size="sm">Heures de Nova Terra.</flux:text>
                @endif
            </section>
        </div>
    </x-pages::settings.layout>
</section>
