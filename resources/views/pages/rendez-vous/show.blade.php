<?php

use App\Concerns\ThrottlesPerUser;
use App\Models\RendezVous;
use App\Services\PriseDeRendezVous;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fiche d'un rendez-vous (F39), aussi page de confirmation juste après la réservation.
 * Visible uniquement par son propriétaire (RendezVousPolicy::view, sinon 403).
 */
new #[Title('Mon rendez-vous')] class extends Component {
    use ThrottlesPerUser;

    #[Locked]
    public RendezVous $record;

    #[Locked]
    public bool $vientDEtreConfirme = false;

    public function mount(RendezVous $rendezVous): void
    {
        $this->authorize('view', $rendezVous);
        $this->record = $rendezVous->loadMissing(['service', 'creneau']);
        $this->vientDEtreConfirme = (bool) session('rendez-vous-confirme', false);
    }

    public function annuler(): void
    {
        $this->authorize('cancel', $this->record);
        $this->throttlePerUser('rendez-vous-annulation', maxAttempts: 10, decaySeconds: 60);

        app(PriseDeRendezVous::class)->annuler($this->record);

        $this->vientDEtreConfirme = false;
        $this->record->refresh()->load(['service', 'creneau']);

        Flux::toast(variant: 'success', text: 'Rendez-vous annulé. Le créneau est de nouveau proposé aux autres habitants.');
    }

    /**
     * Fichier .ics « Ajouter à mon agenda » (heures en UTC, converties par l'agenda du téléphone).
     */
    public function telechargerAgenda(): StreamedResponse
    {
        $this->authorize('view', $this->record);

        $echapper = fn (string $texte): string => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $texte);
        $service = $this->record->service;
        $creneau = $this->record->creneau;
        $pieces = $service->piecesAFournir();

        $description = 'Rendez-vous '.$service->nom.'.'
            .($pieces === [] ? '' : "\nPensez à apporter : ".implode(', ', $pieces).'.');

        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mairie de Nova Terra//Rendez-vous//FR',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:rendez-vous-'.$this->record->id.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$creneau->debut->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$creneau->fin->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$echapper('Rendez-vous : '.$service->nom),
            'LOCATION:'.$echapper((string) $service->lieuRendezVous()),
            'DESCRIPTION:'.$echapper($description),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response()->streamDownload(
            function () use ($lignes): void {
                echo implode("\r\n", $lignes)."\r\n";
            },
            'rendez-vous-'.Str::slug($service->nom).'.ics',
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }
}; ?>

<section class="mx-auto w-full max-w-3xl space-y-6">
    <x-tn.page-header
        label="Rendez-vous"
        :title="$vientDEtreConfirme ? 'Rendez-vous confirmé' : 'Rendez-vous : '.$record->service->nom"
        :subtitle="$record->creneau->libelleComplet()"
        :breadcrumb="['Mes rendez-vous' => route('appointments.index'), $record->service->nom => null]"
    >
        <x-slot:meta>
            <div class="mt-3">
                <x-tn.status-badge :etat="$record->etatStatut()">{{ RendezVous::libelleStatut($record->statut) }}</x-tn.status-badge>
            </div>
        </x-slot:meta>
    </x-tn.page-header>

    @if ($vientDEtreConfirme)
        <flux:callout variant="success" icon="check-circle" role="status">
            <flux:callout.heading>Votre rendez-vous est enregistré.</flux:callout.heading>
            <flux:callout.text>Un agent vous attend {{ Str::lcfirst($record->creneau->libelleComplet()) }}.</flux:callout.text>
        </flux:callout>
    @endif

    @if ($record->statut === 'annule')
        <flux:callout variant="warning" icon="x-circle">
            <flux:callout.text>
                @php $annuleLocal = $record->annule_le?->setTimezone(\App\Models\CreneauRendezVous::fuseau())->locale('fr'); @endphp
                Ce rendez-vous a été annulé{{ $annuleLocal ? ' le '.$annuleLocal->translatedFormat('j F Y').' à '.$annuleLocal->format('H \h i') : '' }}.
            </flux:callout.text>
        </flux:callout>
    @endif

    <x-tn.surface>
        <x-rdv.recapitulatif :service="$record->service" :creneau="$record->creneau" :motif="$record->motif" />
    </x-tn.surface>

    @if ($record->estConfirme() && $record->service->piecesAFournir() !== [])
        <flux:callout icon="document-text">
            <flux:callout.text>
                <strong>Pensez à apporter :</strong> {{ implode(', ', $record->service->piecesAFournir()) }}.
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if ($record->estConfirme() && $record->estAVenir())
            <flux:button icon="calendar" wire:click="telechargerAgenda">Ajouter à mon agenda (.ics)</flux:button>
        @endif

        @can('cancel', $record)
            <flux:button
                variant="danger"
                icon="x-mark"
                wire:click="annuler"
                wire:confirm="Voulez-vous vraiment annuler ce rendez-vous du {{ Str::lcfirst($record->creneau->libelleDate()) }} à {{ $record->creneau->libelleHeureDebut() }} ?"
            >
                Annuler le rendez-vous
            </flux:button>
        @elseif ($record->estConfirme() && $record->estAVenir())
            <flux:text class="text-sm">Ce rendez-vous commence bientôt : il ne peut plus être annulé en ligne. Contactez le service.</flux:text>
        @endif

        <flux:button variant="ghost" :href="route('appointments.index')" wire:navigate>Mes rendez-vous</flux:button>
    </div>
</section>
