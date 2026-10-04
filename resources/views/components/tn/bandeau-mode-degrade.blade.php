{{--
    F77 : bandeau « Service en mode allégé » affiché à tous (invités compris) quand le mode dégradé est actif.
    Inclus en tête de <x-tn.bandeau-annonces />, donc présent dans tous les gabarits.
--}}
@if (\App\Support\ModeDegrade::actif())
    <div role="status" data-test="bandeau-mode-degrade" class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-amber-950 lg:px-8 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-50">
        <div class="mx-auto flex max-w-7xl items-start gap-2 text-sm">
            <flux:icon.bolt class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                <strong class="font-semibold">Service en mode allégé.</strong>
                Nos serveurs sont très sollicités : les pages sont simplifiées et se mettent à jour moins souvent.
                Vos démarches, signalements et le suivi de vos dossiers restent disponibles.
            </p>
        </div>
    </div>
@endif
