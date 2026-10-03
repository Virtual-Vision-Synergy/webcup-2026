{{--
    F51 : « Vos données », page PUBLIQUE (décision : un habitant doit comprendre l'usage de ses données avant de créer un compte).
    Rédigée à partir de l'inventaire réel du code. Les durées viennent de la config quand elles existent ;
    sinon « à préciser par la Mairie » (aucune valeur inventée). Contenu à faire valider par la ville.
--}}
@php
    $aPreciser = 'À préciser par la Mairie';
    $joursTentatives = (int) config('security.login.retention_days');
    $minutesSession = (int) config('session.lifetime');
    $minutesLien = \App\Models\LienConnexion::DUREE_MINUTES;

    // donnée → à quoi elle sert → durée → qui y accède
    $donnees = [
        ['Votre compte : nom, adresse e-mail, mot de passe (chiffré), téléphone, quartier', 'Vous connecter, vous joindre, vous envoyer les alertes de votre quartier', 'Tant que votre compte existe', 'Vous, les agents municipaux, les administrateurs'],
        ['Vos démarches', 'Traiter vos demandes auprès des services', 'Tant que votre compte existe', 'Vous, les agents municipaux, les administrateurs'],
        ['Vos signalements et vos soutiens', 'Réparer ce qui ne va pas dans la ville', 'Tant que votre compte existe', 'Vous, les agents municipaux, les administrateurs'],
        ['Vos rendez-vous', 'Organiser votre rendez-vous avec un service', 'Tant que votre compte existe', 'Vous, les agents municipaux'],
        ['Vos messages à la mairie (avec le nom et l’e-mail saisis)', 'Vous répondre', 'Tant que votre compte existe', 'Vous, les agents municipaux, les administrateurs'],
        ['Vos avis (notifications)', 'Vous prévenir des nouveautés qui vous concernent', $aPreciser, 'Vous seul'],
        ['Vos remontées sur les données', 'Suivre vos questions et y répondre', 'Tant que votre compte existe, puis conservées sans votre nom ni votre e-mail (durée à préciser par la Mairie)', 'Vous, les agents municipaux, les administrateurs'],
        ['Les tentatives de connexion : e-mail saisi, adresse IP, navigateur', 'Bloquer les personnes qui essaient de deviner un mot de passe', $joursTentatives.' jours, puis effacées automatiquement', 'Les agents municipaux, les administrateurs'],
        ['Le journal des actions sensibles : nom de la personne, adresse IP, navigateur, ce qui a changé', 'Savoir qui a fait quoi, en cas de problème', $aPreciser, 'Les agents municipaux, les administrateurs'],
        ['La session de connexion : adresse IP, navigateur, cookies techniques', 'Vous garder connecté en sécurité', 'Fin de session après '.$minutesSession.' minutes sans activité', 'Personne (usage technique)'],
        ['Les liens de connexion envoyés par e-mail', 'Vous connecter sans mot de passe', 'Utilisables '.$minutesLien.' minutes, une seule fois ; durée de conservation à préciser par la Mairie', 'Personne (seule une empreinte est gardée)'],
    ];
@endphp

<x-layouts::public title="Vos données">
    <section class="mx-auto w-full max-w-5xl space-y-8">
        <x-tn.page-header
            label="Vos données"
            title="Vos données, en clair"
            subtitle="Ce que la plateforme garde sur vous, pourquoi, combien de temps, qui peut le voir, et comment l’effacer."
        >
            <x-slot:actions>
                @auth
                    <flux:button icon="arrow-down-tray" :href="route('profile.data')">
                        Télécharger mes données
                    </flux:button>
                @endauth
                <flux:button variant="primary" icon="chat-bubble-left-ellipsis" :href="route('concerns.create')" class="tn-cta">
                    Faire remonter une inquiétude
                </flux:button>
            </x-slot:actions>
        </x-tn.page-header>

        <flux:callout icon="information-circle">
            <flux:callout.text>Ce texte décrit le fonctionnement réel de la plateforme. Il doit encore être validé par la Mairie de Nova Terra : les mentions « à préciser par la Mairie » seront complétées par la ville.</flux:callout.text>
        </flux:callout>

        <nav aria-label="Sommaire" class="flex flex-wrap gap-2 text-sm">
            <a href="#quelles-donnees" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Quelles données ?</a>
            <a href="#pourquoi" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Pourquoi ?</a>
            <a href="#combien-de-temps" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Combien de temps ?</a>
            <a href="#qui-peut-les-voir" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Qui peut les voir ?</a>
            <a href="#comment-les-supprimer" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Comment les supprimer ?</a>
            <a href="#une-question" class="rounded-xs border border-line px-3 py-1.5 hover:border-cyan">Une question ?</a>
        </nav>

        <x-tn.surface id="quelles-donnees">
            <h2 class="tn-display text-xl font-semibold text-ink">Quelles données ?</h2>
            <p class="mt-2 text-ink-2">Seulement ce que vous nous donnez, et ce qui sert à protéger votre compte. Rien n’est vendu. Rien n’est utilisé pour de la publicité.</p>

            {{-- Mobile : une carte par donnée --}}
            <ul class="mt-4 space-y-3 sm:hidden">
                @foreach ($donnees as [$donnee, $usage, $duree, $acces])
                    <li class="rounded-md border border-line p-4">
                        <p class="font-medium text-ink">{{ $donnee }}</p>
                        <dl class="mt-2 space-y-1 text-sm">
                            <div><dt class="inline text-ink-2">À quoi elle sert :</dt> <dd class="inline">{{ $usage }}</dd></div>
                            <div><dt class="inline text-ink-2">Durée :</dt> <dd class="inline">{{ $duree }}</dd></div>
                            <div><dt class="inline text-ink-2">Qui y accède :</dt> <dd class="inline">{{ $acces }}</dd></div>
                        </dl>
                    </li>
                @endforeach
            </ul>

            {{-- Tablette et ordinateur : tableau --}}
            <div class="mt-4 overflow-x-auto max-sm:hidden">
                <table class="w-full text-start text-sm">
                    <caption class="sr-only">Les données gardées par la plateforme</caption>
                    <thead>
                        <tr class="border-b border-line text-start font-mono text-[0.6875rem] uppercase tracking-[.06em] text-ink-2">
                            <th scope="col" class="py-2 pe-4 text-start">Donnée</th>
                            <th scope="col" class="py-2 pe-4 text-start">À quoi elle sert</th>
                            <th scope="col" class="py-2 pe-4 text-start">Combien de temps</th>
                            <th scope="col" class="py-2 text-start">Qui y accède</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donnees as [$donnee, $usage, $duree, $acces])
                            <tr class="border-b border-line align-top last:border-0">
                                <th scope="row" class="py-3 pe-4 text-start font-medium text-ink">{{ $donnee }}</th>
                                <td class="py-3 pe-4">{{ $usage }}</td>
                                <td class="py-3 pe-4">{{ $duree }}</td>
                                <td class="py-3">{{ $acces }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-tn.surface>

        <x-tn.surface id="pourquoi">
            <h2 class="tn-display text-xl font-semibold text-ink">Pourquoi ?</h2>
            <ul class="mt-3 list-disc space-y-1 ps-5">
                <li>Pour traiter vos démarches, vos signalements et vos rendez-vous.</li>
                <li>Pour vous prévenir : avis dans la plateforme et e-mail.</li>
                <li>Pour vous envoyer les alertes de votre quartier.</li>
                <li>Pour protéger votre compte contre les intrusions.</li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="combien-de-temps">
            <h2 class="tn-display text-xl font-semibold text-ink">Combien de temps ?</h2>
            <ul class="mt-3 list-disc space-y-1 ps-5">
                <li>Vos démarches, signalements, rendez-vous et messages : tant que votre compte existe.</li>
                <li>Les tentatives de connexion : {{ $joursTentatives }} jours, puis elles sont effacées automatiquement.</li>
                <li>Votre session : elle se termine après {{ $minutesSession }} minutes sans activité.</li>
                <li>Un compte que vous n’utilisez plus : {{ mb_strtolower($aPreciser) }}.</li>
                <li>Le journal des actions sensibles : {{ mb_strtolower($aPreciser) }}.</li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="qui-peut-les-voir">
            <h2 class="tn-display text-xl font-semibold text-ink">Qui peut les voir ?</h2>
            <ul class="mt-3 list-disc space-y-1 ps-5">
                <li><strong>Vous</strong> : toutes vos données, dans votre espace.</li>
                <li><strong>Les agents municipaux</strong> : ce qui sert à traiter vos demandes (nom, e-mail, démarches, signalements, rendez-vous, messages, remontées).</li>
                <li><strong>Les administrateurs</strong> de la plateforme : pour la gérer et la sécuriser.</li>
                <li><strong>Les autres habitants</strong> : jamais vos données personnelles.</li>
                <li><strong>Hors de la mairie</strong> : seul l’hébergeur de la plateforme fait passer les e-mails qui vous sont envoyés. Aucune donnée n’est envoyée à un autre service.</li>
            </ul>
        </x-tn.surface>

        <x-tn.surface id="comment-les-supprimer">
            <h2 class="tn-display text-xl font-semibold text-ink">Comment les supprimer ?</h2>
            <p class="mt-2">Dans votre profil, le bouton « Supprimer le compte » efface votre compte. On vous demande votre mot de passe pour confirmer.</p>
            <dl class="mt-3 space-y-3">
                <div>
                    <dt class="font-medium text-ink">Ce qui est effacé tout de suite</dt>
                    <dd class="text-ink-2">Votre compte, vos démarches, vos signalements et soutiens, vos rendez-vous, vos messages à la mairie.</dd>
                </div>
                <div>
                    <dt class="font-medium text-ink">Ce qui est gardé, et pourquoi</dt>
                    <dd class="text-ink-2">
                        Vos remontées sur les données : gardées sans votre nom ni votre e-mail, pour garder la trace de leur traitement.
                        Les tentatives de connexion : jusqu’à {{ $joursTentatives }} jours, pour la sécurité.
                        Le journal des actions sensibles : il ne peut pas être modifié, il garde la trace des actions passées.
                        Vos avis (notifications) : ils ne sont pas encore effacés avec le compte ({{ mb_strtolower($aPreciser) }}).
                    </dd>
                </div>
            </dl>
            <div class="mt-4">
                <flux:button icon="trash" :href="route('profile.edit')">Supprimer mon compte</flux:button>
            </div>
        </x-tn.surface>

        <x-tn.surface id="une-question">
            <h2 class="tn-display text-xl font-semibold text-ink">Une question ou une inquiétude ?</h2>
            <p class="mt-2">Écrivez-nous avec vos mots. Vous recevez tout de suite un numéro de suivi. Vous voyez ensuite quand un agent l’a prise en compte, et sa réponse.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <flux:button variant="primary" icon="chat-bubble-left-ellipsis" :href="route('concerns.create')">Faire remonter une inquiétude</flux:button>
                @auth
                    <flux:button icon="list-bullet" :href="route('concerns.index')">Mes remontées</flux:button>
                @else
                    <flux:text class="self-center">Il faut être connecté pour envoyer une remontée.</flux:text>
                @endauth
            </div>
        </x-tn.surface>
    </section>
</x-layouts::public>
