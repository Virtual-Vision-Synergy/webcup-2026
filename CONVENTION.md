# Convention de développement — Webcup 2026 (mode compétition)

> Objectif : livrer vite **des fonctionnalités qui marchent, sécurisées et cohérentes**.
> Le jury vérifie chaque fonctionnalité déclarée, teste la sécurité et peut lire ce repo.
> En cas de doute : **simple, sûr, déployé** > ambitieux, fragile, en local.

---

## 1. Stack et où vit quoi

| Quoi | Où |
|---|---|
| Laravel 13 + Livewire 4 (composants single-file) + UI Flux | tout le projet |
| Routes des pages utilisateur | `routes/web.php` |
| Modèles, relations, casts | `app/Models/` |
| Règles d'accès (qui peut voir / modifier quoi) | `app/Policies/` |
| Migrations, factories, seeders | `database/` |
| Espace admin (Filament 5) | `app/Filament/` → `/admin` |
| Tests (Pest) | `tests/Feature/` |
| Déploiement | `deploy.sh` (sur le serveur) |

- **Local** : SQLite, `http://webcup-2026.test` (Herd).
- **Production** : MariaDB, https://virtualvisionsy.madagascar.webcup.hodi.cloud

---

## 2. Recette : ajouter une fonctionnalité (~15 à 30 min)

Exemple avec une entité `Signalement` appartenant à un utilisateur.

1. **Modèle + migration + factory + seeder + policy**
   ```bash
   php artisan make:model Signalement -mfs --policy
   ```
2. **Migration** : colonnes métier + propriétaire si la donnée appartient à quelqu'un
   ```php
   $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   ```
3. **Modèle** : `$fillable` explicite (jamais `user_id`, `role`, `status` sensibles venant du formulaire), `casts`, relations.
4. **Policy** : propriétaire ou admin
   ```php
   public function update(User $user, Signalement $signalement): bool
   {
       return $user->id === $signalement->user_id || $user->isAdmin();
   }
   ```
5. **Admin (si utile)** : `php artisan make:filament-resource Signalement --generate`
6. **Page utilisateur** : composant Livewire (single-file) + route dans le groupe `auth`.
   Dans chaque action : `$this->authorize('update', $signalement);`
7. **Données de démo** : compléter la factory (Faker `fr_FR`) + le seeder.
8. **Test** : au minimum « le propriétaire peut » et « un autre utilisateur reçoit 403 ».
9. `vendor/bin/pint` → `php artisan test` → commit → push → PR.

> Un générateur `make:feature` automatisera les étapes 1 à 8 (à venir).

---

## 3. Sécurité — règles NON négociables

1. **Tout est protégé par défaut** : toute route est dans le groupe `auth`, sauf exceptions publiques explicites.
2. **Chaque action vérifie les droits** (`authorize` / Policy). Jamais « le bouton est caché donc c'est protégé ».
3. **Jamais faire confiance à un ID venant du client** : toujours passer par la Policy, ou filtrer par propriétaire
   (`auth()->user()->signalements()->findOrFail($id)`).
4. **Assignation de masse** : `$fillable` explicite. `role`, `user_id`, `is_admin`, `status` sensibles → assignés dans le code, jamais depuis la requête.
5. **Validation côté serveur systématique** (règles Livewire / FormRequest), même si le front valide déjà.
6. **Affichage** : toujours `{{ }}`. Jamais `{!! !!}` avec une donnée saisie par un utilisateur.
7. **Uploads** : `image` + `mimes:jpg,jpeg,png,webp` + `max:2048`, stockés avec un nom aléatoire.
8. **Secrets** : uniquement dans `.env` (jamais commité). Clés API (OpenRouter…) **uniquement côté serveur**.
9. **Production** : `APP_DEBUG=false`, toujours.
10. **Messages d'erreur** : génériques pour l'utilisateur (pas de trace, pas de « cet email n'existe pas »).

---

## 4. Git

- **Branches** : `feat/nom-court`, `fix/nom-court`. Petites, fusionnées vite.
- **Commits** : `feat: …`, `fix: …`, `style: …`, `test: …`, `chore: …`, `docs: …` (en français, clair).
- **Avant chaque push** : `vendor/bin/pint` puis `php artisan test`. La CI refuse le code mal formaté.
- **PR** : écrire `Refs #12`, **jamais** `Closes #12` (sinon l'issue saute l'étape « À tester sur Hodi »).
- **Merge et déploiement** : le chef d'équipe ou son adjoint uniquement.
- **Jamais** de `.env`, de mot de passe ou de clé dans un commit.

---

## 5. Définition de « terminé »

Une fonctionnalité est terminée seulement si :

- [ ] elle est **déployée sur Hodi** ;
- [ ] elle a été **testée en ligne** avec un compte jury (user **et** admin si concerné) ;
- [ ] elle fonctionne **sur mobile** ;
- [ ] les droits sont vérifiés (un autre utilisateur ne peut pas y toucher) ;
- [ ] elle est **ajoutée au récap** → l'issue est fermée (colonne « Fait & dans le récap »).

Pas terminé = pas déclaré au jury.

---

## 6. UI / UX

- Composants **Flux** en priorité, pas de CSS maison sauf nécessité.
- **Mobile d'abord** : tester chaque écran en largeur téléphone.
- Toujours prévoir : **état vide**, **chargement**, **erreur**, **message de succès**.
- Textes en **français**, clairs, cohérents avec le thème du sujet.
- Des **données de démo réalistes** partout (une app vide paraît inachevée).

---

## 7. Board et priorités (jour J)

- Colonnes : À faire → En cours → À tester sur Hodi → Fait & dans le récap.
- Priorité : **P0** base obligatoire → **P1** forte valeur jury → **P2** si on a le temps.
- Chaque annonce de l'orga → **triage de 10 min** par le chef d'équipe (ou l'adjoint si le chef dort).
- Déploiement toutes les **2-3 h minimum**. **Gel des nouvelles fonctionnalités vers H+21.**

---

## 8. Commandes utiles

```bash
# Local
composer run dev                  # lance le serveur de dev (Vite)
php artisan migrate:fresh --seed  # repart d'une base propre avec données de démo
vendor/bin/pint                   # formatage
php artisan test                  # tests

# Serveur (Terminal cPanel)
bash ~/webcup-2026/deploy.sh      # déploiement complet
cd ~/webcup-2026 && php84 artisan migrate:status
```

### Pièges connus
- **PowerShell + Herd** : écrire les contraintes Composer avec `~` et non `^` (ex. `"vendor/package:~5.0"`).
- **Serveur** : toujours `cd ~/webcup-2026` avant `artisan`.
- **`public/.htaccess`** contient le bloc qui active PHP 8.4 sur le serveur : ne jamais le supprimer.
- **Filament** : sans `implements FilamentUser` sur `User`, l'admin est ouvert à tous en local et fermé à tous en production.
