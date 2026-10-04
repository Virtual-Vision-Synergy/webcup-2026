{{--
    F95 : page « Sobriété », PUBLIQUE (décision : la mesure doit être consultable sans compte, par les habitants et le jury).
    Lecture seule, aucune donnée personnelle. Les chiffres viennent de mesures faites sur la base de démonstration
    (cache vide) ; les seuils de requêtes SQL sont vérifiés par tests/Feature/SobrieteTest.php.
--}}
@php
    // [page, requêtes SQL avant, après, requêtes en double avant, après, poids de la page compressée (Ko)]
    $mesures = [
        ['Accueil', 9, 9, 0, 0, 17],
        ['Services', 12, 12, 1, 0, 39],
        ['Actualités', 9, 8, 1, 0, 35],
        ['Mes demandes', 10, 8, 1, 0, 37],
        ['Mon espace', 21, 15, 1, 0, 36],
    ];

    $reductions = [
        ['Requêtes en double supprimées', 'Le nombre de notifications non lues était compté deux fois par page (cloche du menu et cloche de l’en-tête). Il est maintenant compté une seule fois.'],
        ['Requêtes regroupées', 'Sur « Mon espace », l’état des services prioritaires était lu service par service ; il est lu en une fois. Les totaux se déduisent d’un seul décompte par statut.'],
        ['Données mises en cache', 'Les chiffres de l’accueil (1 minute) et les services prioritaires (10 minutes) sont gardés en mémoire : la plupart des visites ne lancent aucune requête pour eux.'],
        ['Une seule police', 'Une seule police, hébergée sur notre serveur. Aucune ressource n’est chargée depuis un autre site.'],
        ['Carte chargée seulement si elle est affichée', 'Le script de la carte n’est téléchargé que sur les pages qui en montrent une, au moment où elle arrive à l’écran.'],
        ['Images allégées', 'Les photos envoyées sont converties en WebP, avec une miniature, et chargées seulement quand elles deviennent visibles.'],
        ['Rafraîchissement limité', 'Seuls les écrans de suivi se mettent à jour seuls, toutes les 30 à 60 secondes, et uniquement quand ils sont visibles.'],
        ['Fichiers compressés et gardés en cache', 'Les feuilles de style et les scripts sont compressés et gardés un an par le navigateur ; les images et icônes, 30 jours.'],
    ];
@endphp

<x-layouts::public title="Sobriété">
    <section class="mx-auto w-full max-w-5xl space-y-8">
        <x-tn.page-header
            label="Sobriété"
            title="Une plateforme qui charge le strict nécessaire"
            subtitle="Ce que chaque page demande à nos serveurs et à votre appareil, avant et après nos réductions."
        />

        <x-tn.surface aria-labelledby="titre-mesures">
            <h2 id="titre-mesures" class="tn-display text-xl font-semibold text-ink">Mesures sur 5 pages principales</h2>
            <p class="mt-2 text-ink-2">Mesures faites sur la base de démonstration, sans rien en mémoire (cas le plus défavorable).</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-start text-sm" data-test="tableau-sobriete">
                    <caption class="sr-only">Requêtes à la base de données et poids de 5 pages, avant et après réduction</caption>
                    <thead>
                        <tr class="border-b border-line text-start font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">
                            <th scope="col" class="py-2 pe-4 text-start">Page</th>
                            <th scope="col" class="py-2 pe-4 text-start">Requêtes avant</th>
                            <th scope="col" class="py-2 pe-4 text-start">Requêtes après</th>
                            <th scope="col" class="py-2 pe-4 text-start">En double avant</th>
                            <th scope="col" class="py-2 pe-4 text-start">En double après</th>
                            <th scope="col" class="py-2 text-start">Poids de la page</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mesures as [$page, $avant, $apres, $doublesAvant, $doublesApres, $poids])
                            <tr class="border-b border-line align-top last:border-0">
                                <th scope="row" class="py-3 pe-4 text-start font-medium text-ink">{{ $page }}</th>
                                <td class="py-3 pe-4 font-mono">{{ $avant }}</td>
                                <td class="py-3 pe-4 font-mono font-semibold text-ink">{{ $apres }}</td>
                                <td class="py-3 pe-4 font-mono">{{ $doublesAvant }}</td>
                                <td class="py-3 pe-4 font-mono font-semibold text-ink">{{ $doublesApres }}</td>
                                <td class="py-3 font-mono">{{ $poids }} Ko</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-sm text-ink-2">
                « Requêtes » : questions posées à la base de données pour afficher la page.
                « Poids de la page » : taille du contenu envoyé, une fois compressé.
                Sur l’accueil, les 9 requêtes tombent à 1 dès que les chiffres sont en mémoire.
            </p>
        </x-tn.surface>

        <x-tn.surface aria-labelledby="titre-reductions">
            <h2 id="titre-reductions" class="tn-display text-xl font-semibold text-ink">Ce qui a été réduit</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($reductions as [$titre, $detail])
                    <li class="py-4">
                        <h3 class="font-semibold text-ink">{{ $titre }}</h3>
                        <p class="text-sm text-ink-2">{{ $detail }}</p>
                    </li>
                @endforeach
            </ul>
        </x-tn.surface>

        <x-tn.surface aria-labelledby="titre-aller-plus-loin">
            <h2 id="titre-aller-plus-loin" class="tn-display text-xl font-semibold text-ink">Aller encore plus léger</h2>
            <p class="mt-2 text-ink-2">
                Sur un réseau lent ou un appareil ancien, vous pouvez activer le « Mode allégé » ou la « Version simple » :
                moins d’images, pas d’animations, pas de rafraîchissement automatique.
            </p>
            <p class="mt-3">
                <a href="{{ route('accessibility.show') }}" class="font-medium text-cyan underline underline-offset-2">Voir tous les réglages d’affichage</a>
            </p>
        </x-tn.surface>
    </section>
</x-layouts::public>
