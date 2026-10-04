{{--
    F93 : bandeau affiché quand une dépendance externe est en panne (e-mails). Inclus dans <x-tn.bandeau-annonces />,
    donc présent dans tous les gabarits : l'habitant comprend pourquoi il ne reçoit pas d'e-mail.
--}}
@if (\App\Support\DependancesExternes::enPanne(\App\Support\DependancesExternes::EMAIL))
    <div role="status" data-test="bandeau-panne-email" class="border-b border-amber-300 bg-amber-50 px-4 py-2 text-amber-950 lg:px-8 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-50">
        <div class="mx-auto flex max-w-7xl items-start gap-2 text-sm">
            <flux:icon.envelope class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                <strong class="font-semibold">{{ __('Envoi des e-mails momentanément interrompu.') }}</strong>
                {{ __('Vos démarches et messages sont bien enregistrés : retrouvez les réponses dans vos notifications (cloche) et dans « Mes demandes ».') }}
            </p>
        </div>
    </div>
@endif
