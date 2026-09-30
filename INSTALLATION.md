# Installation du projet (Windows + Herd)

Durée : 20 à 30 minutes. Suivre **dans l'ordre**, sans sauter d'étape. Si une étape échoue, noter le message exact dans l'issue « Installer le projet en local » et prévenir Randy ou Judicaël.

## 1. Outils à installer (une seule fois)

| Outil | Où | Vérifier avec |
|---|---|---|
| **Laravel Herd** (PHP 8.4, Composer, Node inclus) | herd.laravel.com → Windows | `php -v` (8.4.x), `composer -V`, `node -v` |
| **Git** | git-scm.com | `git --version` |
| **Google Chrome** | — | (Firefox pose problème avec les adresses `.test`) |
| **VS Code** + extensions *Laravel*, *PHP Intelephense*, *Tailwind CSS IntelliSense* | code.visualstudio.com | — |
| **GitHub CLI** (facultatif) | `winget install GitHub.cli` | `gh --version` |

Dans Herd : **PHP 8.4** sélectionné, bouton **Start all**. Si le port 80 est pris : arrêter IIS (`services.msc` → *World Wide Web Publishing Service* et *Windows Process Activation Service* → Arrêter, type de démarrage Manuel).

Si `php -v` affiche une autre version (8.3, 8.1…) : Herd → *PHP* → choisir 8.4 comme version globale, puis **fermer et rouvrir** PowerShell.

## 2. Récupérer le projet

Dans PowerShell :

```powershell
cd $HOME\Herd
git clone https://github.com/Virtual-Vision-Synergy/webcup-2026.git
cd webcup-2026
```

Le dossier doit être dans `Herd` : le site sera alors disponible sur **http://webcup-2026.test**.

## 3. Installer et configurer

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Ouvrir `.env` et vérifier :

```
APP_NAME="Webcup V²S"
APP_URL=http://webcup-2026.test
APP_LOCALE=fr
APP_FAKER_LOCALE=fr_FR
```

Puis :

```powershell
php artisan config:clear
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
```

`migrate:fresh --seed` crée la base SQLite **et** les comptes de test. Répondre `yes` si on vous demande de créer le fichier de base de données.

## 4. Vérifier

1. Ouvrir **http://webcup-2026.test** dans Chrome → page d'accueil.
2. Se connecter avec **user@example.com** / `password` → tableau de bord.
3. Ouvrir `/admin` avec ce compte → **403** (normal : ce n'est pas un admin).
4. Se déconnecter, se connecter avec **admin@example.com** / `password` → `/admin` s'ouvre.
5. Lancer les tests : `php artisan test` → tout est vert.

## 5. Au quotidien

```powershell
git pull                         # récupérer le travail des autres
composer install ; npm install   # si composer.json / package.json ont changé
php artisan migrate              # si de nouvelles migrations sont arrivées
composer run dev                 # lancer le projet (laisser tourner pendant qu'on code)
```

## 6. Problèmes connus

| Symptôme | Solution |
|---|---|
| `No application encryption key` / *APP_KEY missing* | `php artisan key:generate` puis `php artisan config:clear` |
| « Ce site est inaccessible » sur `.test` | Utiliser Chrome ; Herd → *Start all* ; IIS arrêté ; VPN désactivé |
| Page sans style, ou style cassé | `composer run dev` doit tourner (ou `npm run build`), puis **Ctrl+F5** |
| `Your requirements could not be resolved … php 8.3` | PHP 8.4 pas actif : voir §1, rouvrir PowerShell |
| `could not find driver (sqlite)` | Herd → PHP 8.4 → extensions → activer `pdo_sqlite` |
| Une contrainte Composer avec `^` ne marche pas | PowerShell mange `^` : écrire `~` (ex. `"vendor/paquet:~5.0"`) |
| `make:feature` : l'enum `a|b|c` échoue | PowerShell mange `|` : écrire `enum(a/b/c)` |
| `Column not found` / `no such table` | `php artisan migrate` (ou `migrate:fresh --seed` en local) |
| Photo envoyée mais invisible | `php artisan storage:link` |
| Un changement n'apparaît pas | `php artisan optimize:clear` |
| `.git/index.lock` existe | Fermer les autres outils Git, puis supprimer ce fichier |

## 7. Première contribution (pour vérifier le cycle complet)

```powershell
git checkout main
git pull
git checkout -b docs/mon-prenom
# modifier une ligne dans docs/recap.md ou README, enregistrer
vendor/bin/pint
php artisan test
git add .
git commit -m "docs: premier test de contribution"
git push -u origin docs/mon-prenom
```

Puis sur GitHub : **Compare & pull request**, écrire `Refs #<numéro de l'issue>`, créer la PR, attendre la coche verte, prévenir Randy.
