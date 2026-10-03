@php
    use App\Models\Actualite;
    use App\Models\Demarche;
    use App\Models\Service;
    use App\Models\User;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Str;

    $connecte = auth()->check();

    // Données réelles de la base, mises en cache 60 s (tableaux simples, pas de modèles en cache).
    $etat = Cache::remember('landing.etat', 60, fn (): array => [
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
        'apercu_services' => Service::query()->orderBy('nom')->limit(4)->get(['nom', 'slug', 'horaires'])
            ->map(fn (Service $s): array => ['nom' => $s->nom, 'slug' => $s->slug, 'horaires' => $s->horaires])->all(),
    ]);

    $majIlYa = max(0, now()->timestamp - $etat['genere_le']);
    $nombre = fn (int $n): string => number_format($n, 0, ',', ' ');

    // Panneau « État de la plateforme » : une ligne par rubrique, avec un état (couleur = état, jamais catégorie).
    $lignesEtat = [
        ['icon' => 'landmark', 'label' => 'Services', 'detail' => $nombre($etat['services']).' services municipaux', 'etat' => $etat['services'] > 0 ? 'normal' : 'info', 'badge' => $etat['services'] > 0 ? 'En ligne' : 'À venir'],
        ['icon' => 'newspaper', 'label' => 'Actualités', 'detail' => $etat['derniere_actualite'] ? 'Dernière : '.\Illuminate\Support\Carbon::parse($etat['derniere_actualite'])->translatedFormat('d M Y') : 'Aucune publication', 'etat' => 'info', 'badge' => $nombre($etat['actualites']).' publiées'],
        ['icon' => 'file-text', 'label' => 'Démarches', 'detail' => $nombre($etat['demarches_traitees']).' traitées', 'etat' => $etat['demarches_en_cours'] > 0 ? 'perturbe' : 'normal', 'badge' => $nombre($etat['demarches_en_cours']).' en cours'],
        ['icon' => 'users-round', 'label' => 'Habitants', 'detail' => 'Comptes inscrits sur le réseau', 'etat' => 'normal', 'badge' => $nombre($etat['inscrits'])],
    ];

    $rubriques = config('navigation.rubriques');

    $illustration = file_exists(public_path('images/hero/ciel-nuit.webp')) ? asset('images/hero/ciel-nuit.webp') : null;
@endphp

<x-layouts::site :title="__('Home')" :fluid="true" description="Vos démarches, les actualités de la ville et le contact avec vos services municipaux, au même endroit : la plateforme civique officielle de la Mairie de Nova Terra.">
    {{-- HERO --}}
    <section class="tn-sky relative overflow-hidden" aria-labelledby="titre-hero">
        @if ($illustration)
            <img src="{{ $illustration }}" alt="" class="absolute inset-0 hidden size-full object-cover opacity-60 dark:block" fetchpriority="high">
        @endif
        <div class="tn-planet -top-32 -right-40 size-[340px] md:-top-40 md:-right-24 md:size-[520px]" aria-hidden="true"></div>
        <div class="tn-grid" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 pt-10 pb-14 sm:pt-16 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:px-8 lg:pt-24 lg:pb-24">
            <div>
                <x-tn.section-label class="flex items-center gap-2 text-cyan!">
                    <x-tn.live-dot class="text-green" /> {{ __('Nova Terra City Hall') }}
                </x-tn.section-label>
                <h1 id="titre-hero" class="tn-h1 mt-4 text-ink">{{ __('The city live, serving its residents.') }}</h1>
                <p class="mt-5 max-w-xl text-[17px] leading-[1.55] text-ink-2 sm:text-lg">
                    {{ __('Your procedures, city news and contact with your municipal services, all in one place.') }}
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @if ($connecte)
                        <flux:button :href="route('dashboard')" variant="primary" class="tn-cta h-[52px]! px-6! text-base!">{{ __('My area') }}</flux:button>
                    @elseif (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary" class="tn-cta h-[52px]! px-6! text-base!">{{ __('Create account') }}</flux:button>
                    @endif
                    @unless ($connecte)
                        <a href="{{ route('login') }}" class="tn-btn-secondary h-[52px]">{{ __('Log in') }}</a>
                    @endunless
                </div>
            </div>

            <x-tn.panel padding="p-5 md:p-7">
                <div class="flex items-center justify-between gap-3">
                    <x-tn.section-label as="h2" class="text-ink!">{{ __('Platform status') }}</x-tn.section-label>
                    <x-tn.status-badge etat="normal" :live="true">{{ __('Live') }}</x-tn.status-badge>
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

                <p class="mt-2 border-t border-line pt-3 font-mono text-[10.5px] uppercase tracking-[.06em] text-ink-2">
                    MAJ il y a {{ $majIlYa }} s · Source : base municipale
                </p>
            </x-tn.panel>
        </div>
    </section>

    {{-- LES 4 RUBRIQUES --}}
    <section class="border-y border-line bg-night" aria-labelledby="titre-rubriques">
        <h2 id="titre-rubriques" class="sr-only">{{ __('Quick access to sections') }}</h2>
        <ul class="mx-auto grid max-w-7xl sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
            @foreach ($rubriques as $i => $rubrique)
                <li class="border-line max-lg:border-b sm:max-lg:odd:border-e lg:border-e lg:first:border-s">
                    <a href="{{ Route::has($rubrique['route']) ? route($rubrique['route']) : '#' }}" class="group flex h-full flex-col gap-3 px-4 py-7 transition-colors hover:bg-cyan/[.04] lg:px-6">
                        <span class="flex items-center justify-between">
                            <span class="font-mono text-sm text-cyan">{{ sprintf('%02d', $i + 1) }}</span>
                            <flux:icon :name="$rubrique['icon']" class="size-5 text-ink-2 transition-colors group-hover:text-cyan" />
                        </span>
                        <span class="tn-display text-xl font-semibold text-ink">{{ __($rubrique['label']) }}</span>
                        <span class="text-[15px] text-ink-2">{{ __($rubrique['texte']) }}</span>
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
                    <x-tn.section-label>{{ __('High Council feed') }}</x-tn.section-label>
                    <h2 class="tn-h2 mt-2 text-ink">{{ __('Latest announcements') }}</h2>
                </div>
                @if (Route::has('actualites.index'))
                    <a href="{{ route('actualites.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-cyan hover:underline">{{ __('All news') }}</a>
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
                                <x-tn.domain-tag>Actualité</x-tn.domain-tag>
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
                    <p class="mt-3 font-medium text-ink">{{ __('No announcements yet') }}</p>
                    <p class="mt-1 text-sm text-ink-2">Les publications du Haut Conseil apparaîtront ici.</p>
                </div>
            @endif
        </div>

        <aside aria-labelledby="titre-services">
            <x-tn.panel padding="p-5 md:p-6">
                <x-tn.section-label>{{ __('Municipal services') }}</x-tn.section-label>
                <h2 id="titre-services" class="tn-h2 mt-2 text-ink">{{ __('At your service') }}</h2>

                @if (count($etat['apercu_services']))
                    <ul class="mt-4">
                        @foreach ($etat['apercu_services'] as $service)
                            <li>
                                <x-tn.list-row icon="landmark" :href="route('services.show', $service['slug'])">
                                    <span class="block truncate font-medium text-ink group-hover:text-cyan">{{ $service['nom'] }}</span>
                                    @if ($service['horaires'])
                                        <span class="block truncate font-mono text-xs text-ink-2">{{ $service['horaires'] }}</span>
                                    @endif
                                </x-tn.list-row>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-ink-2">Les services municipaux seront bientôt listés ici.</p>
                @endif

                @if (Route::has('messages.index'))
                    <a href="{{ route('messages.index') }}" class="tn-btn-secondary mt-5 w-full">
                        <flux:icon name="mail" class="size-4" /> {{ __('Contact: write to a service') }}
                    </a>
                @endif
            </x-tn.panel>
        </aside>
    </section>
</x-layouts::site>
