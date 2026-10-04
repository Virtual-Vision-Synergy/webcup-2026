<x-filament-panels::page>
    <x-filament::section
        heading="Comment un compte devient suspect"
        description="Aucun compte n’est bloqué sur un seul signal faible : chaque signal ajoute des points sur 24 h (moyen = 1, élevé = 3, simple information = 0). À partir de {{ config('security.surveillance.score_suspect') }} points le compte apparaît ici ; à {{ config('security.surveillance.score_verrouillage_auto') }} points en une heure il est verrouillé automatiquement {{ config('security.surveillance.verrouillage_auto_minutes') }} minutes (jamais un admin)."
    />

    {{ $this->table }}
</x-filament-panels::page>
