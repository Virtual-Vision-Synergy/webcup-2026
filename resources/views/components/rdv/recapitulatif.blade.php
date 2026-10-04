@props([
    'service',
    'creneau',
    'motif' => null,
])

{{-- Récapitulatif d'un rendez-vous (F39) : avant confirmation, sur la confirmation et sur la fiche. --}}
<dl {{ $attributes }}>
    <x-tn.field label="Service">{{ $service->nom }}</x-tn.field>
    <x-tn.field label="Date">{{ $creneau->libelleDate() }}</x-tn.field>
    <x-tn.field label="Heure">
        De {{ $creneau->libelleHeureDebut() }} à {{ $creneau->libelleHeureFin() }}
        <span class="block text-sm text-ink-2">Fuseau : {{ config('rendez_vous.libelle_fuseau') }} ({{ $creneau->debut->setTimezone(\App\Models\CreneauRendezVous::fuseau())->format('\U\T\CP') }})</span>
    </x-tn.field>
    <x-tn.field label="Lieu">{{ $service->lieuRendezVous() ?? 'Lieu communiqué par le service' }}</x-tn.field>
    <x-tn.field label="Pièces à apporter">
        @if ($service->piecesAFournir() === [])
            Aucune pièce particulière.
        @else
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($service->piecesAFournir() as $piece)
                    <li>{{ $piece }}</li>
                @endforeach
            </ul>
        @endif
    </x-tn.field>
    @if (filled($motif))
        <x-tn.field label="Motif"><p class="whitespace-pre-line">{{ $motif }}</p></x-tn.field>
    @endif
</dl>
