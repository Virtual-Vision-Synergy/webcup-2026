<?php

use App\Concerns\ExportsCsv;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\DonneesPersonnelles;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * F55 : « Télécharger mes données ». Document lisible (imprimable / PDF) avec un résumé,
 * plus les mêmes données en JSON et en CSV. Toujours celles de l'utilisateur connecté (aucun ID dans l'URL),
 * vérifié par UserPolicy::exportPersonalData. Chaque export est tracé dans le journal (ActionLog).
 */
new #[Layout('layouts::imprimable'), Title('Mes données personnelles')] class extends Component {
    use ExportsCsv;

    public function mount(): void
    {
        $this->authorize('exportPersonalData', $this->utilisateur());

        ActionLog::record('export_donnees_document', $this->utilisateur());
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function donnees(): array
    {
        return DonneesPersonnelles::pour($this->utilisateur())->tout();
    }

    public function telechargerJson(): StreamedResponse
    {
        $this->authorize('exportPersonalData', $this->utilisateur());
        ActionLog::record('export_donnees_json', $this->utilisateur());

        $json = (string) json_encode($this->donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($json): void {
            echo $json;
        }, 'mes-donnees-'.now()->format('Ymd').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public function telechargerCsv(): StreamedResponse
    {
        $this->authorize('exportPersonalData', $this->utilisateur());
        ActionLog::record('export_donnees_csv', $this->utilisateur());

        $lignes = $this->lignesCsv($this->donnees);

        return response()->streamDownload(function () use ($lignes): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $this->writeCsvRow($out, ['Rubrique', 'Élément', 'Détail', 'État', 'Date']);

            foreach ($lignes as $ligne) {
                $this->writeCsvRow($out, $ligne);
            }

            fclose($out);
        }, 'mes-donnees-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Mêmes données que le JSON, à plat : une ligne par élément (Rubrique ; Élément ; Détail ; État ; Date).
     *
     * @param  array<string, mixed>  $d
     * @return list<array<int, mixed>>
     */
    private function lignesCsv(array $d): array
    {
        $lignes = [];

        foreach ($d['resume'] as $phrase) {
            $lignes[] = ['Résumé', $phrase, '', '', ''];
        }

        $libellesProfil = [
            'nom' => 'Nom', 'email' => 'E-mail', 'identifiant' => 'Identifiant', 'telephone' => 'Téléphone',
            'quartier' => 'Quartier', 'role' => 'Rôle', 'langue' => 'Langue', 'alertes_par_email' => 'Alertes par e-mail',
            'double_authentification' => 'Double authentification', 'email_verifie' => 'E-mail vérifié', 'compte_cree_le' => 'Compte créé le',
        ];
        foreach ($d['profil'] as $cle => $valeur) {
            $lignes[] = ['Profil', $libellesProfil[$cle] ?? $cle, $valeur, '', ''];
        }

        foreach (['demarches' => 'Démarches', 'signalements' => 'Signalements', 'remontees' => 'Remontées'] as $cle => $rubrique) {
            foreach ($d[$cle]['par_etat'] as $etat => $nombre) {
                $lignes[] = [$rubrique.' (par état)', $etat, $nombre, '', ''];
            }
        }

        foreach ($d['demarches']['liste'] as $x) {
            $lignes[] = ['Démarche', $x['reference'].' · '.$x['titre'], $x['service'] ?? 'Service non précisé', $x['etat'], $x['deposee_le']];
        }
        foreach ($d['signalements']['liste'] as $x) {
            $lignes[] = ['Signalement', $x['reference'].' · '.$x['categorie'], $x['lieu'].' ('.$x['soutiens_recus'].' soutien(s) reçu(s))', $x['etat'], $x['signale_le']];
        }
        foreach ($d['soutiens'] as $x) {
            $lignes[] = ['Soutien', $x['signalement'], $x['lieu'], $x['etat'], $x['soutenu_le']];
        }
        foreach ($d['remontees']['liste'] as $x) {
            $lignes[] = ['Remontée', $x['reference'].' · '.$x['objet'], $x['categorie'], $x['etat'], $x['envoyee_le']];
        }
        foreach ($d['rendez_vous'] as $x) {
            $lignes[] = ['Rendez-vous', $x['service'], $x['motif'], $x['etat'], $x['date']];
        }
        foreach ($d['avis_projets'] as $x) {
            $lignes[] = ['Avis sur un projet', $x['projet'], $x['commentaire'], $x['position'], $x['donne_le']];
        }
        foreach ($d['messages'] as $x) {
            $lignes[] = ['Message', $x['sujet'], '', '', $x['envoye_le']];
        }
        foreach ($d['connexions_recentes'] as $x) {
            $lignes[] = ['Connexion', $x['adresse_ip'], $x['navigateur'], $x['resultat'], $x['date']];
        }
        foreach ($d['appareils'] as $x) {
            $lignes[] = ['Appareil', $x['appareil'], $x['adresse_approx'], $x['revoque'] ? 'Révoqué' : 'Actif', $x['derniere_connexion']];
        }

        return $lignes;
    }

    private function utilisateur(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}; ?>

<div class="space-y-8 text-zinc-900">
    {{-- Toujours un bloc php fermé par endphp dans ce fichier : la forme en ligne avale le bloc $rubriques plus bas. --}}
    @php
        $d = $this->donnees;
    @endphp

    {{-- Barre d'actions (masquée à l'impression) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-cyan underline underline-offset-2">← Retour à mon profil</a>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.print()" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-on-cyan hover:opacity-90">
                Imprimer / Enregistrer en PDF
            </button>
            <button type="button" wire:click="telechargerJson" wire:loading.attr="disabled" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                <span wire:loading.remove wire:target="telechargerJson">Télécharger en JSON</span>
                <span wire:loading wire:target="telechargerJson">Préparation…</span>
            </button>
            <button type="button" wire:click="telechargerCsv" wire:loading.attr="disabled" class="rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                <span wire:loading.remove wire:target="telechargerCsv">Télécharger en CSV</span>
                <span wire:loading wire:target="telechargerCsv">Préparation…</span>
            </button>
        </div>
    </div>

    {{-- En-tête du document --}}
    <header class="border-b-2 border-zinc-900 pb-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Mairie de Nova Terra · Terra Nova</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Mes données personnelles</h1>
        <p class="mt-2 text-sm text-zinc-600">
            {{ $d['profil']['nom'] }} · édité le {{ now()->timezone(\App\Models\KnownDevice::FUSEAU)->format('d/m/Y à H:i') }}
        </p>
    </header>

    {{-- Résumé --}}
    <section aria-labelledby="resume" class="space-y-4">
        <h2 id="resume" class="text-lg font-semibold">Ce qu'il faut retenir</h2>
        <ul class="list-disc space-y-1 rounded-md bg-zinc-100 p-4 ps-8 text-sm leading-relaxed print:border print:border-zinc-300 print:bg-white">
            @foreach ($d['resume'] as $phrase)
                <li>{{ $phrase }}</li>
            @endforeach
        </ul>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4 print:grid-cols-4">
            @foreach ([
                'Démarches' => count($d['demarches']['liste']),
                'Signalements' => count($d['signalements']['liste']),
                'Soutiens' => count($d['soutiens']),
                'Remontées' => count($d['remontees']['liste']),
                'Rendez-vous' => count($d['rendez_vous']),
                'Avis sur des projets' => count($d['avis_projets']),
                'Messages' => count($d['messages']),
                'Appareils connus' => count($d['appareils']),
            ] as $libelle => $nombre)
                <div class="rounded-md border border-zinc-300 p-3">
                    <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ $libelle }}</dt>
                    <dd class="text-2xl font-bold">{{ $nombre }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Profil --}}
    <section aria-labelledby="profil" class="space-y-3 break-inside-avoid">
        <h2 id="profil" class="text-lg font-semibold">Profil</h2>
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 print:grid-cols-2">
            @foreach ([
                'Nom' => $d['profil']['nom'],
                'E-mail' => $d['profil']['email'] ?? 'Aucun (compte sans e-mail)',
                'Identifiant' => $d['profil']['identifiant'] ?? '—',
                'Téléphone' => $d['profil']['telephone'] ?? 'Non renseigné',
                'Quartier' => $d['profil']['quartier'] ?? 'Non renseigné',
                'Rôle' => $d['profil']['role'] ?? '—',
                'Alertes par e-mail' => $d['profil']['alertes_par_email'] ? 'Oui' : 'Non',
                'Double authentification' => $d['profil']['double_authentification'] ? 'Activée' : 'Non activée',
                'Compte créé le' => $d['profil']['compte_cree_le'] ?? '—',
            ] as $libelle => $valeur)
                <div class="flex justify-between gap-3 border-b border-zinc-200 py-1">
                    <dt class="text-zinc-500">{{ $libelle }}</dt>
                    <dd class="text-end font-medium">{{ $valeur }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Demandes par état --}}
    <section aria-labelledby="etats" class="space-y-3 break-inside-avoid">
        <h2 id="etats" class="text-lg font-semibold">Où en sont mes demandes ?</h2>
        <div class="grid gap-4 sm:grid-cols-3 print:grid-cols-3">
            @foreach (['demarches' => 'Démarches', 'signalements' => 'Signalements', 'remontees' => 'Remontées sur mes données'] as $cle => $titre)
                <div class="rounded-md border border-zinc-300 p-3">
                    <h3 class="text-sm font-semibold">{{ $titre }}</h3>
                    <dl class="mt-2 space-y-1 text-sm">
                        @foreach ($d[$cle]['par_etat'] as $etat => $nombre)
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ $etat }}</dt><dd class="font-semibold">{{ $nombre }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Détail par rubrique --}}
    @php
        $rubriques = [
            ['Mes démarches', $d['demarches']['liste'], fn ($x) => [$x['reference'].' · '.$x['titre'], $x['service'] ?? 'Service non précisé', $x['etat'], $x['deposee_le']]],
            ['Mes signalements', $d['signalements']['liste'], fn ($x) => [$x['reference'].' · '.$x['categorie'], $x['lieu'].' · '.$x['soutiens_recus'].' soutien(s) reçu(s)', $x['etat'], $x['signale_le']]],
            ['Signalements que je soutiens', $d['soutiens'], fn ($x) => [$x['signalement'], $x['lieu'] ?? '—', $x['etat'] ?? '—', $x['soutenu_le']]],
            ['Mes remontées', $d['remontees']['liste'], fn ($x) => [$x['reference'].' · '.$x['objet'], $x['categorie'], $x['etat'], $x['envoyee_le']]],
            ['Mes rendez-vous', $d['rendez_vous'], fn ($x) => [$x['service'] ?? '—', $x['motif'] ?? '—', $x['etat'], $x['date']]],
            ['Mes avis sur les projets', $d['avis_projets'], fn ($x) => [$x['projet'], $x['commentaire'] ?? '—', $x['position'], $x['donne_le']]],
            ['Mes messages à la mairie', $d['messages'], fn ($x) => [$x['sujet'], '', '', $x['envoye_le']]],
            ['Connexions récentes', $d['connexions_recentes'], fn ($x) => [$x['adresse_ip'] ?? 'Adresse inconnue', $x['navigateur'] ?? '—', $x['resultat'], $x['date']]],
            ['Mes appareils', $d['appareils'], fn ($x) => [$x['appareil'], 'Adresse ≈ '.$x['adresse_approx'], $x['revoque'] ? 'Révoqué' : 'Actif', $x['derniere_connexion']]],
        ];
    @endphp

    @foreach ($rubriques as [$titre, $elements, $colonnes])
        <section class="space-y-2" wire:key="rubrique-{{ $loop->index }}">
            <h2 class="text-lg font-semibold">{{ $titre }} <span class="text-sm font-normal text-zinc-500">({{ count($elements) }})</span></h2>

            @if (count($elements) === 0)
                <p class="rounded-md border border-dashed border-zinc-300 p-3 text-sm text-zinc-600">Rien à signaler dans cette rubrique.</p>
            @else
                <ul class="divide-y divide-zinc-200 rounded-md border border-zinc-300 text-sm">
                    @foreach ($elements as $element)
                        @php
                            [$principal, $detail, $etat, $date] = $colonnes($element);
                        @endphp
                        <li class="flex flex-col gap-1 p-3 break-inside-avoid sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-medium break-words">{{ $principal }}</p>
                                @if ($detail !== '')
                                    <p class="text-xs break-words text-zinc-500">{{ $detail }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-xs sm:text-end">
                                @if ($etat !== '')
                                    <p class="font-semibold">{{ $etat }}</p>
                                @endif
                                <p class="text-zinc-500">{{ $date ?? '—' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach

    <footer class="border-t border-zinc-300 pt-3 text-xs text-zinc-500">
        Document personnel généré depuis Terra Nova. Il ne contient que vos propres données.
        Une question ou une correction ? Utilisez « Faire remonter une inquiétude » depuis la page « Vos données ».
    </footer>
</div>
