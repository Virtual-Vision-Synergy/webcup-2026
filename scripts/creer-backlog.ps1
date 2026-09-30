# Crée le backlog de préparation Webcup 2026 dans GitHub (issues + projet #17).
#
# Prérequis (une seule fois) :
#   winget install GitHub.cli        (ou https://cli.github.com)
#   gh auth login
#   gh auth refresh -s project       (droit d'écrire dans les projets)
#
# Usage, depuis le dossier du projet :
#   powershell -ExecutionPolicy Bypass -File scripts\creer-backlog.ps1
#
# Le script peut être relancé : une issue dont le titre existe déjà est ignorée.

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$OutputEncoding = [Text.Encoding]::UTF8

$Owner   = 'Virtual-Vision-Synergy'
$Repo    = "$Owner/webcup-2026"
$Project = 17

# Identifiants GitHub des membres. Laisser '' si inconnu : l'issue sera créée sans responsable.
$Comptes = @{
    'Randy'       = 'And-matia'
    'Judicael'    = ''
    'Tsoa'        = ''
    'Njaraniaina' = ''
}

# ---------------------------------------------------------------------------
# Backlog : Titre, Qui (clé de $Comptes, plusieurs séparées par une virgule), Priorité, Échéance, Critère de fin, Détails
# ---------------------------------------------------------------------------
$Taches = @(
    # Mercredi 30/09
    @{ T='[Mer] Question Discord : code générique préparé, fuseau, push après 9 h, app en ligne pendant l''évaluation'; Qui='Randy'; P='P0'; D='Mer 30/09 12 h'
       Fin='Réponse de l''orga copiée dans cette issue'
       Det="Message proposé dans le playbook (§1.6 et §11). Si le code préparé est interdit : on retire les modules et on garde socle + guides." },
    @{ T='[Mer] Page d''accueil et identité visuelle'; Qui='Randy'; P='P1'; D='Mer 30/09 18 h'
       Fin='PR mergée, déployée, testée sur téléphone'
       Det="Session cloud (prompt « Session A » de la discussion). Couleur d'accent centralisée dans resources/css/app.css, textes dans un seul tableau." },
    @{ T='[Mer] Tester le nouveau deploy.sh (sauvegarde + contrôle de santé)'; Qui='Randy'; P='P0'; D='Mer 30/09 20 h'
       Fin='Un déploiement réussi : fichier dans ~/backups et « /up -> 200 OK »'
       Det="Le script écrit aussi un journal dans ~/deploy.log. Vérifier que mysqldump fonctionne sur Hodi." },
    @{ T='[Mer] Cron schedule:run sur cPanel'; Qui='Randy'; P='P1'; D='Mer 30/09 20 h'
       Fin='Une tâche planifiée de test s''exécute en ligne'
       Det="cPanel > Tâches Cron, chaque minute : cd ~/webcup-2026 && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1" },
    @{ T='[Mer] APP_LOCALE=fr en production'; Qui='Randy'; P='P1'; D='Mer 30/09 20 h'
       Fin='Site en ligne entièrement en français'
       Det="Ajouter APP_LOCALE=fr et APP_FAKER_LOCALE=fr_FR au .env du serveur, puis php84 artisan optimize." },
    @{ T='[Mer] Réunion d''équipe 21 h (30 min)'; Qui='Njaraniaina'; P='P0'; D='Mer 30/09 21 h 30'
       Fin='Décisions notées dans cette issue'
       Det="Ordre du jour : rôles, lieu du week-end, horaires de sommeil, achats, simulation de jeudi, lecture du playbook." },
    @{ T='[Mer] Installer le projet en local'; Qui='Judicael,Tsoa,Njaraniaina'; P='P0'; D='Mer 30/09 23 h'
       Fin='Connexion OK et /signalements s''affiche en local'
       Det="Suivre INSTALLATION.md à la lettre. Noter ici tout problème rencontré." },
    @{ T='[Mer] Premier cycle complet : une petite PR chacun'; Qui='Judicael,Tsoa'; P='P1'; D='Mer 30/09 23 h 59'
       Fin='PR mergée par Randy, CI verte'
       Det="Exemple : corriger un texte, ajouter une ligne au README. But : branche > commit > push > PR > CI > merge." },

    @{ T='[Mer] Kit e-mail et notifications (session cloud)'; Qui='Randy'; P='P0'; D='Mer 30/09 22 h'
       Fin='PR mergée ; « mot de passe oublié » reçu en français dans une vraie boîte, depuis le site en ligne'
       Det="Prompt « Session 1 » du 30/09. En production : MAIL_MAILER=sendmail, MAIL_FROM_ADDRESS=noreply@virtualvisionsy.madagascar.webcup.hodi.cloud, QUEUE_CONNECTION=sync. Test : php84 artisan app:test-mail <adresse>." },
    @{ T='[Mer] Kit carte GPS (session cloud)'; Qui='Judicael'; P='P1'; D='Jeu 01/10 12 h'
       Fin='PR mergée ; carte, choix d''un point et « me localiser » testés sur téléphone en ligne'
       Det="Prompt « Session 2 » du 30/09 : composant <x-carte>, trait HasCoordinates, intégration au générateur quand latitude/longitude existent." },
    @{ T='[Mer] Bascule du déploiement sur Hodifly'; Qui='Randy'; P='P0'; D='Mer 30/09 23 h'
       Fin='Un push sur main se déploie seul ; un retour arrière testé ; site OK'
       Det="hodifly.json est dans le repo. Variables d'environnement à saisir dans le projet Hodifly (DB_*, APP_KEY actuelle du serveur, APP_LOCALE=fr, MAIL_*, QUEUE_CONNECTION=sync, OPENROUTER_*). Aperçus de PR désactivés (ils partagent la base de production). deploy.sh reste en secours." },
    @{ T='[Mer] CI : tests aussi sur MariaDB 10.11'; Qui='Randy'; P='P1'; D='Mer 30/09 23 h'
       Fin='Les deux jobs (ci, mariadb) sont verts sur une PR'
       Det="Déplacer docs/github-templates/tests.yml vers .github/workflows/tests.yml." },
    # Jeudi 01/10
    @{ T='[Jeu] Lire le tome 1 (Laravel) et son guide de rôle'; Qui='Randy,Judicael,Tsoa,Njaraniaina'; P='P0'; D='Jeu 01/10 18 h'
       Fin='Exercices du tome 1 faits'
       Det="docs/guides/10-laravel-complet.pdf + le guide de son rôle." },
    @{ T='[Jeu] Générateur : options --belongs-to, --statut, --public, ressource Filament'; Qui='Judicael'; P='P1'; D='Jeu 01/10 18 h'
       Fin='Tests verts, doc du générateur à jour'
       Det="Avec une session cloud Claude. Conditionné à la réponse de l'orga (outillage générique = OK a priori)." },
    @{ T='[Jeu] OpenRouter : compte, clé sur le serveur, modèle de secours'; Qui='Judicael'; P='P1'; D='Jeu 01/10 18 h'
       Fin='Un appel test réussi depuis le serveur'
       Det="Clé uniquement dans le .env du serveur (OPENROUTER_API_KEY, OPENROUTER_MODEL). Décider d'un crédit (50 -> 1000 requêtes/jour)." },
    @{ T='[Jeu] Envoi d''e-mail testé sur le serveur'; Qui='Judicael'; P='P2'; D='Jeu 01/10 18 h'
       Fin='E-mail de test reçu'
       Det="MAIL_MAILER=sendmail sur Hodi, envoi via tinker." },
    @{ T='[Jeu] Lighthouse mobile >= 90 (accueil + une liste)'; Qui='Tsoa'; P='P1'; D='Jeu 01/10 18 h'
       Fin='4 scores notés ici, corrections faites'
       Det="Chrome > F12 > Lighthouse > Mobile, en navigation privée, sur l'URL en ligne." },
    @{ T='[Jeu] Comptes jury en production'; Qui='Randy'; P='P0'; D='Jeu 01/10 20 h'
       Fin='Connexion user et admin OK en ligne'
       Det="jury@... (user) et jury-admin@... (admin). Mots de passe uniques >= 12 caractères, gardés hors de GitHub jusqu'au README final." },
    @{ T='[Jeu] Modèles : README jury, récap, script vidéo'; Qui='Njaraniaina'; P='P1'; D='Jeu 01/10 20 h'
       Fin='docs/recap.md et docs/modeles/README-jury.md relus et complétés'
       Det="Partir des fichiers déjà créés et du playbook §9." },
    @{ T='[Jeu] OBS installé, enregistrement test de 30 s'; Qui='Njaraniaina'; P='P1'; D='Jeu 01/10 20 h'
       Fin='Vidéo test lisible, son correct'
       Det="Écran + micro, export MP4." },
    @{ T='[Jeu] Simulation 21 h (sujet d''entraînement, 2 h) + rétro'; Qui='Randy,Judicael,Tsoa,Njaraniaina'; P='P0'; D='Jeu 01/10 23 h 30'
       Fin='Leçons de la rétro notées ici'
       Det="Dérouler le playbook à l'identique : lancement, triage, issues, PR, merge, déploiement, recette, récap." },
    @{ T='[Jeu] Gel de la préparation'; Qui='Randy'; P='P0'; D='Jeu 01/10 minuit'
       Fin='Plus aucun nouveau module après minuit'
       Det="Tout module non fini et non testé est retiré." },

    @{ T='[Jeu] Crédit OpenRouter (1000 requêtes/jour pendant l''évaluation)'; Qui='Judicael'; P='P1'; D='Ven 02/10'
       Fin='Quota à 1000/jour confirmé sur openrouter.ai'
       Det="Le jury testera l'IA pendant 5 jours : 50 requêtes/jour ne suffisent pas." },
    @{ T='[Ven] Surveillance du site (UptimeRobot)'; Qui='Njaraniaina'; P='P2'; D='Ven 02/10'
       Fin='Alerte e-mail reçue lors d''un test'
       Det="Moniteur HTTP(S) gratuit sur l'URL du site et sur /up, toutes les 5 min, alerte à l'équipe." },
    # Vendredi 02/10
    @{ T='[Ven] Retirer l''exemple Signalement'; Qui='Randy'; P='P0'; D='Ven 02/10 18 h'
       Fin='Déployé ; le repo ne contient aucune entité métier'
       Det="Supprimer fichiers + lignes dans routes/features.php, sidebar, seeder. Nouvelle migration qui supprime la table signalements (pas de migrate:fresh en production)." },
    @{ T='[Ven] Régénérer les PDF des guides'; Qui='Randy'; P='P1'; D='Ven 02/10 18 h'
       Fin='PDF à jour dans docs/guides/'
       Det="Après le gel, pour qu'ils décrivent exactement le code final." },
    @{ T='[Ven] Logistique du week-end'; Qui='Njaraniaina'; P='P0'; D='Ven 02/10 18 h'
       Fin='Liste cochée'
       Det="Lieu, repas, eau, café, rallonges, multiprises, partage 4G, chargeurs, batterie externe, casque/micro." },
    @{ T='[Ven] Test à vide : déploiement, site, admin, CI'; Qui='Randy'; P='P0'; D='Ven 02/10 20 h'
       Fin='Tout est vert'
       Det="deploy.sh sans changement, site en navigation privée, /admin, dernière CI verte, sessions cloud qui démarrent." }
)

# ---------------------------------------------------------------------------

# Appelle gh et renvoie uniquement la sortie standard. Lève une erreur si gh échoue.
function Invoke-Gh {
    $ancien = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $sortie = & gh @args 2>&1
    $ErrorActionPreference = $ancien
    if ($LASTEXITCODE -ne 0) { throw "gh $($args -join ' ') : $($sortie | Out-String)" }
    return @($sortie | Where-Object { $_ -isnot [System.Management.Automation.ErrorRecord] } | ForEach-Object { "$_" })
}

Write-Host '==> Vérification de gh'
Invoke-Gh auth status | Out-Null

Write-Host '==> Labels'
$labels = @(
    @{ n = 'prépa';       c = 'FBCA04'; d = 'Préparation avant le jour J' },
    @{ n = 'base';        c = '0E8A16'; d = 'Fonctionnalité de base (départ)' },
    @{ n = 'progressive'; c = '1D76DB'; d = 'Fonctionnalité annoncée pendant le concours' },
    @{ n = 'sécu';        c = 'B60205'; d = 'Dimension sécurité' },
    @{ n = 'bug';         c = 'D93F0B'; d = 'Quelque chose ne marche pas' }
)
foreach ($l in $labels) { Invoke-Gh label create $l.n --repo $Repo --color $l.c --description $l.d --force | Out-Null }

Write-Host '==> Projet et champs'
$proj   = (Invoke-Gh project view $Project --owner $Owner --format json) -join "`n" | ConvertFrom-Json
$fields = ((Invoke-Gh project field-list $Project --owner $Owner --format json --limit 50) -join "`n" | ConvertFrom-Json).fields
$prioField   = $fields | Where-Object { $_.name -match '^Priorit' } | Select-Object -First 1
$statusField = $fields | Where-Object { $_.name -eq 'Status' } | Select-Object -First 1
if (-not $prioField)   { Write-Warning 'Champ « Priorité » introuvable : priorités non renseignées.' }
if (-not $statusField) { Write-Warning 'Champ « Status » introuvable : colonnes non renseignées.' }
$todoOption = $null
if ($statusField) { $todoOption = $statusField.options | Where-Object { $_.name -match '^À faire|^A faire|^Todo' } | Select-Object -First 1 }

Write-Host '==> Issues existantes'
$existantes = ((Invoke-Gh issue list --repo $Repo --state all --limit 300 --json title) -join "`n" | ConvertFrom-Json).title

$utf8 = New-Object Text.UTF8Encoding $false
$tmp  = [IO.Path]::GetTempFileName()
$crees = 0

foreach ($t in $Taches) {
    if ($existantes -contains $t.T) { Write-Host "  = existe déjà : $($t.T)"; continue }

    $noms = $t.Qui -split ','
    $body = @"
**Responsable** : $($noms -join ', ')
**Échéance** : $($t.D)
**Priorité** : $($t.P)

### Détails
$($t.Det)

### Terminé quand
- [ ] $($t.Fin)

_Backlog de préparation — voir docs/guides/20-playbook-competition (§11)._
"@
    [IO.File]::WriteAllText($tmp, $body, $utf8)

    $ghArgs = @('issue', 'create', '--repo', $Repo, '--title', $t.T, '--body-file', $tmp, '--label', 'prépa')
    foreach ($n in $noms) { $login = $Comptes[$n.Trim()]; if ($login) { $ghArgs += @('--assignee', $login) } }

    try {
        $url = (Invoke-Gh @ghArgs | Select-Object -Last 1).ToString().Trim()
    } catch {
        Write-Warning "Échec avec responsables, nouvel essai sans : $($t.T)"
        $ghArgs = @('issue', 'create', '--repo', $Repo, '--title', $t.T, '--body-file', $tmp, '--label', 'prépa')
        $url = (Invoke-Gh @ghArgs | Select-Object -Last 1).ToString().Trim()
    }

    $item = (Invoke-Gh project item-add $Project --owner $Owner --url $url --format json) -join "`n" | ConvertFrom-Json

    if ($prioField) {
        $opt = $prioField.options | Where-Object { $_.name -like "$($t.P)*" } | Select-Object -First 1
        if ($opt) { Invoke-Gh project item-edit --id $item.id --project-id $proj.id --field-id $prioField.id --single-select-option-id $opt.id | Out-Null }
    }
    if ($todoOption) {
        Invoke-Gh project item-edit --id $item.id --project-id $proj.id --field-id $statusField.id --single-select-option-id $todoOption.id | Out-Null
    }

    $crees++
    Write-Host "  + $url  $($t.T)"
}

Remove-Item $tmp -ErrorAction SilentlyContinue
Write-Host "==> Terminé : $crees issue(s) créée(s)."
