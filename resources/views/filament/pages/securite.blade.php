<x-filament-panels::page>
    <x-filament::section heading="Vérification de la configuration (security:check)" description="Même contrôle que « php artisan security:check » sur le serveur.">
        <table class="fi-ta-table" style="width: 100%">
            <tbody>
                @foreach ($this->verifications() as $ligne)
                    <tr>
                        <td style="padding: .35rem .5rem; width: 4rem">
                            <x-filament::badge :color="$ligne['ok'] ? 'success' : 'danger'">{{ $ligne['ok'] ? 'OK' : 'KO' }}</x-filament::badge>
                        </td>
                        <td style="padding: .35rem .5rem; font-weight: 600">{{ $ligne['libelle'] }}</td>
                        <td style="padding: .35rem .5rem; word-break: break-word">{{ $ligne['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Ce qui a été audité et corrigé">
        {{-- Contenu issu de docs/SECURITY.md (fichier du dépôt, pas une saisie utilisateur ; HTML brut retiré par Str::markdown). --}}
        <div class="fi-prose">{!! $this->recapitulatif() !!}</div>
    </x-filament::section>
</x-filament-panels::page>
