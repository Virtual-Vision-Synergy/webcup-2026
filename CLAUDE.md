# Webcup 2026 — contexte projet pour Claude Code

Tu travailles avec l'équipe **Virtual Vision Synergie** (4 développeurs, Madagascar) sur le **24h by Webcup 2026** :
un hackathon où l'on construit en 24 h une **application web** sur un sujet révélé au départ.
Réponds en **français**. L'équipe découvre Laravel : explique brièvement ce que tu fais et pourquoi.

Règles et conventions détaillées : @CONVENTION.md
Organisation du week-end (rôles, workflow, board, déroulé) : `docs/guides/20-playbook-competition.md`. Cours Laravel du projet : `docs/guides/10-laravel-complet.md`. Rôles : Randy chef/intégration/déploiement, Manakasina Judicaël adjoint touche-à-tout, Tsoa (Voa-hary) exécuteur, Njaraniaina scrum master et testeuse.
Consignes officielles Laravel Boost (versions, skills, outils) : @AGENTS.md

## La compétition (ce qui guide toutes les décisions)

- Samedi 3 oct. 9h → dimanche 4 oct. 9h. ~7 fonctionnalités de base au départ, puis des fonctionnalités
  « progressives » annoncées toutes les 2-3 h. Impossible de tout faire : **la priorisation est évaluée**.
- Le jury **vérifie chaque fonctionnalité déclarée** et **teste la sécurité** (authentification, contrôle d'accès,
  validation des entrées, endpoints protégés, fuite de données, brute force, rôles). Il peut lire le code (repo public).
- Critères : fonctionnalités réellement livrées, qualité technique, design/UX, cohérence ambition/exécution.
- Conséquence : **une fonctionnalité sûre et finie vaut mieux que deux à moitié**. Ne jamais laisser une page cassée.

## Stack (versions installées — ne pas en supposer d'autres)

- Laravel 13, PHP 8.4, Livewire 4 (composants **single-file** `resources/views/pages/**/⚡nom.blade.php`),
  UI **Flux** (version gratuite : button, input, select, textarea, checkbox, table, card, badge, modal, pagination,
  heading, text, link, sidebar…), Tailwind 4, Vite.
- Auth : starter kit Livewire + Fortify (inscription, 2FA, confirmation de mot de passe).
- Admin : **Filament 5** sur `/admin`, réservé aux `role = 'admin'` (`User::canAccessPanel()`).
- Tests : **Pest**. Formatage : **Pint**.
- Base : SQLite en local, **MariaDB 10.11** en production (hébergement mutualisé cPanel Hodi, 2 Go RAM).
- Pas de nouvelle dépendance Composer/npm sans demander à l'équipe.

## Architecture

- Une fonctionnalité métier = modèle + migration + factory + **policy** + 3 pages Livewire (`index`, `form`, `show`)
  dans `resources/views/pages/<slug>/` + routes dans `routes/features.php` (groupe `auth`) + tests Pest.
- **Pour créer une fonctionnalité CRUD, utilise d'abord le générateur**, puis adapte le résultat :
  ```
  php artisan make:feature Signalement --fields="titre:string,description:text?,niveau:enum(faible/moyen/critique),photo:image?,latitude:decimal?,longitude:decimal?" --icon=exclamation-triangle
  ```
  Types : string, text, integer, decimal, boolean, date, datetime, enum(a/b/c), image. `?` = facultatif.
  Sous PowerShell, séparer les valeurs d'enum par `/` (le `|` est intercepté).
  Ensuite : `php artisan migrate`, éventuellement `php artisan make:filament-resource Nom --generate`.
- Exemple de référence déjà généré : `Signalement` (modèle, policy, pages, tests). Copie son style.
- Marqueurs utilisés par le générateur (ne pas les supprimer) :
  `// make:feature:routes` (routes/features.php), `{{-- make:feature:nav --}}` (sidebar),
  `// make:feature:seeders` (DatabaseSeeder).

## Règles de sécurité non négociables

1. Toute route métier est dans le groupe `auth` de `routes/features.php`, sauf exception publique explicite.
2. Chaque action Livewire qui lit/modifie/supprime une ressource appelle `$this->authorize(...)` (Policy).
3. Jamais d'ID venant du client sans vérification : Policy ou filtrage par propriétaire.
4. `role`, `user_id` et tout champ sensible ne sont **jamais** dans `#[Fillable]` : ils sont assignés dans le code.
5. Validation serveur systématique (`rules()` Livewire). Uploads : `image`, `mimes:jpg,jpeg,png,webp`, `max:2048`.
6. Blade : toujours `{{ }}`, jamais `{!! !!}` sur une donnée utilisateur.
7. Secrets uniquement dans `.env`. Clés API (OpenRouter…) utilisées **côté serveur seulement**.
8. Chaque nouvelle fonctionnalité a au minimum un test « le propriétaire peut » et un test « un autre utilisateur → 403 ».

## Typage

- Typer paramètres, retours de méthode et propriétés de classe.
- Propriétés de **formulaire** Livewire en `string` (les champs HTML envoient du texte), contrôlées par la validation,
  converties par les `casts` du modèle. Pas de `declare(strict_types=1)`.

## Commandes

```
composer run dev                    # serveur de dev + Vite
php artisan migrate:fresh --seed    # base locale propre + données de démo (JAMAIS en production)
vendor/bin/pint                     # formatage (obligatoire avant push, sinon la CI échoue)
php artisan test                    # tests
```
Comptes de démo locaux : `admin@example.com` / `user@example.com`, mot de passe `password`.

## Git et livraison

- Ne fais **pas** de commit, push ou merge toi-même : propose les commandes, l'équipe les lance.
- Messages de commit : `feat: …`, `fix: …`, `test: …`, `style: …`, `docs: …` (en français).
- PR : `Refs #12`, jamais `Closes #12`.
- Déploiement (fait par le chef d'équipe) : `bash ~/webcup-2026/deploy.sh` sur le serveur.
- Ne jamais modifier le bloc `AddHandler … ea-php84` de `public/.htaccess` (il active PHP 8.4 sur le serveur).

## Style de travail attendu

- Avant de coder : reformule la fonctionnalité en 2-3 lignes, liste les fichiers touchés, puis code.
- Après avoir codé : lance `vendor/bin/pint` et `php artisan test`, puis indique comment tester à la main
  (URL, compte à utiliser, ce qu'on doit voir).
- Interface en français, responsive mobile, avec états vide / chargement / erreur / succès.
- Données de démo réalistes (factory + seeder) pour chaque nouvelle entité.

## Sessions cloud (prioritaire sur la règle « pas de commit » ci-dessus)

Si tu tournes dans une session cloud (et non sur le PC de Randy) :

1. Au démarrage, lance `bash scripts/cloud-setup.sh`. Si `composer install` échoue à cause du réseau, dis-le tout de suite et continue sans lancer les tests (la CI GitHub les lancera).
2. Travaille sur une branche `feat/<sujet>` créée depuis `main`. Ne touche jamais à `main` et ne merge jamais.
3. Petits commits en français, format `type: description`.
4. Avant de pousser : `vendor/bin/pint` puis `php artisan test`. Si tu ne peux pas les lancer, écris-le dans la PR.
5. Ouvre une PR avec `Refs #<numéro d'issue>` (jamais `Closes`) et liste dans la description : ce qui a été fait, comment tester à la main, risques.
6. Une session = une fonctionnalité. Ne modifie pas `routes/features.php`, la sidebar ou le seeder à la main : passe par `make:feature` (marqueurs), pour éviter les conflits entre sessions parallèles.
