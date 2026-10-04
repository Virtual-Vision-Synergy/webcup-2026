{{-- Historique des modifications d'une fiche (F48). Classe : App\View\Components\AuditHistory (droits vérifiés dans shouldRender). --}}
@if ($variant === 'resume')
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-surface px-4 py-3 text-sm text-ink-2">
        <p>
            @if ($entrees->isNotEmpty())
                Dernière modification par <span class="font-medium text-ink">{{ $entrees->first()->actor_name }}</span>
                le {{ $entrees->first()->dateComplete() }} ({{ $entrees->first()->dateRelative() }})
            @else
                Aucune modification enregistrée pour cette fiche.
            @endif
        </p>
        <flux:button size="sm" icon="clock" href="#historique">Historique ({{ $total }})</flux:button>
    </div>
@else
    <x-tn.surface id="historique" class="scroll-mt-24">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-tn.section-label as="h2">Historique des modifications ({{ $total }})</x-tn.section-label>
            @if ($total > 0)
                <flux:link :href="$lienComplet()" wire:navigate class="text-sm">Voir tout l'historique</flux:link>
            @endif
        </div>

        @if ($entrees->isEmpty())
            <flux:text>Aucune modification enregistrée pour cette fiche.</flux:text>
        @else
            <ul class="divide-y divide-line">
                @foreach ($entrees as $log)
                    <x-audit-history.entree :log="$log" wire:key="historique-{{ $log->id }}" />
                @endforeach
            </ul>
            @if ($total > $entrees->count())
                <div class="mt-4">
                    <flux:button size="sm" :href="$lienComplet()" wire:navigate>Voir les {{ $total }} modifications</flux:button>
                </div>
            @endif
        @endif
    </x-tn.surface>
@endif
