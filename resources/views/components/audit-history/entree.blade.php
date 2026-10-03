@props(['log'])

@use('App\Models\AuditLog')

{{-- Une modification de l'historique d'une fiche (F48) : qui, quand, quoi, champ par champ avant / après. Tout est échappé. --}}
<li {{ $attributes->class('space-y-3 py-4 first:pt-0 last:pb-0') }}>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <x-tn.status-badge :etat="$log->etatAction()">{{ $log->libelleAction() }}</x-tn.status-badge>
        @if ($log->estDuJour())
            <x-tn.status-badge etat="normal">Aujourd'hui</x-tn.status-badge>
        @endif
        <span class="font-medium text-ink">{{ $log->actor_name }}</span>
        @if ($log->actor_role)
            <span class="text-sm text-ink-2">{{ $log->actor_role }}</span>
        @endif
    </div>
    <p class="text-sm text-ink-2">
        <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->dateComplete() }}</time>
        <span aria-hidden="true">·</span> {{ $log->dateRelative() }}
        <span aria-hidden="true">·</span>
        <a href="{{ route('agent.audit.show', $log) }}" class="text-cyan hover:underline" wire:navigate>Voir dans le journal</a>
    </p>

    @if (! empty($log->changes))
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Champ</flux:table.column>
                    <flux:table.column>Avant</flux:table.column>
                    <flux:table.column>Après</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($log->changes as $champ => $valeurs)
                        <flux:table.row wire:key="historique-{{ $log->id }}-{{ $champ }}">
                            <flux:table.cell class="font-medium">{{ AuditLog::libelleChamp((string) $champ) }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal! break-words text-ink-2">{{ $log->valeurLisible((string) $champ, $valeurs['avant'] ?? null) }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal! break-words">{{ $log->valeurLisible((string) $champ, $valeurs['apres'] ?? null) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</li>
