<?php

use App\Concerns\ExportsCsv;
use App\Models\Demarche;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * F56 : récapitulatif téléchargeable des demandes de l'habitant connecté.
 * Page imprimable (Imprimer → Enregistrer en PDF) + export CSV.
 * Toujours limité aux demandes de l'utilisateur connecté, quel que soit son rôle.
 */
new #[Layout('layouts::imprimable'), Title('Récapitulatif de mes demandes')] class extends Component {
    use ExportsCsv;

    /** Statuts considérés comme terminés (le délai de traitement se mesure sur ceux-là). */
    private const STATUTS_TERMINES = ['traitee', 'refusee'];

    public function mount(): void
    {
        $this->authorize('viewAny', Demarche::class);
    }

    /**
     * Demandes de l'utilisateur connecté, de la plus récente à la plus ancienne.
     *
     * @return Collection<int, Demarche>
     */
    #[Computed]
    public function demandes(): Collection
    {
        return auth()->user()->demarches()
            ->with('service')
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * Nombre de demandes par statut (tous les statuts, même à zéro).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function parStatut(): array
    {
        $compte = $this->demandes->countBy('statut');

        return collect(Demarche::STATUT_OPTIONS)
            ->mapWithKeys(fn (string $statut): array => [$statut => (int) ($compte[$statut] ?? 0)])
            ->all();
    }

    /**
     * Délai moyen de traitement (en jours) des demandes terminées : du dépôt à la dernière mise à jour.
     */
    #[Computed]
    public function delaiMoyenJours(): ?float
    {
        $terminees = $this->demandes->whereIn('statut', self::STATUTS_TERMINES);

        if ($terminees->isEmpty()) {
            return null;
        }

        return round($terminees->avg(fn (Demarche $d): float => $d->created_at->diffInHours($d->updated_at) / 24), 1);
    }

    /**
     * Phrase de synthèse lisible pour le demandeur.
     */
    #[Computed]
    public function synthese(): string
    {
        $total = $this->demandes->count();

        if ($total === 0) {
            return "Vous n'avez encore déposé aucune demande.";
        }

        $enAttente = $this->parStatut['deposee'] + $this->parStatut['en_cours'];
        $phrase = "Vous avez déposé {$total} demande(s). ";
        $phrase .= $enAttente > 0
            ? "{$enAttente} sont encore en attente de réponse de la mairie."
            : 'Toutes ont reçu une réponse de la mairie.';

        if ($this->delaiMoyenJours !== null) {
            $phrase .= ' Délai moyen de traitement : '.$this->formatDelai($this->delaiMoyenJours).'.';
        }

        return $phrase;
    }

    public function formatDelai(float $jours): string
    {
        if ($jours < 1) {
            return 'moins d’un jour';
        }

        return str_replace('.', ',', (string) $jours).' jour'.($jours >= 2 ? 's' : '');
    }

    /**
     * Export CSV des demandes de l'utilisateur connecté uniquement.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Demarche::class);

        return $this->streamCsv('viewAny', Demarche::class, auth()->user()->demarches()->with('service')->getQuery(), [
            'Référence' => fn (Demarche $d) => '#'.$d->id,
            'Titre' => fn (Demarche $d) => $d->titre,
            'Service' => fn (Demarche $d) => $d->service?->nom ?? 'Non précisé',
            'Déposée le' => fn (Demarche $d) => $d->created_at,
            'Dernière mise à jour' => fn (Demarche $d) => $d->updated_at,
            'État actuel' => fn (Demarche $d) => Demarche::libelleStatut($d->statut),
            'Délai de traitement (jours)' => fn (Demarche $d) => in_array($d->statut, self::STATUTS_TERMINES, true)
                ? str_replace('.', ',', (string) round($d->created_at->diffInHours($d->updated_at) / 24, 1))
                : 'En attente',
        ], 'recapitulatif-mes-demandes');
    }
}; ?>

<div class="space-y-8 text-zinc-900">
    {{-- Barre d'actions (masquée à l'impression) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('demarches.historique') }}" class="text-sm font-medium text-cyan underline underline-offset-2">← Retour à mes demandes</a>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.print()" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-on-cyan hover:opacity-90">
                Imprimer / Enregistrer en PDF
            </button>
            <button type="button" wire:click="export" wire:loading.attr="disabled" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                <span wire:loading.remove wire:target="export">Télécharger en CSV</span>
                <span wire:loading wire:target="export">Préparation…</span>
            </button>
        </div>
    </div>

    {{-- En-tête du document --}}
    <header class="border-b-2 border-zinc-900 pb-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Mairie de Nova Terra · Terra Nova</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Récapitulatif de mes demandes</h1>
        <p class="mt-2 text-sm text-zinc-600">
            {{ auth()->user()->name }} · édité le {{ now()->format('d/m/Y à H:i') }}
        </p>
    </header>

    {{-- Synthèse --}}
    <section aria-labelledby="synthese" class="space-y-4">
        <h2 id="synthese" class="text-lg font-semibold">Synthèse</h2>
        <p class="rounded-md bg-zinc-100 p-4 text-sm leading-relaxed print:border print:border-zinc-300 print:bg-white">{{ $this->synthese }}</p>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 print:grid-cols-3">
            <div class="rounded-md border border-zinc-300 p-3">
                <dt class="text-xs uppercase tracking-wide text-zinc-500">Total</dt>
                <dd class="text-2xl font-bold">{{ $this->demandes->count() }}</dd>
            </div>
            <div class="rounded-md border border-zinc-300 p-3">
                <dt class="text-xs uppercase tracking-wide text-zinc-500">Délai moyen de traitement</dt>
                <dd class="text-2xl font-bold">{{ $this->delaiMoyenJours === null ? '—' : $this->formatDelai($this->delaiMoyenJours) }}</dd>
            </div>
            @foreach ($this->parStatut as $statut => $nombre)
                <div class="rounded-md border border-zinc-300 p-3">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ Demarche::libelleStatut($statut) }}</dt>
                    <dd class="text-2xl font-bold">{{ $nombre }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($this->delaiMoyenJours !== null)
            <p class="text-xs text-zinc-500">Délai moyen calculé sur les demandes traitées ou refusées, du dépôt à la réponse.</p>
        @endif
    </section>

    {{-- Liste détaillée --}}
    <section aria-labelledby="liste" class="space-y-3">
        <h2 id="liste" class="text-lg font-semibold">Détail des demandes</h2>

        @if ($this->demandes->isEmpty())
            <div class="rounded-md border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-600">
                Aucune demande pour le moment.
                <a href="{{ route('demarches.create') }}" class="font-medium text-cyan underline print:hidden">Déposer une démarche</a>
            </div>
        @else
            {{-- Tableau (écran large et impression) --}}
            <div class="hidden overflow-x-auto sm:block print:block">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b-2 border-zinc-900">
                            <th scope="col" class="py-2 pe-3">Réf.</th>
                            <th scope="col" class="py-2 pe-3">Demande</th>
                            <th scope="col" class="py-2 pe-3">Déposée le</th>
                            <th scope="col" class="py-2 pe-3">Mise à jour</th>
                            <th scope="col" class="py-2">État actuel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->demandes as $demande)
                            <tr wire:key="r-{{ $demande->id }}" class="border-b border-zinc-200 align-top break-inside-avoid">
                                <td class="py-2 pe-3 font-mono text-xs text-zinc-500">#{{ $demande->id }}</td>
                                <td class="py-2 pe-3">
                                    <span class="font-medium">{{ $demande->titre }}</span>
                                    <span class="block text-xs text-zinc-500">{{ $demande->service?->nom ?? 'Service non précisé' }}</span>
                                </td>
                                <td class="py-2 pe-3 whitespace-nowrap">{{ $demande->created_at->format('d/m/Y') }}</td>
                                <td class="py-2 pe-3 whitespace-nowrap">{{ $demande->updated_at->format('d/m/Y') }}</td>
                                <td class="py-2 whitespace-nowrap font-semibold">{{ Demarche::libelleStatut($demande->statut) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Cartes (téléphone) --}}
            <ul class="space-y-2 sm:hidden print:hidden">
                @foreach ($this->demandes as $demande)
                    <li wire:key="m-{{ $demande->id }}" class="rounded-md border border-zinc-300 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-medium">{{ $demande->titre }}</span>
                            <span class="shrink-0 text-sm font-semibold">{{ Demarche::libelleStatut($demande->statut) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">
                            #{{ $demande->id }} · {{ $demande->service?->nom ?? 'Service non précisé' }}<br>
                            Déposée le {{ $demande->created_at->format('d/m/Y') }} · mise à jour le {{ $demande->updated_at->format('d/m/Y') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <footer class="border-t border-zinc-300 pt-3 text-xs text-zinc-500">
        Document personnel généré depuis Terra Nova. Il ne contient que vos propres demandes.
    </footer>
</div>
