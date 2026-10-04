@php
    use App\Models\Actualite;
    use App\Models\Demarche;
    use App\Models\Service;
    use App\Models\User;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;

    $connecte = auth()->check();

    // Données réelles de la base, mises en cache 60 s (tableaux simples, pas de modèles en cache).
    // F78 : vidé dès qu'un service ou une actualité change (trait ViderCachesPublics).
    $etat = Cache::remember(Service::CACHE_ACCUEIL, 60, fn (): array => [
        'genere_le' => now()->timestamp,
        'services' => Service::count(),
        'actualites' => Actualite::count(),
        'derniere_actualite' => Actualite::max('date'),
        'demarches_en_cours' => Demarche::whereIn('statut', ['deposee', 'en_cours'])->count(),
        'demarches_traitees' => Demarche::where('statut', 'traitee')->count(),
        'inscrits' => User::count(),
        'fil' => Actualite::query()->latest('date')->latest('id')->limit(3)->get(['id', 'titre', 'contenu', 'date'])
            ->map(fn (Actualite $a): array => [
                'id' => $a->id,
                'titre' => $a->titre,
                'extrait' => Str::limit(strip_tags((string) $a->contenu), 140),
                'date' => $a->date?->toDateString(),
            ])->all(),
        // Services mis en avant par les agents en premier, complétés par ordre alphabétique.
        'apercu_services' => Service::query()->prioritaires()->limit(4)->get(['nom', 'slug', 'horaires', 'mis_en_avant'])
            ->map(fn (Service $s): array => ['nom' => $s->nom, 'slug' => $s->slug, 'horaires' => $s->horaires, 'mis_en_avant' => $s->mis_en_avant])->all(),
    ]);

    $majIlYa = max(0, now()->timestamp - $etat['genere_le']);
    $nombre = fn (int $n): string => number_format($n, 0, ',', ' ');

    // Panneau « État de la plateforme » : une ligne par rubrique, avec un état (couleur = état, jamais catégorie).
    $lignesEtat = [
        ['icon' => 'landmark', 'label' => __('Services'), 'detail' => __(':n services municipaux', ['n' => $nombre($etat['services'])]), 'etat' => $etat['services'] > 0 ? 'normal' : 'info', 'badge' => $etat['services'] > 0 ? __('En ligne') : __('À venir')],
        ['icon' => 'newspaper', 'label' => __('Actualités'), 'detail' => $etat['derniere_actualite'] ? __('Dernière :').' '.\Illuminate\Support\Carbon::parse($etat['derniere_actualite'])->translatedFormat('d M Y') : __('Aucune publication'), 'etat' => 'info', 'badge' => __(':n publiées', ['n' => $nombre($etat['actualites'])])],
        ['icon' => 'file-text', 'label' => __('Démarches'), 'detail' => __(':n traitées', ['n' => $nombre($etat['demarches_traitees'])]), 'etat' => $etat['demarches_en_cours'] > 0 ? 'perturbe' : 'normal', 'badge' => __(':n en cours', ['n' => $nombre($etat['demarches_en_cours'])])],
        ['icon' => 'users-round', 'label' => __('Habitants'), 'detail' => __('Comptes inscrits sur le réseau'), 'etat' => 'normal', 'badge' => $nombre($etat['inscrits'])],
    ];

    $rubriques = config('navigation.rubriques');

    // F59 : en « Mode allégé », l'illustration décorative du hero n'est pas téléchargée.
    $illustration = ! \App\Support\ModeAllege::actif() && file_exists(public_path('images/hero/ciel-nuit.webp')) ? asset('images/hero/ciel-nuit.webp') : null;
@endphp

<x-layouts::site :title="__('Accueil')" :fluid="true" :description="__('Vos démarches, les actualités de la ville et le contact avec vos services municipaux, au même endroit : la plateforme civique officielle de la Mairie de Nova Terra.')">
    {{-- HERO --}}
    <section class="tn-sky relative overflow-hidden" aria-labelledby="titre-hero">
        @if ($illustration)
            {{-- F60 : loading="lazy" sur une image masquée (display:none) évite son téléchargement en thème clair. --}}
            <img src="{{ $illustration }}" alt="" class="absolute inset-0 hidden size-full object-cover opacity-60 dark:block" loading="lazy" decoding="async">
        @endif
        <div class="tn-planet -top-32 -right-40 size-[340px] md:-top-40 md:-right-24 md:size-[520px]" aria-hidden="true"></div>
        <div class="tn-grid" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 pt-10 pb-14 sm:pt-16 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:px-8 lg:pt-24 lg:pb-24">
            <div>
                <x-tn.section-label class="flex items-center gap-2 text-cyan!">
                    <x-tn.live-dot class="text-green" /> {{ __('Mairie de Nova Terra') }}
                </x-tn.section-label>
                <h1 id="titre-hero" class="tn-h1 mt-4 text-ink">{{ __('La ville en direct, au service de ses habitants.') }}</h1>
                <p class="mt-5 max-w-xl text-[1.0625rem] leading-[1.55] text-ink-2 sm:text-lg">
                    {{ __('Vos démarches, les actualités de la ville et le contact avec vos services municipaux, au même endroit.') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @if ($connecte)
                        <flux:button :href="route('dashboard')" variant="primary" class="tn-cta h-[52px]! px-6! text-base!">{{ __('Mon espace') }}</flux:button>
                    @elseif (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" class="tn-cta h-[52px]! px-6! text-base!">{{ __('Créer un compte') }}</flux:button>
                    @endif
                    @unless ($connecte)
                        <a href="{{ route('login') }}" class="tn-btn-secondary h-[52px]">{{ __('Connexion') }}</a>
                    @endunless
                </div>
            </div>

            <x-tn.panel padding="p-5 md:p-7">
                <div class="flex items-center justify-between gap-3">
                    <x-tn.section-label as="h2" class="text-ink!">{{ __('État de la plateforme') }}</x-tn.section-label>
                    <x-tn.status-badge etat="normal" :live="true">{{ __('En direct') }}</x-tn.status-badge>
                </div>

                <ul class="mt-4">
                    @foreach ($lignesEtat as $ligne)
                        <li>
                            <x-tn.list-row :icon="$ligne['icon']">
                                <span class="block font-medium text-ink">{{ $ligne['label'] }}</span>
                                <span class="block truncate text-sm text-ink-2">{{ $ligne['detail'] }}</span>
                                <x-slot:aside>
                                    <x-tn.status-badge :etat="$ligne['etat']">{{ $ligne['badge'] }}</x-tn.status-badge>
                                </x-slot:aside>
                            </x-tn.list-row>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-2 border-t border-line pt-3 font-mono text-[0.65625rem] uppercase tracking-[.06em] text-ink-2">
                    {{ __('MAJ il y a :s s · Source : base municipale', ['s' => $majIlYa]) }}
                </p>
            </x-tn.panel>
        </div>
    </section>

    {{-- F46 : URGENCES / SANTÉ, accès direct depuis l'accueil --}}
    <section class="border-t border-magenta/35 bg-magenta/8" aria-labelledby="titre-urgences">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <h2 id="titre-urgences" class="flex items-center gap-2 font-semibold text-ink">
                <flux:icon name="heart" class="size-5 shrink-0 text-magenta" aria-hidden="true" />
                {{ __('Urgence ?') }}
            </h2>
            <ul class="flex flex-wrap gap-2">
                @foreach (array_slice(Service::NUMEROS_URGENCE, 0, 3) as $urgence)
                    <li>
                        <a href="tel:{{ $urgence['numero'] }}" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-magenta/50">
                            {{ __($urgence['label']) }} <span class="font-mono font-semibold text-magenta">{{ $urgence['numero'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('urgences.index') }}" class="inline-flex min-h-11 items-center gap-1 font-medium text-magenta hover:underline">
                {{ __('Urgences / Santé : hôpitaux et numéros') }} <flux:icon name="arrow-right" class="size-4" aria-hidden="true" />
            </a>
        </div>
    </section>

    {{-- LES 4 RUBRIQUES --}}
    <section class="border-y border-line bg-night" aria-labelledby="titre-rubriques">
        <h2 id="titre-rubriques" class="sr-only">{{ __('Accès rapide aux rubriques') }}</h2>
        <ul class="mx-auto grid max-w-7xl sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
            @foreach ($rubriques as $i => $rubrique)
                <li class="border-line max-lg:border-b sm:max-lg:odd:border-e lg:border-e lg:first:border-s">
                    <a href="{{ Route::has($rubrique['route']) ? route($rubrique['route']) : '#' }}" class="group flex h-full flex-col gap-3 px-4 py-7 transition-colors hover:bg-cyan/[.04] lg:px-6">
                        <span class="flex items-center justify-between">
                            <span class="font-mono text-sm text-cyan">{{ sprintf('%02d', $i + 1) }}</span>
                            <flux:icon :name="$rubrique['icon']" class="size-5 text-ink-2 transition-colors group-hover:text-cyan" />
                        </span>
                        <span class="tn-display text-xl font-semibold text-ink">{{ __($rubrique['label']) }}</span>
                        <span class="text-[0.9375rem] text-ink-2">{{ __($rubrique['texte']) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- FIL DU HAUT CONSEIL + SERVICES --}}
    <section class="mx-auto grid max-w-7xl gap-10 px-4 py-14 lg:grid-cols-[1.4fr_1fr] lg:gap-14 lg:px-8 lg:py-20">
        <div>
            <div class="flex items-end justify-between gap-4">
                <div>
                    <x-tn.section-label>{{ __('Fil du Haut Conseil') }}</x-tn.section-label>
                    <h2 class="tn-h2 mt-2 text-ink">{{ __('Dernières annonces') }}</h2>
                </div>
                @if (Route::has('actualites.index'))
                    <a href="{{ route('actualites.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">{{ __('Toutes les actualités') }}</a>
                @endif
            </div>

            @if (count($etat['fil']))
                <ol class="mt-6">
                    @foreach ($etat['fil'] as $article)
                        <li class="grid gap-2 border-t border-line py-5 sm:grid-cols-[160px_1fr] sm:gap-6">
                            <div class="flex items-center gap-3 sm:flex-col sm:items-start">
                                @if ($article['date'])
                                    <time datetime="{{ $article['date'] }}" class="font-mono text-sm text-ink-2">{{ \Illuminate\Support\Carbon::parse($article['date'])->translatedFormat('d M Y') }}</time>
                                @endif
                                <x-tn.domain-tag>{{ __('Actualité') }}</x-tn.domain-tag>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-ink">
                                    <a href="{{ route('actualites.show', $article['id']) }}" class="hover:text-cyan">{{ $article['titre'] }}</a>
                                </h3>
                                <p class="mt-1 text-ink-2">{{ $article['extrait'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="mt-6 rounded-md border border-dashed border-line p-8 text-center">
                    <flux:icon name="newspaper" class="mx-auto size-8 text-ink-2" />
                    <p class="mt-3 font-medium text-ink">{{ __('Aucune annonce pour le moment') }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ __('Les publications du Haut Conseil apparaîtront ici.') }}</p>
                </div>
            @endif
        </div>

        <aside aria-labelledby="titre-services">
            <x-tn.panel padding="p-5 md:p-6">
                <x-tn.section-label>{{ __('Services municipaux') }}</x-tn.section-label>
                <h2 id="titre-services" class="tn-h2 mt-2 text-ink">{{ __('À votre service') }}</h2>

                @if (count($etat['apercu_services']))
                    <ul class="mt-4">
                        @foreach ($etat['apercu_services'] as $service)
                            <li>
                                <x-tn.list-row :icon="($service['mis_en_avant'] ?? false) ? 'star' : 'landmark'" :href="route('services.show', $service['slug'])">
                                    <span class="block truncate font-medium text-ink group-hover:text-cyan">{{ __($service['nom']) }}</span>
                                    @if ($service['mis_en_avant'] ?? false)
                                        <span class="sr-only">{{ __('(service mis en avant)') }}</span>
                                    @endif
                                    @if ($service['horaires'])
                                        <span class="block truncate font-mono text-xs text-ink-2">{{ __($service['horaires']) }}</span>
                                    @endif
                                </x-tn.list-row>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-ink-2">{{ __('Les services municipaux seront bientôt listés ici.') }}</p>
                @endif

                @if (Route::has('messages.index'))
                    <a href="{{ route('messages.index') }}" class="tn-btn-secondary mt-5 w-full">
                        <flux:icon name="mail" class="size-4" /> {{ __('Contact : écrire à un service') }}
                    </a>
                @endif
            </x-tn.panel>
        </aside>
    </section>
</x-layouts::site>
