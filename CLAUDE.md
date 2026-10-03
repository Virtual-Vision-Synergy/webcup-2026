# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Le contexte projet complet (compétition, stack, règles de sécurité, migrations en production, Git) est dans @CONTEXT.md ; les conventions dans @CONVENTION.md ; l'état du serveur dans @docs/PASSATION.md ; les consignes Laravel Boost dans @AGENTS.md. Réponds en français.

## Commandes

```bash
composer run dev                                   # serveur + queue:listen + Vite (laisser tourner)
php artisan test --compact                         # tous les tests (Pest)
php artisan test --compact tests/Feature/SecurityTest.php   # un fichier
php artisan test --compact --filter="nom du test"  # un seul test
vendor/bin/pint --format agent                     # formatage (la CI échoue sinon)
composer types:check                               # PHPStan (--memory-limit=1G déjà dans le script)
composer test                                      # = config:clear + pint --test + phpstan + tests (équivaut à la CI)
```

`composer ci:check` et `composer test` exécutent Pint en mode `--test` : lance d'abord `vendor/bin/pint`.

## Architecture (vue d'ensemble)

- **Pas de contrôleurs** : chaque page est un composant Livewire single-file `resources/views/pages/<slug>/{index,form,show}.blade.php` (le préfixe `⚡` apparaît sur certains fichiers, ex. `components/⚡cloche-notifications.blade.php`). Le routage des fonctionnalités est dans `routes/features.php` (groupe `auth`) ; `routes/web.php` ne porte que le socle (accueil, tableau de bord).
- **`php artisan make:feature`** (`app/Console/Commands/MakeFeature.php`, testé par `tests/Feature/MakeFeatureTest.php`) génère modèle, migration, factory, policy, 3 pages, routes, entrée de sidebar, seeder et tests. Il s'appuie sur les marqueurs `// make:feature:routes`, `{{-- make:feature:nav --}}`, `// make:feature:seeders` : ne pas les supprimer. Le dépôt ne livre plus d'exemple de fonctionnalité (`Signalement` retiré) : en générer un pour s'inspirer.
- **Boîte à outils transversale** (réutiliser avant de réécrire) :
  - `app/Services/Ai.php` : client OpenRouter côté serveur (`isConfigured()`, `ask($system, $prompt)`, retourne `null` si indisponible → prévoir un secours) ; config dans `config/services.php`.
  - `app/Services/OrgaApi.php` : client HTTP de l'API de l'organisation avec cache (`get($path, $query, $ttl)`).
  - `app/Concerns/ThrottlesPerUser.php` : `throttlePerUser($action, $max, $decay)` pour limiter les actions sensibles.
  - `app/Concerns/ExportsCsv.php` : export CSV ; `app/Models/ActionLog.php` : journal d'actions (ressource Filament `ActionLogs`).
  - `app/Models/Concerns/HasCoordinates.php` + `<x-carte>` (Leaflet) : cartes ; notifications + cloche `⚡cloche-notifications`.
  - Widget Filament `UsersStatsOverview` ; commande `app:promouvoir-admin` (rôle admin) ; `app:test-mail`.
- **Admin** : Filament 5 sur `/admin` (`app/Filament/Resources`, `Widgets`), accès par `User::canAccessPanel()` (`role = 'admin'`).
- **Temps réel** : pas de WebSockets, uniquement `wire:poll.10s.visible` (jamais sous 5 s, limite de 20 requêtes simultanées en production).
- **CI** (`.github/workflows/tests.yml`) : Pint + PHPStan + tests sur SQLite, plus un job **mariadb** obligatoire avant tout merge contenant une migration.
- **Déploiement** : `hodifly.json` ; chaque merge sur `main` part en production (migrations incluses).

## Tests

Pest, `tests/Pest.php` étend `TestCase`. Les tests de sécurité transverses sont dans `tests/Feature/SecurityTest.php` ; la boîte à outils dans `tests/Feature/Toolbox/`. Lire la skill `testing-best-practices` avant d'écrire des tests.

## Outillage Claude du dépôt

- Commandes slash dans `.claude/commands/` : `/fonctionnalite`, `/triage`, `/audit-secu`, `/recap-jury`.
- Skills dans `.claude/skills/` (Flux, Livewire, Fortify, Tailwind, bonnes pratiques Laravel, tests) : les activer quand le domaine correspond.
