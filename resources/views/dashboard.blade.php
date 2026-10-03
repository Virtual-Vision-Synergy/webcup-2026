@php
    use App\Models\Demarche;
    use Illuminate\Support\Facades\Route;

    $user = auth()->user();
    // Uniquement les démarches de l'utilisateur connecté (jamais d'ID venant du navigateur).
    $demarches = $user->demarches()->with('service')->latest()->limit(5)->get();
    $totalDemarches = $user->demarches()->count();
    $parStatut = $user->demarches()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');
    // Alertes : démarches traitées ou refusées dans les 7 derniers jours.
    $alertes = $user->demarches()
        ->whereIn('statut', ['traitee', 'refusee'])
        ->where('updated_at', '>=', now()->subDays(7))
        ->latest('updated_at')
        ->limit(3)
        ->get();
    // F29 : alertes en cours qui visent le quartier de l'habitant, affichées en tête.
    $alertesQuartier = \App\Models\Annonce::enDiffusion($user)->filter(fn ($annonce) => $annonce->concerne($user));
    // F28 : services prioritaires (mis en avant par un agent), proposés dans l'accès rapide.
    $servicesPrioritaires = \App\Models\Service::query()->where('mis_en_avant', true)->orderBy('nom')->limit(4)->get(['id', 'nom', 'slug', 'indisponible_depuis']);
    $rubriques = array_filter(config('navigation.rubriques'), fn (array $r): bool => Route::has($r['route']));
    // D11 : résumé de « Mes demandes » (signalements de l'utilisateur connecté uniquement).
    $totalMesDemandes = \App\Models\Signalement::duCitoyen($user)->count();
    $mesDemandesEnCours = \App\Models\Signalement::duCitoyen($user)->whereNotIn('statut', \App\Models\Signalement::STATUTS_TERMINES)->count();
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
        <x-tn.page-header label="Mon espace" :breadcrumb="['Mon espace' => null]" :title="'Bonjour '.$user->name" subtitle="Vos démarches et l'activité de la ville, en un coup d'œil.">
            <x-slot:actions>
                <span class="inline-flex items-center gap-2 rounded-xs border border-line px-2.5 py-1 font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">
                    <flux:icon name="users-round" class="size-3.5" /> {{ $user->role->label }}
                </span>
                @can('create', Demarche::class)
                    <flux:button variant="primary" icon="plus" :href="route('demarches.create')" class="tn-cta" wire:navigate>Nouvelle démarche</flux:button>
                @endcan
            </x-slot:actions>
        </x-tn.page-header>

        @if ($alertesQuartier->isNotEmpty())
            <section aria-labelledby="titre-alertes-quartier" class="flex flex-col gap-3">
                <h2 id="titre-alertes-quartier" class="tn-display text-lg font-semibold text-ink">Alertes dans votre quartier</h2>
                @foreach ($alertesQuartier as $alerteQuartier)
                    <x-tn.bandeau-annonce
                        class="rounded-md border"
                        variante="renforce"
                        :niveau="$alerteQuartier->niveau"
                        :titre="$alerteQuartier->titre"
                        :contenu="$alerteQuartier->contenu"
                        :consignes="$alerteQuartier->listeConsignes()"
                        :quartier="$alerteQuartier->nomQuartier()"
                        :lien="route('alertes.show', $alerteQuartier->id)"
                    />
                @endforeach
            </section>
        @elseif ($user->quartier_id === null)
            <p class="rounded-md border border-line bg-surface px-4 py-3 text-sm text-ink-2">
                <flux:icon name="map-pin" class="me-1 inline size-4 text-cyan" aria-hidden="true" />
                Indiquez votre quartier pour voir en priorité les alertes qui vous concernent.
                <a href="{{ route('profile.edit') }}" wire:navigate class="font-medium text-cyan underline underline-offset-2">Renseigner mon quartier</a>
            </p>
        @endif

        @if (($user->isAgent() || $user->isAdmin()) && ! $user->hasEnabledTwoFactorAuthentication())
            <p class="rounded-md border border-magenta/35 bg-magenta/8 px-4 py-3 text-sm text-ink-2" role="status">
                <flux:icon name="shield-check" class="me-1 inline size-4 text-magenta" aria-hidden="true" />
                Votre compte {{ $user->role->label }} donne accès aux données des habitants : la double authentification est fortement recommandée.
                <a href="{{ route('security.edit') }}" wire:navigate class="font-medium text-magenta underline underline-offset-2">Activer la double authentification</a>
            </p>
        @endif

        <x-onboarding.rappel />

        {{-- F72 : services recommandés selon la situation de l'habitant --}}
        <x-onboarding.par-ou-commencer />

        {{-- ALERTES --}}
        @if ($alertes->isNotEmpty())
            <section aria-labelledby="titre-alertes" class="flex flex-col gap-2">
                <h2 id="titre-alertes" class="sr-only">Alertes</h2>
                @foreach ($alertes as $alerte)
                    @php $refusee = $alerte->statut === 'refusee'; @endphp
                    <a href="{{ route('demarches.show', $alerte) }}" wire:navigate @class([
                        'flex items-center gap-3 rounded-md border px-4 py-3 transition-colors',
                        'border-magenta/35 bg-magenta/8 hover:bg-magenta/12' => $refusee,
                        'border-green/35 bg-green/8 hover:bg-green/12' => ! $refusee,
                    ])>
                        <flux:icon :name="$refusee ? 'megaphone' : 'activity'" @class(['size-5 shrink-0', 'text-magenta' => $refusee, 'text-green' => ! $refusee]) />
                        <span class="min-w-0 flex-1 text-ink">
                            <span class="font-medium">{{ $alerte->titre }}</span>
                            <span class="text-ink-2"> · {{ $refusee ? 'refusée' : 'traitée' }} {{ $alerte->updated_at->diffForHumans() }}</span>
                        </span>
                        <x-tn.status-badge :etat="$alerte->etatStatut()">{{ Demarche::libelleStatut($alerte->statut) }}</x-tn.status-badge>
                    </a>
                @endforeach
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            {{-- MES DÉMARCHES --}}
            <x-tn.panel padding="p-5 md:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <x-tn.section-label as="h2" class="text-ink!">Mes démarches</x-tn.section-label>
                        <p class="mt-1 text-sm text-ink-2">{{ $totalDemarches }} démarche(s) déposée(s)</p>
                    </div>
                    @if ($totalDemarches > 0)
                        <a href="{{ route('demarches.index') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">Tout voir</a>
                        <a href="{{ route('demarches.historique') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">Historique</a>
                    @endif
                </div>

                {{-- Compteurs par statut --}}
                <dl class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-sm border border-line bg-line sm:grid-cols-4">
                    @foreach (Demarche::STATUT_OPTIONS as $statut)
                        <div class="bg-surface/90 px-3 py-3 dark:bg-night/70">
                            <dt class="flex items-center gap-1.5 font-mono text-[0.65625rem] uppercase tracking-[.06em] text-ink-2">{{ Demarche::libelleStatut($statut) }}</dt>
                            <dd class="tn-display mt-1 text-2xl font-semibold tabular-nums text-ink">{{ sprintf('%02d', $parStatut[$statut] ?? 0) }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($demarches->isEmpty())
                    <x-tn.empty icon="file-text" title="Aucune démarche pour le moment" text="Vos démarches et leur suivi apparaîtront ici." class="mt-5 py-10">
                        <flux:button variant="primary" icon="plus" :href="route('demarches.create')" wire:navigate>Déposer une démarche</flux:button>
                    </x-tn.empty>
                @else
                    <ul class="mt-4">
                        @foreach ($demarches as $demarche)
                            <li wire:key="demarche-{{ $demarche->id }}">
                                <x-tn.list-row icon="file-text" :href="route('demarches.show', $demarche)" :stack="true">
                                    <span class="block truncate font-medium text-ink group-hover:text-cyan">{{ $demarche->titre }}</span>
                                    <span class="block truncate text-sm text-ink-2">{{ $demarche->service?->nom ?? 'Service non précisé' }} · <span class="font-mono text-xs">{{ $demarche->created_at->format('d.m.Y') }}</span></span>
                                    <x-slot:aside>
                                        <x-tn.status-badge :etat="$demarche->etatStatut()">{{ Demarche::libelleStatut($demarche->statut) }}</x-tn.status-badge>
                                    </x-slot:aside>
                                </x-tn.list-row>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-tn.panel>

            <div class="flex flex-col gap-6">
                {{-- MES DEMANDES (D11) --}}
                <x-tn.surface>
                    <div class="flex items-center justify-between gap-3">
                        <x-tn.section-label as="h2">Mes demandes</x-tn.section-label>
                        <flux:icon name="clipboard-document-list" class="size-5 text-cyan" aria-hidden="true" />
                    </div>
                    <p class="mt-2 text-ink-2">
                        @if ($totalMesDemandes === 0)
                            Vous n'avez encore déposé aucune demande.
                        @else
                            <strong class="text-ink">{{ $mesDemandesEnCours }}</strong> en cours sur {{ $totalMesDemandes }} demande(s) déposée(s).
                        @endif
                    </p>
                    <a href="{{ route('mes-demandes.index') }}" wire:navigate class="tn-btn-secondary mt-4 w-full">
                        <flux:icon name="clipboard-document-list" class="size-4" /> Suivre mes demandes
                    </a>
                </x-tn.surface>

                {{-- RACCOURCIS --}}
                <section aria-labelledby="titre-raccourcis">
                    <x-tn.section-label as="h2" id="titre-raccourcis" class="mb-3">Accès rapide</x-tn.section-label>
                    <ul class="grid grid-cols-2 gap-2">
                        @foreach ($rubriques as $rubrique)
                            <li>
                                <a href="{{ route($rubrique['route']) }}" wire:navigate class="group flex min-h-[92px] flex-col justify-between rounded-md border border-line bg-surface p-3 transition-colors hover:border-cyan/40">
                                    <span class="flex size-9 items-center justify-center rounded-sm border border-cyan/18 bg-cyan/8 text-cyan" aria-hidden="true">
                                        <flux:icon :name="$rubrique['icon']" class="size-[18px]" />
                                    </span>
                                    <span class="mt-2 font-medium text-ink group-hover:text-cyan">{{ __($rubrique['label']) }}</span>
                                </a>
                            </li>
                        @endforeach
                        <li class="col-span-2">
                            <a href="{{ route('urgences.index') }}" wire:navigate class="group flex min-h-[92px] flex-col justify-between rounded-md border border-magenta/35 bg-magenta/8 p-3 transition-colors hover:border-magenta/60">
                                <span class="flex size-9 items-center justify-center rounded-sm border border-magenta/25 bg-magenta/8 text-magenta" aria-hidden="true">
                                    <flux:icon name="heart" class="size-[18px]" />
                                </span>
                                <span class="mt-2 font-medium text-ink group-hover:text-magenta">Urgences / Santé</span>
                            </a>
                        </li>
                    </ul>
                </section>

                {{-- SERVICES PRIORITAIRES (F28) --}}
                @if ($servicesPrioritaires->isNotEmpty())
                    <section aria-labelledby="titre-services-prioritaires">
                        <x-tn.section-label as="h2" id="titre-services-prioritaires" class="mb-3">Services prioritaires</x-tn.section-label>
                        <ul class="flex flex-col gap-2">
                            @foreach ($servicesPrioritaires as $servicePrioritaire)
                                <li>
                                    <a href="{{ route('services.show', $servicePrioritaire) }}" wire:navigate class="group flex min-h-11 items-center gap-3 rounded-md border border-cyan/40 bg-surface px-3 py-2 transition-colors hover:border-cyan">
                                        <flux:icon name="star" variant="solid" class="size-4 shrink-0 text-cyan" aria-hidden="true" />
                                        <span class="min-w-0 flex-1 truncate font-medium text-ink group-hover:text-cyan">{{ __($servicePrioritaire->nom) }}</span>
                                        @if ($servicePrioritaire->estIndisponible())
                                            <flux:badge size="sm" color="red">Indisponible</flux:badge>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('services.index') }}" wire:navigate class="mt-2 inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">Tous les services</a>
                    </section>
                @endif

                {{-- MON COMPTE --}}
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-2">Mon compte</x-tn.section-label>
                    <dl>
                        <x-tn.field label="Nom">{{ $user->name }}</x-tn.field>
                        @if ($user->aUnEmail())
                            <x-tn.field label="E-mail"><span class="break-all">{{ $user->email }}</span></x-tn.field>
                        @endif
                        @if ($user->identifiant)
                            <x-tn.field label="{{ __('Identifiant d\'habitant') }}"><span class="font-mono">{{ $user->identifiant }}</span></x-tn.field>
                        @endif
                        @if ($user->telephone)
                            <x-tn.field label="{{ __('Téléphone') }}">{{ $user->telephone }}</x-tn.field>
                        @endif
                        <x-tn.field label="Membre depuis">{{ $user->created_at?->translatedFormat('d F Y') }}</x-tn.field>
                    </dl>
                    <a href="{{ route('profile.edit') }}" wire:navigate class="tn-btn-secondary mt-4 w-full">
                        <flux:icon name="settings" class="size-4" /> Modifier mon profil
                    </a>
                </x-tn.surface>
            </div>
        </div>
    </div>
</x-layouts::app>
