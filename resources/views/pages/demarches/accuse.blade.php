<?php

use App\Models\Demarche;
use App\Services\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * F83 : accusé de réception imprimable d'une démarche (Imprimer → Enregistrer en PDF).
 * Visible par l'auteur, le personnel du service concerné et les admins (DemarchePolicy::voirAccuse) ; autre habitant → 403.
 */
new #[Layout('layouts::imprimable'), Title('Accusé de réception')] class extends Component {
    #[Locked]
    public Demarche $record;

    public function mount(Demarche $demarche): void
    {
        AuditLogger::autoriser('voirAccuse', $demarche);
        $this->record = $demarche->loadMissing(['service:id,nom', 'user:id,name']);
    }
}; ?>

<div class="space-y-8 text-zinc-900">
    {{-- Barre d'actions (masquée à l'impression) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('demarches.show', $record) }}" class="text-sm font-medium text-cyan underline underline-offset-2">← Retour à la demande</a>
        <button type="button" onclick="window.print()" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-on-cyan hover:opacity-90">
            Imprimer / Enregistrer en PDF
        </button>
    </div>

    {{-- En-tête du document --}}
    <header class="flex items-start justify-between gap-4 border-b-2 border-zinc-900 pb-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Mairie de Nova Terra · Terra Nova</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Accusé de réception</h1>
        </div>
        <x-app-logo-icon class="size-12 shrink-0" />
    </header>

    <section aria-labelledby="reference" class="rounded-md border-2 border-zinc-900 p-5 text-center">
        <h2 id="reference" class="text-xs font-semibold uppercase tracking-widest text-zinc-500">Référence de la demande</h2>
        <p class="mt-2 font-mono text-3xl font-bold tracking-wider" data-test="reference">{{ $record->numeroSuivi() }}</p>
    </section>

    <p class="leading-relaxed">
        La mairie de Nova Terra atteste avoir reçu la demande décrite ci-dessous. Elle sera traitée par le service concerné ;
        son avancement est consultable à tout moment dans l'espace personnel du demandeur.
    </p>

    <dl class="divide-y divide-zinc-200 border-y border-zinc-300 text-sm">
        <div class="grid gap-1 py-3 sm:grid-cols-3">
            <dt class="font-semibold text-zinc-600">Reçue le</dt>
            <dd class="sm:col-span-2">{{ $record->dateReceptionLocale() }}</dd>
        </div>
        <div class="grid gap-1 py-3 sm:grid-cols-3">
            <dt class="font-semibold text-zinc-600">Objet</dt>
            <dd class="sm:col-span-2">{{ $record->titre }}</dd>
        </div>
        <div class="grid gap-1 py-3 sm:grid-cols-3">
            <dt class="font-semibold text-zinc-600">Service</dt>
            <dd class="sm:col-span-2">{{ $record->service?->nom ?? 'Non précisé (la mairie orientera la demande)' }}</dd>
        </div>
        <div class="grid gap-1 py-3 sm:grid-cols-3">
            <dt class="font-semibold text-zinc-600">Demandeur</dt>
            <dd class="sm:col-span-2">{{ $record->user?->name ?? '—' }}</dd>
        </div>
    </dl>

    <p class="rounded-md bg-zinc-100 p-4 text-sm font-medium print:border print:border-zinc-300 print:bg-white">
        Conservez ce document : la référence permet de retrouver votre demande.
    </p>

    <footer class="border-t border-zinc-300 pt-3 text-xs text-zinc-500">
        Document généré depuis Terra Nova le {{ now()->setTimezone(\App\Models\Annonce::FUSEAU)->format('d/m/Y à H:i') }}.
    </footer>
</div>
