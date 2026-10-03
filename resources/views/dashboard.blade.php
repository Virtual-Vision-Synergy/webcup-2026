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
    $rubriques = array_filter(config('navigation.rubriques'), fn (array $r): bool => Route::has($r['route']));
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

        <x-onboarding.rappel />

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
                        <a href="{{ route('demarches.historique') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">Suivi de mes demandes</a>
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
                                    <span class="mt-2 font-medium text-ink group-hover:text-cyan">{{ $rubrique['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- MON COMPTE --}}
                <x-tn.surface>
                    <x-tn.section-label as="h2" class="mb-2">Mon compte</x-tn.section-label>
                    <dl>
                        <x-tn.field label="Nom">{{ $user->name }}</x-tn.field>
                        <x-tn.field label="E-mail"><span class="break-all">{{ $user->email }}</span></x-tn.field>
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
