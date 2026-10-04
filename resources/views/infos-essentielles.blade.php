{{--
    F94 : page « Infos essentielles », publique (décision : elle doit rester lisible par tous pendant un incident).
    HTML simple, styles en ligne, ni JavaScript ni image : enregistrée telle quelle en fichier statique
    (App\Support\InfosEssentielles) et servie sans base de données ni session. Aucune donnée personnelle.
    $services = null : état des services inconnu (base indisponible au moment de la génération).
    $alertes (F104) : alertes graves en cours avec leurs consignes ; la page étant gardée hors ligne (F93), elles restent lisibles pendant une coupure.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Infos essentielles · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --fond: #ffffff; --texte: #111827; --doux: #4b5563; --ligne: #d1d5db; --carte: #f3f4f6; --lien: #0e7490; --alerte: #9a1238; --info: #0e7490; }
        @media (prefers-color-scheme: dark) { :root { --fond: #0b0f14; --texte: #f3f4f6; --doux: #b6bcc6; --ligne: #374151; --carte: #151b23; --lien: #5fd4e8; --alerte: #ff7aa2; --info: #5fd4e8; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fond); color: var(--texte); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 46rem; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
        h1 { font-size: 1.6rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.15rem; margin: 2rem 0 .75rem; }
        p { margin: .25rem 0; }
        a { color: var(--lien); }
        ul { list-style: none; margin: 0; padding: 0; }
        .doux { color: var(--doux); font-size: .9rem; }
        .consigne { margin-top: 1.25rem; border: 2px solid var(--info); border-radius: .5rem; padding: 1rem; }
        .consigne.alerte { border-color: var(--alerte); }
        .consigne strong { display: block; font-size: 1.1rem; }
        .consigne p { white-space: pre-line; }
        .consigne ul { list-style: disc; margin: .5rem 0 0; padding-left: 1.25rem; }
        .consigne li { margin: .25rem 0; }
        .consigne .titre { display: inline; font-size: 1rem; }
        .grille { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        .carte { background: var(--carte); border: 1px solid var(--ligne); border-radius: .5rem; padding: .75rem; }
        .numero { font-size: 1.5rem; font-weight: 700; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; }
        td { border-bottom: 1px solid var(--ligne); padding: .4rem 0; vertical-align: top; }
        td:first-child { padding-right: 1rem; font-weight: 600; }
        .etat { font-weight: 700; }
        .etat.indisponible { color: var(--alerte); }
    </style>
</head>
<body>
<main>
    <h1>Infos essentielles</h1>
    <p class="doux">Mairie de Nova Terra · page allégée, consultable même pendant un incident de la plateforme.</p>
    <p class="doux">Mise à jour le {{ $genereeLe }}.</p>

    @if ($consigne)
        <section @class(['consigne', 'alerte' => $consigne['niveau'] === 'alerte']) role="alert" data-test="consigne-incident">
            <strong>{{ $consigne['titre'] }}</strong>
            <p>{{ $consigne['message'] }}</p>
        </section>
    @else
        <section class="consigne" role="status">
            <strong>Aucune consigne particulière en cours</strong>
            <p>La mairie n’a pas publié de consigne d’incident. En cas de danger, appelez les numéros d’urgence ci-dessous.</p>
        </section>
    @endif

    @if ($alertes !== null && $alertes->isNotEmpty())
        <h2>Alertes en cours</h2>
        @foreach ($alertes as $alerte)
            <section class="consigne alerte" data-test="alerte-en-cours">
                <strong>{{ $alerte->libelleNiveau() }} — {{ $alerte->titre }}</strong>
                <p class="doux">{{ $alerte->estCiblee() ? 'Quartier '.$alerte->nomQuartier().' uniquement' : 'Toute la ville' }}</p>
                @if ($alerte->impact_prevu_le)
                    <p><strong class="titre">Début estimé de la perturbation :</strong> {{ \App\Models\Annonce::heureLisible($alerte->impact_prevu_le) }}</p>
                @endif
                <p><strong class="titre">Fin estimée de l’alerte :</strong> {{ \App\Models\Annonce::heureLisible($alerte->fin) }} (heure de Madagascar)</p>
                <p>{{ $alerte->contenu }}</p>
                @if ($alerte->listeConsignes() !== [])
                    <p><strong class="titre">Consignes à suivre</strong></p>
                    <ul>
                        @foreach ($alerte->listeConsignes() as $ligne)
                            <li>{{ $ligne }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    @endif

    <h2>Numéros d’urgence (24 h/24, gratuits)</h2>
    <ul class="grille">
        @foreach (\App\Models\Service::NUMEROS_URGENCE as $urgence)
            <li class="carte">
                <a class="numero" href="tel:{{ preg_replace('/[^0-9+]/', '', $urgence['numero']) }}">{{ $urgence['numero'] }}</a>
                <p><strong>{{ $urgence['label'] }}</strong></p>
                <p class="doux">{{ $urgence['detail'] }}</p>
            </li>
        @endforeach
    </ul>

    <h2>Coordonnées de la mairie</h2>
    <div class="carte">
        <p><strong>{{ \App\Support\InfosEssentielles::MAIRIE['nom'] }}</strong></p>
        <p>{{ \App\Support\InfosEssentielles::MAIRIE['adresse'] }}</p>
        <p>Téléphone : <a href="tel:{{ preg_replace('/[^0-9+]/', '', \App\Support\InfosEssentielles::MAIRIE['telephone']) }}">{{ \App\Support\InfosEssentielles::MAIRIE['telephone'] }}</a></p>
        <p>E-mail : <a href="mailto:{{ \App\Support\InfosEssentielles::MAIRIE['email'] }}">{{ \App\Support\InfosEssentielles::MAIRIE['email'] }}</a></p>
    </div>

    <h2>Horaires d’accueil</h2>
    <table>
        @foreach (\App\Support\InfosEssentielles::HORAIRES_MAIRIE as $horaire)
            <tr><td>{{ $horaire['jours'] }}</td><td>{{ $horaire['heures'] }}</td></tr>
        @endforeach
    </table>

    <h2>État des services</h2>
    @if ($services === null)
        <p data-test="etat-inconnu">L’état détaillé des services ne peut pas être affiché pour le moment. Appelez la mairie pour toute démarche urgente.</p>
    @elseif ($services->isEmpty())
        <p data-test="tous-disponibles">Tous les services municipaux fonctionnent normalement.</p>
    @else
        <ul>
            @foreach ($services as $service)
                <li class="carte" style="margin-bottom:.5rem">
                    <p><strong>{{ $service->nom }}</strong> — <span @class(['etat', 'indisponible' => $service->estIndisponible()])>{{ $service->libelleEtat() }}</span></p>
                    @if (filled($service->motifEtat()))
                        <p>Motif : {{ $service->motifEtat() }}</p>
                    @endif
                    <p class="doux">{{ $service->libelleRetourPrevu() }}</p>
                    @if (filled($service->alternativeTexteEtat()))
                        <p>En attendant : {{ $service->alternativeTexteEtat() }}</p>
                    @endif
                    @if ($service->telephone)
                        <p>Téléphone : <a href="tel:{{ preg_replace('/[^0-9+]/', '', $service->telephone) }}">{{ $service->telephone }}</a></p>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="doux">Les autres services fonctionnent normalement.</p>
    @endif

    <h2>Et ensuite ?</h2>
    <p>Vos démarches déjà déposées restent enregistrées. Réessayez la plateforme plus tard : <a href="/">revenir à l’accueil</a>.</p>
</main>
</body>
</html>
