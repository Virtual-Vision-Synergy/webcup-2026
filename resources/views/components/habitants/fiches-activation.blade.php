@props([
    /** @var list<array{nom: string, identifiant: string, telephone: string|null, code: string}> */
    'fiches' => [],
])

{{-- F71 : fiches d'activation imprimables (une par habitant). À l'impression, seules les fiches apparaissent. --}}
<div {{ $attributes->class('fiches-activation space-y-4') }}>
    <style>
        @media print {
            body * { visibility: hidden !important; }
            .fiches-activation, .fiches-activation * { visibility: visible !important; }
            .fiches-activation { position: absolute; inset: 0 auto auto 0; width: 100%; }
            .fiche-activation { break-inside: avoid; color: #000 !important; background: #fff !important; border-color: #000 !important; }
            .fiches-activation .no-print { display: none !important; }
        }
    </style>

    <div class="no-print flex flex-wrap items-center justify-between gap-3">
        <flux:text>{{ __('Les codes ne sont affichés qu\'une seule fois : imprimez les fiches maintenant.') }}</flux:text>
        <flux:button variant="primary" icon="printer" x-data x-on:click="window.print()">{{ __('Imprimer les fiches') }}</flux:button>
    </div>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        @foreach ($fiches as $fiche)
            <article class="fiche-activation rounded-md border border-dashed border-line bg-surface p-4" wire:key="fiche-{{ $fiche['identifiant'] }}">
                <p class="font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">{{ __('Mairie de Nova Terra') }} · {{ __('Fiche d\'activation') }}</p>
                <p class="mt-1 text-lg font-semibold text-ink">{{ $fiche['nom'] }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-ink-2">{{ __('Identifiant') }} / Identifier / Mpampiasa</dt>
                        <dd class="font-mono text-base font-semibold text-ink">{{ $fiche['identifiant'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-2">{{ __('Code d\'activation') }} / Activation code / Kaody</dt>
                        <dd class="font-mono text-base font-semibold text-ink">{{ $fiche['code'] }}</dd>
                    </div>
                    @if ($fiche['telephone'])
                        <div class="col-span-2">
                            <dt class="text-ink-2">{{ __('Téléphone') }}</dt>
                            <dd class="font-mono text-ink">{{ $fiche['telephone'] }}</dd>
                        </div>
                    @endif
                </dl>
                <ol class="mt-3 list-decimal space-y-0.5 ps-5 text-xs text-ink-2">
                    <li>{{ route('activation.create') }}</li>
                    <li>{{ __('Saisir l\'identifiant et le code ci-dessus') }} · Enter the identifier and code · Ampidiro ny mpampiasa sy ny kaody</li>
                    <li>{{ __('Choisir son code personnel (code à usage unique)') }} · Choose your personal code · Mifidiana kaody manokana</li>
                </ol>
            </article>
        @endforeach
    </div>
</div>
