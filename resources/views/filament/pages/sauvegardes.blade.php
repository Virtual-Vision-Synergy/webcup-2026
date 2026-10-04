<x-filament-panels::page>
    @php
        $etat = $this->getEtat();
        $derniere = $this->getDerniere();
        $verification = $this->getDerniereVerification();
    @endphp

    <div wire:poll.30s.visible>
        <x-filament::callout
            :color="$etat['couleur']"
            :icon="match ($etat['niveau']) {
                'ok' => 'heroicon-o-check-circle',
                'attention' => 'heroicon-o-exclamation-triangle',
                default => 'heroicon-o-x-circle',
            }"
            :heading="$etat['libelle']"
            :description="$derniere
                ? 'Dernière sauvegarde le '.$derniere['date'].' ('.$derniere['age'].') · '.$derniere['taille'].' · '.$derniere['nom']
                : 'Aucun fichier de sauvegarde n’a été trouvé sur le serveur.'"
        />
    </div>

    <x-filament::section
        heading="Dernière vérification"
        description="Fichier lisible et non vide, export terminé, tables présentes et lignes des tables importantes comparées à la base actuelle."
    >
        @if ($verification)
            <p>
                <x-filament::badge
                    :color="match ($verification->statut) { 'complete' => 'success', 'incomplete' => 'warning', default => 'danger' }"
                >
                    {{ \App\Models\VerificationSauvegarde::STATUT_LABELS[$verification->statut] ?? $verification->statut }}
                </x-filament::badge>
            </p>

            <p style="margin-top: 0.75rem; font-weight: 600;">{{ $verification->rapport }}</p>

            @if (! empty($verification->details['importantes']))
                <ul style="margin-top: 0.75rem; display: grid; gap: 0.25rem;">
                    @foreach ($verification->details['importantes'] as $ligne)
                        <li>
                            {{ \App\Services\Sauvegardes::nombre($ligne['sauvegarde']) }} {{ $ligne['libelle'] }} dans la sauvegarde
                            · {{ \App\Services\Sauvegardes::nombre($ligne['base']) }} dans la base aujourd’hui
                            @if ($ligne['base'] > $ligne['sauvegarde'])
                                ({{ \App\Services\Sauvegardes::nombre($ligne['base'] - $ligne['sauvegarde']) }} ajouté(s) depuis)
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($verification->details['tables_manquantes']))
                <p style="margin-top: 0.75rem;">
                    Tables absentes de la sauvegarde : {{ implode(', ', $verification->details['tables_manquantes']) }}
                </p>
            @endif

            <p style="margin-top: 0.75rem; opacity: 0.7; font-size: 0.875rem;">
                Vérifiée le {{ $verification->created_at?->format('d/m/Y à H:i') }}
                par {{ $verification->user?->name ?? 'la vérification automatique' }}.
                La restauration ne se fait jamais depuis l’application.
            </p>
        @else
            <p>Aucune vérification n’a encore été faite.</p>
        @endif
    </x-filament::section>

    <x-filament::section heading="Historique des vérifications">
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
