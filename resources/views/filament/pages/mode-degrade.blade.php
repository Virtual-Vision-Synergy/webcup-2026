<x-filament-panels::page>
    <x-filament::callout
        :color="$this->estActif() ? 'warning' : 'success'"
        :icon="$this->estActif() ? 'heroicon-o-bolt' : 'heroicon-o-check-circle'"
        :heading="$this->estActif() ? 'Mode dégradé actif' : 'Fonctionnement normal'"
        :description="$this->estImposeParEnvironnement()
            ? 'Imposé par la variable d’environnement MODE_DEGRADE (à changer dans Hodifly → Variables).'
            : ($this->estActif() ? 'Activé depuis cette page.' : 'Activez-le si le serveur est surchargé.')"
    />

    <x-filament::section heading="Ce que change le mode dégradé">
        {{-- Styles en ligne : le CSS précompilé de Filament ne contient pas les utilitaires Tailwind du site. --}}
        <ul style="list-style: disc; padding-inline-start: 1.25rem; display: grid; gap: 0.25rem; font-size: 0.875rem;">
            <li>Mode allégé imposé à tous : pas d’images décoratives, de flou ni d’animations.</li>
            <li>Bandeau « Service en mode allégé » affiché à tous les habitants.</li>
            <li>Rafraîchissements automatiques 4 fois moins fréquents (moins de requêtes sur le serveur).</li>
            <li>Pages publiques (accueil, projets, idées, partenaires, urgences…) gardées 5 minutes en cache par le navigateur des visiteurs.</li>
            <li>Les parcours essentiels restent identiques : connexion, démarches, signalements, suivi des dossiers.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
