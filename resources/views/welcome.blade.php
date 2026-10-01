@php
    /*
     * TEXTES DE LA LANDING — seul endroit à modifier le jour J.
     * `icone` = nom d'une icône Heroicons (https://heroicons.com), ex. : shield-check, map-pin, bolt.
     */
    $landing = [
        'accroche' => 'Une idée, une application, vingt-quatre heures.',
        'sous_titre' => 'Créez votre compte et découvrez ce que notre équipe a construit pendant le 24h by Webcup 2026.',
        'bouton' => 'Créer un compte',
        'fonctionnalites' => [
            [
                'icone' => 'bolt',
                'titre' => 'Rapide',
                'texte' => 'Une interface légère qui s’affiche en un clin d’œil, même sur un réseau mobile.',
            ],
            [
                'icone' => 'shield-check',
                'titre' => 'Sécurisé',
                'texte' => 'Connexion protégée, double authentification et accès limité à vos propres données.',
            ],
            [
                'icone' => 'device-phone-mobile',
                'titre' => 'Pensé pour le mobile',
                'texte' => 'Une expérience confortable sur téléphone comme sur ordinateur, en clair ou en sombre.',
            ],
        ],
    ];

    $nom = config('app.name');
    $connecte = auth()->check();

    // Chiffres en direct : une seule requête, mise en cache 60 secondes.
    $chiffres = Cache::remember('landing.chiffres', 60, fn (): array => [
        ['valeur' => \App\Models\User::count(), 'libelle' => 'utilisateurs inscrits'],
    ]);
@endphp
<!DOCTYPE html>
<html lang="fr">
    <head>
        @include('partials.head', ['title' => 'Accueil', 'description' => $landing['sous_titre']])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="border-b border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" aria-label="{{ $nom }} — accueil">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground">
                        <x-app-logo-icon class="size-5 fill-current" width="20" height="20" aria-hidden="true" />
                    </span>
                    <span class="truncate">{{ $nom }}</span>
                </a>

                <nav class="flex items-center gap-2" aria-label="Compte">
                    @if ($connecte)
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">Mon espace</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">Connexion</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">Inscription</flux:button>
                        @endif
                    @endif
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-5xl px-4 py-16 text-center sm:py-24">
                <h1 class="mx-auto max-w-3xl text-balance text-4xl font-bold tracking-tight sm:text-5xl">
                    {{ $landing['accroche'] }}
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-300">
                    {{ $landing['sous_titre'] }}
                </p>
                <div class="mt-8">
                    @if ($connecte)
                        <flux:button :href="route('dashboard')" variant="primary">Mon espace</flux:button>
                    @elseif (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary">{{ $landing['bouton'] }}</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="primary">Connexion</flux:button>
                    @endif
                </div>
            </section>

            <section class="mx-auto max-w-5xl px-4 pb-12" aria-labelledby="titre-fonctionnalites">
                <h2 id="titre-fonctionnalites" class="sr-only">Fonctionnalités</h2>
                <ul class="grid gap-4 sm:grid-cols-3">
                    @foreach ($landing['fonctionnalites'] as $fonctionnalite)
                        <li>
                            <flux:card class="h-full">
                                <flux:icon :name="$fonctionnalite['icone']" class="size-6 text-accent-content" aria-hidden="true" />
                                <h3 class="mt-3 text-lg font-semibold">{{ $fonctionnalite['titre'] }}</h3>
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $fonctionnalite['texte'] }}</p>
                            </flux:card>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="border-y border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900" aria-labelledby="titre-chiffres">
                <h2 id="titre-chiffres" class="sr-only">La plateforme en chiffres</h2>
                <dl class="mx-auto flex max-w-5xl flex-wrap justify-center gap-x-12 gap-y-4 px-4 py-8 text-center">
                    @foreach ($chiffres as $chiffre)
                        <div>
                            <dt class="text-zinc-600 dark:text-zinc-300">{{ $chiffre['libelle'] }}</dt>
                            <dd class="text-3xl font-bold text-accent-content">{{ number_format($chiffre['valeur'], 0, ',', ' ') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-8 text-center text-sm text-zinc-600 dark:text-zinc-300">
            Virtual Vision Synergie — 24h by Webcup 2026
        </footer>
    </body>
</html>
