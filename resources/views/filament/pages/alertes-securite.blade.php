<x-filament-panels::page>
    <x-filament::section>
        <p>
            Chaque refus (accès interdit, page expirée, lien signé altéré, fichier refusé, limite de débit, paramètre suspect) est journalisé
            avec la route et une IP approximative, jamais le contenu de la requête. Au-delà de {{ config('security.suspect.user_max') }} événements
            en {{ intdiv((int) config('security.suspect.window_seconds'), 60) }} minutes pour un compte ({{ config('security.suspect.ip_max') }} pour une IP),
            l’accès est bloqué {{ config('security.suspect.block_minutes') }} minutes et les administrateurs sont prévenus.
        </p>
        <p>
            <x-filament::link :href="route('agent.security.index')">Tentatives de connexion (F37)</x-filament::link>
            ·
            <x-filament::link :href="url('/admin/securite')">Récapitulatif de sécurité</x-filament::link>
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
