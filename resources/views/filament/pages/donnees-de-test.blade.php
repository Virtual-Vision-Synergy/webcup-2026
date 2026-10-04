<x-filament-panels::page>
    <x-filament::callout
        color="danger"
        icon="heroicon-o-exclamation-triangle"
        heading="Opération irréversible (hors sauvegarde)"
        description="Toutes les données saisies (y compris par le jury) sont effacées et remplacées par le jeu de données de test. Une sauvegarde est faite juste avant ; si elle échoue, rien n’est effacé."
    />

    <x-filament::section heading="Ce qui se passe">
        {{-- Styles en ligne : le CSS précompilé de Filament ne contient pas les utilitaires Tailwind du site. --}}
        <ul style="list-style: disc; padding-inline-start: 1.25rem; display: grid; gap: 0.25rem; font-size: 0.875rem;">
            <li>Effacé puis recréé : services, démarches, signalements, rendez-vous, annonces, actualités, messages, projets, idées, avis, notifications, journaux…</li>
            <li>Conservés : tous les comptes, les rôles, les quartiers, les sessions (personne n’est déconnecté).</li>
            <li>Comptes de démo (@example.com, hors production) : remis dans leur état d’origine, mot de passe <code>password</code>. Votre propre compte n’est pas modifié.</li>
            <li>En cas d’erreur pendant l’opération, la base reste exactement comme avant.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
