# Tsoa (Voa-hary) — Exécuteur

_Tu es le moteur de production : tu transformes les cartes du board en **fonctionnalités finies**, vite et proprement. Ton arme : le générateur `make:feature`, les composants Flux et les recettes du tome 1. Tu n'as pas à décider des priorités : tu prends la carte que Randy t'attribue et tu la mènes jusqu'à la PR._

À lire avec : tome 2 §3-§5 (convention, workflow, board) ; tome 1 chapitres 1 à 11, 15, 16, 21, 25.

## 1. Tes responsabilités

| Tu es responsable de | Concrètement |
|---|---|
| **Livrer des cartes** | Une carte à la fois, de « À faire » à la PR |
| **L'interface** | Pages claires, mobiles, en français, avec les 4 états |
| **Les données de démo** | Chaque entité a des données crédibles (le jury ne doit jamais voir une page vide) |
| **L'identité visuelle** | Couleur, logo, page d'accueil, textes cohérents avec le sujet |

## 2. Ton cycle pour chaque carte

```powershell
# 1. Partir de main à jour
git checkout main
git pull
git checkout -b feat/nom-court

# 2. Générer (si nouvelle entité)
php artisan make:feature Incident --fields="titre:string,description:text?,niveau:enum(faible/moyen/critique),photo:image?" --label="Incident" --plural="Incidents" --icon=exclamation-triangle
php artisan migrate

# 3. Adapter (textes, filtres, affichage), vérifier dans le navigateur
# 4. Vérifier
vendor/bin/pint
php artisan test

# 5. Envoyer
git add .
git commit -m "feat: signalement d'incidents avec photo"
git push -u origin feat/nom-court
```

Puis PR sur GitHub : titre clair, `Refs #12`, section « Comment tester » (URL, compte, ce qu'on doit voir). Prévenir Randy : « PR #12 prête. » Et **prendre la carte suivante** sans attendre.

## 3. Ce que le générateur fait pour toi (et ce qu'il te reste)

| Généré | À faire par toi |
|---|---|
| Migration, modèle, factory, policy, 3 pages, routes, menu, seeder, 6 tests | Relire la factory : **textes crédibles liés au sujet** |
| Liste avec recherche, filtres sur les enums, pagination, « mes éléments » | Adapter les colonnes affichées, les libellés, l'icône |
| Formulaire avec validation et upload | Ordre et libellés des champs, aides, valeurs par défaut |
| Page détail | Mise en page soignée (carte, badges, image) |

Types de champs : `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `enum(a/b/c)`, `image`. Suffixe `?` = facultatif. **Sous PowerShell : `/` entre les valeurs d'enum.**

**Options du générateur** (combinables, détail au chapitre 25 du tome 1) :

| Option | Quand l'utiliser |
|---|---|
| `--belongs-to=Zone` (répétable, `Zone?` = facultatif) | La fiche appartient à un autre modèle **déjà généré** (zone, catégorie…) : liste déroulante, filtre, nom affiché |
| `--statut=en_attente/valide/refuse` | La fiche doit être modérée : badge, filtre, bouton réservé à l'admin (le premier statut est la valeur par défaut) |
| `--public` | Liste et détail visibles sans compte (lecture seule) |
| `--filament` | L'admin doit gérer la fiche dans `/admin` |

```powershell
php artisan make:feature Zone --fields="nom:string" --icon=map
php artisan make:feature Incident --fields="titre:string,description:text?,photo:image?" --belongs-to=Zone --statut=en_attente/valide/refuse --public --filament --icon=exclamation-triangle
php artisan migrate
```

Génère d'abord le modèle lié (`Zone`), puis la fiche. Après génération, relire la migration, la policy et la factory, puis lancer `vendor/bin/pint` et `php artisan test`.

## 4. Les règles d'interface (le jury les voit en premier)

- Composants **Flux** : `flux:heading`, `flux:button`, `flux:input`, `flux:select`, `flux:table`, `flux:card`, `flux:badge`, `flux:modal` (tome 1 §11).
- **4 états** sur chaque écran : vide (message + bouton), chargement (`wire:loading`), erreur (messages en français), succès (`Flux::toast(...)`).
- **Mobile d'abord** : `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`. Tester en largeur téléphone (F12 → mode responsive).
- Images : `alt` toujours, `object-cover`, pas d'image lourde.
- Boutons « Modifier / Supprimer » dans `@can('update', $item)` … `@endcan`.
- Textes : français, sans faute, vocabulaire du sujet.

## 5. Les pièges à éviter

| Piège | Pourquoi c'est grave | Correct |
|---|---|---|
| Ajouter `user_id` ou `statut` à `#[Fillable]` pour « que ça marche » | Faille : le jury se donne les droits | Assigner dans le code (tome 1 §12) |
| Supprimer un `$this->authorize(...)` qui « bloque » | Faille : n'importe qui modifie tout | Corriger la Policy, demander à Judicaël |
| `{!! $x !!}` pour afficher du HTML | Faille XSS | `{{ $x }}` |
| Modifier une migration déjà déployée | La production ne verra pas le changement | Nouvelle migration |
| Rester bloqué 1 h | On perd une fonctionnalité | Au bout de 20 min, demander à Judicaël ou Claude |
| Travailler sur `main` | Conflits, déploiement cassé | Toujours une branche |

## 6. Utiliser Claude Code efficacement

- `/fonctionnalite <texte exact de la carte>` : il suit la convention, écrit les tests, te dit comment tester.
- Relis ce qu'il produit : tu es responsable de la PR.
- Si quelque chose ne marche pas, colle-lui **l'erreur exacte** (20 dernières lignes de `storage/logs/laravel.log`), pas « ça marche pas ».
- `/clear` entre deux fonctionnalités.

## 7. Ta journée

| Heure | Toi |
|---|---|
| 9 h → 15 h | Fonctionnalités de base (entités, pages), identité visuelle, page d'accueil |
| 15 h → 21 h | Fonctionnalités progressives |
| **21 h → 0 h 30** | **Dormir** |
| 0 h 30 → 6 h | Progressives (tu es un des deux réveillés entre 2 h et 4 h) |
| 6 h → 8 h | Finitions : mobile, textes, états vides, données de démo |
| 8 h 30 | Test sur téléphone après le dernier déploiement |

## 8. Préparation (avant samedi)

Voir tes issues `prépa` : installation, première PR, Lighthouse, simulation de jeudi. Fais les **exercices du tome 1** : c'est là que tu gagnes ta vitesse de samedi.
