<x-filament-panels::page>
    @php($consigne = $this->consigne())

    <x-filament::callout
        :color="$consigne ? ($consigne['niveau'] === 'alerte' ? 'danger' : 'info') : 'success'"
        :icon="$consigne ? 'heroicon-o-megaphone' : 'heroicon-o-check-circle'"
        :heading="$consigne ? 'Consigne en cours : '.$consigne['titre'] : 'Aucune consigne d’incident publiée'"
        :description="$consigne ? $consigne['message'] : 'Publiez une consigne si la plateforme ou un service est touché par un incident.'"
    />

    <x-filament::section heading="Comment fonctionne la page « Infos essentielles »">
        {{-- Styles en ligne : le CSS précompilé de Filament ne contient pas les utilitaires Tailwind du site. --}}
        <ul style="list-style: disc; padding-inline-start: 1.25rem; display: grid; gap: 0.25rem; font-size: 0.875rem;">
            <li>Page publique et ultra-légère (HTML simple, sans JavaScript ni image) : consigne en cours, numéros d’urgence, coordonnées et horaires de la mairie, état des services.</li>
            <li>Version statique régénérée à chaque modification (consigne, état d’un service, interruption) : elle reste servie même si la base de données est indisponible.</li>
            <li>Liens depuis le bandeau de consigne, les pages d’erreur et la page hors ligne ; gardée en cache par le navigateur (service worker).</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
