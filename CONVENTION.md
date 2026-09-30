# Convention de développement — Webcup 2026 (mode compétition)

> Objectif : livrer vite **des fonctionnalités qui marchent, sécurisées et cohérentes**.
> Le jury vérifie chaque fonctionnalité déclarée, attaque la sécurité et peut lire ce repo.
> En cas de doute : **simple, sûr, déployé** > ambitieux, fragile, en local.
>
> Organisation, rôles et déroulé du week-end : `docs/guides/20-playbook-competition.md`.
> Cours Laravel avec exemples : `docs/guides/10-laravel-complet.md`.

---

## 1. Stack et où vit quoi

| Quoi | Où |
|---|---|
| Laravel 13 (PHP 8.4) + Livewire 4 (composants single-file) + Flux + Tailwind 4 | tout le projet |
| Pages des fonctionnalités (logique + vue dans un fichier) | `resources/views/pages/<entite>/⚡index|⚡form|⚡show.blade.php` |
| Routes des fonctionnalités (groupe `auth`) | `routes/features.php` |
| Routes du socle (accueil, tableau de bord, paramètres) | `routes/web.php`, `routes/settings.php` |
| Modèles, relations, casts, constantes d'options | `app/Models/` |
| Règles d'accès | `app/Policies/` |
| Migrations, factories, seeders | `database/` |
| Espace admin (Filament 5) | `app/Filament/` → `/admin` (admins uniquement) |
| Services externes (IA, API de l'orga) | `app/Services/` + `config/services.php` |
| Traductions françaises | `lang/fr.json`, `lang/fr/*.php` |
| Tests (Pest) | `tests/Feature/` |
| Générateur | `app/Console/Commands/MakeFeature.php` |
| Déploiement | `deploy.sh` (lancé sur le serveur) |

- **Local** : SQLite, `http://webcup-2026.test` (Herd, Chrome).
- **Production** : MariaDB, https://virtualvisionsy.madagascar.webcup.hodi.cloud
- **Sessions cloud Claude** : préparées automatiquement (`.claude/settings.json` → `scripts/cloud-setup.sh`).

---

## 2. Recette : ajouter une entité (5 à 30 min)

1. **Générer** (jamais de CRUD écrit à la main) :
   ```bash
   php artisan make:feature PointRegroupement --fields="nom:string,capacite:integer,ouvert:boolean,latitude:decimal?,longitude:decimal?" --label="Point de regroupement" --plural="Points de regroupement" --icon=map-pin
   php artisan migrate
   ```
   Types : `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `enum(a/b/c)`, `image`. Suffixe `?` = facultatif. Sous PowerShell, séparer les valeurs d'enum par `/`.
2. **Relire** la migration (index, `nullable`), le modèle (relations, casts), la factory (données crédibles), la policy (règles du sujet).
3. **Adapter** : relations, filtres, statut réservé à l'admin, textes, icônes.
4. **Admin** si utile : `php artisan make:filament-resource PointRegroupement --generate`, puis enums en `Select`, colonnes en badges, actions de modération.
5. **Tester** : les 6 tests générés + un test par règle métier.
6. `vendor/bin/pint` → `php artisan test` → commit → push → PR.

---

## 3. Sécurité — règles NON négociables

1. **Tout est protégé par défaut** : routes métier dans le groupe `auth` de `routes/features.php`. Une page publique est une **décision** notée dans l'issue.
2. **Chaque méthode publique** d'un composant Livewire vérifie les droits : `$this->authorize('update', $record)`. Jamais « le bouton est caché donc c'est protégé ».
3. **Jamais confiance à un ID venant du navigateur** : `findOrFail($id)` puis `authorize`, ou filtrer par propriétaire.
4. **Enregistrement du composant verrouillé** : `#[Locked] public ?Modele $record = null;`
5. **Assignation de masse** : `#[Fillable([...])]` explicite. `user_id`, `role`, `statut` réservés **jamais dedans** : assignés dans le code (`$x->user()->associate(auth()->user())`).
6. **Validation serveur** de chaque champ (`rules()`), `Rule::in(Modele::X_OPTIONS)` pour les listes.
7. **Affichage** : toujours `{{ }}`. Jamais `{!! !!}` avec une donnée saisie. Texte injecté en JS : `textContent`, jamais `innerHTML`.
8. **Uploads** : `image` + `mimes:jpg,jpeg,png,webp` + `max:2048`, stockage `->store('dossier', 'public')`.
9. **Actions sensibles limitées** (envoi, IA, signalement) : `RateLimiter` par utilisateur.
10. **Secrets** uniquement dans `.env` (jamais commité), lus via `config()`. `APP_DEBUG=false` en production.
11. **Messages d'erreur** génériques (pas de trace, pas de « cet e-mail n'existe pas »).
12. **Données personnelles** d'autrui (e-mail, téléphone) jamais affichées sans règle de Policy dédiée.

---

## 4. Code

- Nommage métier **en français** (comme le sujet) : `Signalement`, `titre`, `niveau`. Code technique Laravel en anglais (`index`, `store`, `user`).
- Typer paramètres, retours et propriétés. Propriétés de formulaire Livewire en `string` + validation + cast dans le modèle. Pas de `declare(strict_types=1)`.
- Une constante par liste d'options : `public const NIVEAU_OPTIONS = ['faible', 'moyen', 'critique'];`
- Requêtes de liste : `with([...])` pour éviter le N+1, `paginate(10)`.
- Pas de logique métier dans les vues Blade ; pas de requête dans une boucle.
- `dd()` / `dump()` retirés avant commit.
- La CI lance **Pint + PHPStan + tests** : elle doit rester verte.

---

## 5. Git

- **Branches** : `feat/nom-court`, `fix/nom-court`, depuis `main` à jour. Une branche = une issue.
- **Commits** : `feat: …`, `fix: …`, `style: …`, `test: …`, `chore: …`, `docs: …` (en français, clair, petit).
- **Avant chaque push** : `vendor/bin/pint` puis `php artisan test`.
- **PR** : titre clair, `Refs #12` (**jamais** `Closes #12`), section « Comment tester ».
- **Merge et déploiement** : Randy, ou Judicaël quand Randy dort. Personne ne merge sa propre PR (sauf correctif urgent du chef).
- **Conflits** dans `routes/features.php`, la sidebar ou le seeder : garder **les deux** blocs.
- **Jamais** de `.env`, de mot de passe ou de clé dans un commit.
- **Sessions cloud** : branche dédiée, PR, jamais de push sur `main`.

---

## 6. Définition de « terminé »

- [ ] Mergé dans `main`, CI verte
- [ ] **Déployé sur Hodi**
- [ ] **Testé en ligne par Njaraniaina** avec le compte jury user (et admin si concerné)
- [ ] Un autre utilisateur ne peut ni voir ni modifier ce qui ne le concerne pas
- [ ] Utilisable **sur téléphone**
- [ ] Ligne ajoutée à `docs/recap.md` → issue fermée (« Fait & dans le récap »)

Pas terminé = pas déclaré au jury.

---

## 7. UI / UX

- Composants **Flux** en priorité, Tailwind pour la mise en page, pas de CSS maison sauf nécessité.
- Couleur d'accent **uniquement** dans le bloc `@theme` de `resources/css/app.css` (et `Color::` dans le panneau Filament).
- **Mobile d'abord** : tester chaque écran en largeur téléphone.
- Chaque écran : **état vide** (message + action), **chargement** (`wire:loading`), **erreur** (validation en français), **succès** (`Flux::toast`).
- Textes en **français**, cohérents avec le thème. Images avec `alt`.
- **Données de démo réalistes** partout.

---

## 8. Board et priorités

- Colonnes : **À faire → En cours → À tester sur Hodi → Fait & dans le récap**.
- Priorité : **P0** base obligatoire → **P1** forte valeur jury → **P2** si le temps le permet.
- Labels : `base`, `progressive`, `sécu`, `prépa`, `bug`.
- Une seule carte « En cours » par personne.
- Triage d'une annonce : **10 min** par Randy (Judicaël s'il est de service).
- Déploiement toutes les **2-3 h**. **Gel à H+21 (6 h)**. Dernier déploiement **8 h 30**.

---

## 9. Commandes utiles

```bash
# Local
composer run dev                          # serveur de dev (laisser tourner)
php artisan make:feature ...              # générer une entité
php artisan migrate                       # appliquer les migrations
php artisan migrate:fresh --seed          # base propre + démo (LOCAL uniquement)
vendor/bin/pint                           # formatage
php artisan test                          # tests
php artisan route:list --path=xxx         # routes
php artisan optimize:clear                # vider les caches

# Serveur (Terminal cPanel)
bash ~/webcup-2026/deploy.sh              # déploiement complet
cd ~/webcup-2026 && php84 artisan migrate:status
tail -n 60 ~/webcup-2026/storage/logs/laravel.log
```

### Pièges connus
- **PowerShell** : `~` au lieu de `^` dans les contraintes Composer ; `/` au lieu de `|` dans les enums de `make:feature`.
- **Herd** : utiliser Chrome (le VPN / DoH de Firefox casse les `.test`) ; IIS arrêté.
- **Style cassé** : `composer run dev` doit tourner, sinon `npm run build`, puis Ctrl+F5.
- **Serveur** : toujours `cd ~/webcup-2026` avant `artisan` ; `php84` = PHP 8.4 (le `php` par défaut est 8.1).
- **`public/.htaccess`** contient le bloc `AddHandler … ea-php84` : ne jamais le supprimer.
- **Filament** : `User implements FilamentUser` + `canAccessPanel()` sinon l'admin est ouvert à tous en local.
- **Production** : jamais `migrate:fresh`, `db:seed` ni `key:generate`.
- **PHPStan** : si `composer ci:check` plante sur la mémoire (128M par défaut), le script `types:check` utilise déjà `--memory-limit=1G`.
