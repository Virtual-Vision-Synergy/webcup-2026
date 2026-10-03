# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Réponds en **français** : l'équipe (Virtual Vision Synergie, 4 développeurs, 24h by Webcup 2026) découvre Laravel.

## À lire avant de coder

| Fichier | Contenu |
|---|---|
| @CONTEXT.md | Contexte compétition, rôles, règles de sécurité non négociables, règles Git (**pas de commit/merge toi-même**), consignes des sessions cloud |
| @CONVENTION.md | Convention détaillée : recette d'ajout d'entité, sécurité, UI/UX, board, pièges connus |
| @docs/PASSATION.md | État réel du projet et du serveur (mis à jour le 2/10/2026) |
| @AGENTS.md | Consignes Laravel Boost (versions, skills, outils MCP) |
| `docs/guides/09-fondations.md` | Tome 0 : la mécanique du projet depuis zéro (cycle de vie d'une requête, protocole Livewire et sécurité qui en découle) |
| `docs/guides/10-laravel-complet.md` | Cours Laravel du projet, avec le chapitre sur le générateur |

Note : le hook `SessionStart` de `.claude/settings.json` renvoie vers « CLAUDE.md (section Sessions cloud) » — cette section vit en fait dans `CONTEXT.md`.

## Commandes

```bash
composer run dev                  # serve + queue:listen + vite (à laisser tourner)
composer test                     # = config:clear + pint --test + phpstan + artisan test  → ce que lance la CI
composer lint                     # pint --parallel (corrige)
composer types:check              # phpstan --memory-limit=1G (128M ne suffit pas)
php artisan test --compact        # tests seuls
vendor/bin/pest --filter="nom du test"
vendor/bin/pest tests/Feature/SignalementTest.php
npm run build                     # vite-plus (`vp`), pas vite directement
```

`php artisan test` seul **ne suffit pas** avant un push : la CI échoue aussi sur Pint et PHPStan. Elle rejoue
en plus toute la suite sur MariaDB 10.11 (la base du serveur) en complément de SQLite.

## Architecture

**Une fonctionnalité = une entité générée, pas du CRUD écrit à la main.**
`php artisan make:feature Nom --fields="..."` (`app/Console/Commands/MakeFeature.php`, ~1150 lignes) écrit en une
passe : migration, modèle, factory, seeder, policy, les 3 pages Livewire, 6 tests Pest — puis **insère** ses blocs
aux marqueurs `// make:feature:routes` (`routes/features.php`), `{{-- make:feature:nav --}}`
(`resources/views/layouts/app/sidebar.blade.php`) et `// make:feature:seeders` (`DatabaseSeeder`). Ne jamais
supprimer ces marqueurs ni éditer ces trois endroits à la main (conflits entre sessions parallèles).
`Signalement` est l'exemple de référence : copier son style.

**Livewire 4 en composants single-file.** Pas de classes dans `app/Livewire` : la logique et la vue sont dans
`resources/views/pages/<slug>/index|form|show.blade.php` (`new #[Title('…')] class extends Component {}` en
tête de fichier). Les routes les montent par leur chemin de vue : `Route::livewire('signalements', 'pages::signalements.index')`.
Les noms de fichiers contiennent un `` : les citer dans le shell.

**Trois fichiers de routes** : `routes/web.php` (accueil, dashboard, et `require` des deux autres),
`routes/settings.php` (profil / apparence / sécurité, Fortify), `routes/features.php` (tout le métier, groupe `auth`).

**Couches de sécurité**, dans cet ordre : groupe `auth` → `$this->authorize()` dans **chaque** méthode publique du
composant (pas seulement `mount`) → Policy (`app/Policies/`) → `#[Locked] public ?Modele $record` → `rules()` serveur →
`#[Fillable([...])]`. Les modèles utilisent l'attribut PHP `#[Fillable]`/`#[Hidden]` de Laravel 13, pas les propriétés
`$fillable`/`$hidden` ; `user_id`, `role`, `statut` n'y figurent jamais et sont assignés dans le code.
`App\Http\Middleware\SecurityHeaders` est appliqué globalement dans `bootstrap/app.php`.

**Admin Filament 5** sur `/admin`, réservé par `User::canAccessPanel()` → `isAdmin()` (`role === 'admin'`).
Un resource = un dossier `app/Filament/Resources/<Pluriel>/` avec `Pages/`, `Schemas/` (formulaires) et `Tables/`.

**Boîte à outils réutilisable** (PR #34) — préférer ces briques à du code neuf :
`app/Concerns/ExportsCsv` (`streamCsv()`, autorisation obligatoire), `ThrottlesPerUser` (`throttlePerUser('ia', 5, 60)`
pour toute action sensible), `app/Services/Ai` (OpenRouter, renvoie `null` au lieu de lever, cache 1 h),
`app/Services/OrgaApi` (renvoie `[]` en cas d'échec), `ActionLog::record('deleted', $modele)`,
`app/Models/Concerns/HasCoordinates` + `<x-carte>` (Leaflet, modes `lecture` / `choix`).
Ces services dégradent silencieusement quand ils ne sont pas configurés : la page doit afficher un état de repli.

**Tests** : Pest, `tests/Feature/` uniquement, `RefreshDatabase` appliqué automatiquement par `tests/Pest.php`
(ne pas le réimporter). `Tests\TestCase::skipUnlessFortifyHas()` sert aux tests qui dépendent d'une option Fortify.
Minimum par fonctionnalité : « le propriétaire peut » **et** « un autre utilisateur → 403 ».

**Couleur d'accent** : uniquement dans le bloc `@theme` de `resources/css/app.css` (et `Color::` dans
`AdminPanelProvider`). Elle est en `amber` provisoire, à changer le jour J selon le sujet.
