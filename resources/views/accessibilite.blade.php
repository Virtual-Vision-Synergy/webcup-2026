{{--
    D20 : page « Accessibilité », PUBLIQUE (décision : on doit pouvoir régler l'affichage AVANT de s'inscrire).
    Les réglages présentés ici sont les mêmes composants que dans les en-têtes : un seul parcours pour tous,
    pas de version « adaptée » à part. Rien n'est stocké côté serveur (préférences gardées dans le navigateur).
--}}
@php
    $raccourcis = [
        ['Tab', 'Passer à l’élément suivant (lien, bouton, champ).'],
        ['Maj + Tab', 'Revenir à l’élément précédent.'],
        ['Entrée', 'Ouvrir un lien, valider un bouton, passer à l’étape suivante d’un formulaire.'],
        ['Espace', 'Cocher une case, choisir une option, activer un bouton.'],
        ['Flèches', 'Changer d’option dans une liste de choix (service d’une démarche, langue…).'],
        ['Échap', 'Fermer le menu mobile, une fenêtre ou une liste ouverte.'],
    ];
@endphp

<x-layouts::public title="Accessibilité">
    <section class="mx-auto w-full max-w-5xl space-y-8">
        <x-tn.page-header
            label="Accessibilité"
            title="Une plateforme utilisable par tous"
            subtitle="Les aides disponibles pour lire, naviguer et faire vos démarches, que vous voyiez mal, utilisiez le clavier, un lecteur d’écran ou une autre langue."
        />

        <flux:callout icon="information-circle">
            <flux:callout.text>
                Il n’y a pas de « version accessible » séparée : les réglages ci-dessous s’appliquent à toute la plateforme,
                de l’inscription au suivi de vos démarches. Ils sont gardés dans votre navigateur, sans créer de compte.
            </flux:callout.text>
        </flux:callout>

        <nav aria-label="Sommaire" class="flex flex-wrap gap-2 text-sm">
            <a href="#reglages" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Réglages d’affichage</a>
            <a href="#clavier" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Clavier</a>
            <a href="#lecteur-ecran" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Lecteur d’écran</a>
            <a href="#lisibilite" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Couleurs et lisibilité</a>
            <a href="#langue" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Langue</a>
            <a href="#signaler" class="inline-flex min-h-11 items-center rounded-xs border border-line px-3 hover:border-cyan">Signaler un problème</a>
        </nav>

        <x-tn.surface id="reglages" aria-labelledby="titre-reglages">
            <h2 id="titre-reglages" class="tn-display text-xl font-semibold text-ink">Réglages d’affichage</h2>
            <p class="mt-2 text-ink-2">Essayez-les ici : l’effet est immédiat et reste actif sur toutes les pages. Les mêmes boutons sont en haut de chaque page (ordinateur) et dans le menu (téléphone).</p>

            <ul class="mt-5 divide-y divide-line">
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-ink">Contraste élevé</h3>
                        <p class="text-sm text-ink-2">Texte noir sur blanc (ou blanc sur noir), bordures marquées, liens soulignés. Rapport de contraste supérieur à 15:1.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-ink-2" aria-hidden="true">Activer</span>
                        <x-tn.contrast-toggle class="border border-line" />
                    </div>
                </li>
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-ink">Taille du texte</h3>
                        <p class="text-sm text-ink-2">A (normal), A+ (125 %) ou A++ (150 %). Toute la page s’agrandit sans rien cacher ; le zoom du navigateur jusqu’à 400 % fonctionne aussi.</p>
                    </div>
                    <x-tn.text-size />
                </li>
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-ink">Thème clair ou sombre</h3>
                        <p class="text-sm text-ink-2">Choisissez le fond le plus confortable pour vos yeux. Les deux thèmes respectent les contrastes minimaux.</p>
                    </div>
                    <x-tn.theme-toggle class="border border-line" />
                </li>
                <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-ink">Animations réduites</h3>
                        <p class="text-sm text-ink-2">Si votre appareil demande moins de mouvements (réglage « Réduire les animations »), la plateforme coupe automatiquement les animations et transitions.</p>
                    </div>
                    <flux:badge icon="check" color="green">Automatique</flux:badge>
                </li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="clavier" aria-labelledby="titre-clavier">
            <h2 id="titre-clavier" class="tn-display text-xl font-semibold text-ink">Navigation au clavier</h2>
            <p class="mt-2 text-ink-2">Tout se fait sans souris : inscription, connexion, dépôt d’une démarche, suivi, messages. L’élément actif est toujours entouré d’un contour visible.</p>
            <ul class="mt-3 list-disc space-y-1 ps-5 text-ink-2">
                <li>Le premier appui sur <kbd class="rounded-xs border border-line px-1 font-mono text-ink">Tab</kbd> affiche le lien <strong class="text-ink">« Aller au contenu »</strong>, qui saute le menu.</li>
                <li>Dans le formulaire de démarche, <kbd class="rounded-xs border border-line px-1 font-mono text-ink">Entrée</kbd> passe à l’étape suivante et le curseur est placé au début de la nouvelle étape.</li>
                <li>Le menu mobile garde le focus à l’intérieur tant qu’il est ouvert et se ferme avec <kbd class="rounded-xs border border-line px-1 font-mono text-ink">Échap</kbd>.</li>
            </ul>

            <dl class="mt-5 grid gap-2 sm:grid-cols-2">
                @foreach ($raccourcis as [$touche, $effet])
                    <div class="flex items-start gap-3 rounded-md border border-line p-3">
                        <dt class="shrink-0"><kbd class="inline-flex min-w-16 justify-center rounded-xs border border-line bg-surface-2 px-2 py-0.5 font-mono text-sm text-ink">{{ $touche }}</kbd></dt>
                        <dd class="text-sm text-ink-2">{{ $effet }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-tn.surface>

        <x-tn.surface id="lecteur-ecran" aria-labelledby="titre-lecteur">
            <h2 id="titre-lecteur" class="tn-display text-xl font-semibold text-ink">Lecteurs d’écran</h2>
            <p class="mt-2 text-ink-2">La plateforme est conçue pour NVDA, JAWS, VoiceOver et TalkBack.</p>
            <ul class="mt-3 list-disc space-y-1 ps-5 text-ink-2">
                <li>Chaque page a un titre unique, une zone principale et des titres hiérarchisés pour naviguer rapidement.</li>
                <li>Les boutons qui ne montrent qu’une icône ont un nom lu à voix haute (« Modifier », « Supprimer », « Contraste élevé »…).</li>
                <li>Les champs obligatoires sont annoncés comme « requis » ; les erreurs sont lues dès qu’elles apparaissent.</li>
                <li>Les étapes d’un formulaire sont annoncées (« Étape 2 sur 3 : Votre demande »).</li>
                <li>Les images utiles ont un texte alternatif ; les images décoratives sont ignorées.</li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="lisibilite" aria-labelledby="titre-lisibilite">
            <h2 id="titre-lisibilite" class="tn-display text-xl font-semibold text-ink">Couleurs et lisibilité</h2>
            <ul class="mt-3 list-disc space-y-1 ps-5 text-ink-2">
                <li>Les textes respectent un contraste d’au moins 4,5:1 (niveau AA des règles internationales WCAG).</li>
                <li>La couleur n’est jamais la seule information : chaque état (en cours, traité, alerte…) a aussi une icône et un libellé écrit.</li>
                <li>La palette a été vérifiée pour les personnes daltoniennes (protanopie, deutéranopie, tritanopie).</li>
                <li>Une seule police, lisible, et des zones de clic d’au moins 44 pixels sur téléphone.</li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="langue" aria-labelledby="titre-langue">
            <h2 id="titre-langue" class="tn-display text-xl font-semibold text-ink">Langue</h2>
            <p class="mt-2 text-ink-2">La plateforme est proposée en français, en malagasy et en anglais. Votre choix est mémorisé sur cet appareil et sur votre compte. La langue de la page est déclarée pour que les lecteurs d’écran prononcent correctement.</p>
            <x-tn.langue class="mt-4" />
        </x-tn.surface>

        <x-tn.surface id="signaler" aria-labelledby="titre-signaler">
            <h2 id="titre-signaler" class="tn-display text-xl font-semibold text-ink">Signaler un problème d’accessibilité</h2>
            <p class="mt-2 text-ink-2">Une page difficile à utiliser, un bouton inaccessible au clavier, un texte illisible ? Dites-le aux services municipaux : nous vous proposerons une solution, et la démarche pourra être faite avec un agent si besoin.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @auth
                    <flux:button variant="primary" icon="mail" :href="route('messages.create')" class="tn-cta" wire:navigate>Écrire aux services</flux:button>
                    <flux:button icon="calendar-days" :href="route('appointments.create')" wire:navigate>Prendre rendez-vous avec un agent</flux:button>
                @else
                    <flux:button variant="primary" icon="mail" :href="route('login')" class="tn-cta">Se connecter pour écrire aux services</flux:button>
                @endauth
            </div>
        </x-tn.surface>
    </section>
</x-layouts::public>
