# Tome 0 — Les fondations : comment ce projet fonctionne vraiment

_Pour les 4 membres, et d'abord pour ceux qui n'ont jamais écrit une ligne de PHP. Ce document n'explique pas **comment écrire** une fonctionnalité (c'est le rôle du tome 1) : il explique **ce qui se passe** quand on ouvre une page de notre application, pièce par pièce, de la porte d'entrée jusqu'au HTML. Une fois ces fondations posées, les recettes du tome 1 deviennent évidentes au lieu d'être des formules à recopier._

## 0. Comment lire ce document

Chaque chapitre suit le même plan que le tome 1 : **à quoi ça sert → ce que fait notre code → les erreurs fréquentes**. Tous les exemples sont du vrai code de `webcup-2026`, avec son chemin : vous pouvez ouvrir le fichier à côté.

**Ordre de lecture conseillé** : ce tome 0 en entier (1 h 30), puis le tome 1 (`10-laravel-complet.md`) chapitres 1 à 12 et 25, puis le tome 2 (`20-playbook-competition.md`) pour l'organisation du week-end.

Trois chapitres sont **essentiels** : le **2** (le voyage d'une requête), le **3** (le protocole Livewire, qui explique *pourquoi* nos règles de sécurité existent) et le **12** (le réflexe de débogage). Si vous ne lisez que trois chapitres, ce sont ceux-là.

---

## 1. Les briques, avant Laravel

### À quoi ça sert

Cinq outils travaillent ensemble. Les confondre est la première source de perte de temps.

**PHP** est un langage exécuté **sur le serveur**. Point capital, et contre-intuitif quand on vient de JavaScript : à chaque requête, PHP démarre, construit la page, l'envoie, puis **oublie tout**. Aucune variable ne survit d'une requête à la suivante. C'est pour cela qu'il existe une **base de données** (mémoire longue) et une **session** (mémoire d'un visiteur entre deux requêtes, un petit fichier côté serveur relié au navigateur par un cookie). Quand vous lisez « l'utilisateur connecté », cela veut dire : « un cookie a été envoyé, PHP a retrouvé la session correspondante, et y a lu un identifiant ».

**Laravel** est un *framework* : une bibliothèque de code déjà écrit (routage, base de données, sécurité, e-mails…) qui impose en échange une organisation de dossiers et des conventions de nommage. Notre dépôt ne contient presque que les 5 % spécifiques à notre application ; les 95 % restants sont dans `vendor/`.

**Composer** est le gestionnaire de paquets de PHP. `composer.json` liste nos dépendances (Laravel 13, Livewire 4, Flux, Filament 5…) ; `composer install` les télécharge dans `vendor/`. Il génère aussi l'*autoload* : la correspondance automatique entre un nom de classe et un fichier. Ce bloc de `composer.json` :

```json
"autoload": { "psr-4": { "App\\": "app/" } }
```

dit : « la classe `App\Models\Signalement` se trouve dans `app/Models/Signalement.php` ». D'où la règle : **un fichier = une classe, et le chemin du fichier doit refléter le `namespace`**. Un `namespace` mal écrit en haut d'un fichier, et la classe devient introuvable.

**npm + Vite** s'occupent de la moitié navigateur : ils transforment `resources/css/app.css` et `resources/js/*.js` en fichiers optimisés dans `public/build/`. Voir le chapitre 6.

**`php artisan`** est la ligne de commande du projet : créer un fichier, appliquer les migrations, lister les routes, lancer les tests. `php artisan list` affiche tout ce qui est disponible.

**Herd** installe PHP 8.4 et un serveur web local, et sert automatiquement tout dossier placé dans `~/Herd` à l'adresse `http://<nom-du-dossier>.test` — d'où `http://webcup-2026.test`.

### Ce qu'on ne modifie jamais

```
vendor/          ← code de Laravel et des paquets (régénéré par composer install)
node_modules/    ← paquets JS (régénéré par npm install)
public/build/    ← CSS et JS compilés (régénéré par npm run build)
storage/         ← fichiers temporaires, vues compilées, journaux, uploads
database/database.sqlite  ← notre base locale (jetable : migrate:fresh --seed la recrée)
```

Ces dossiers sont dans `.gitignore` (sauf `storage/` partiellement) : modifier un fichier dedans ne sert à rien, il sera écrasé.

### Erreurs fréquentes

- `Class "App\Models\Truc" not found` → `namespace` ou nom de fichier incohérents, ou il faut lancer `composer dump-autoload`.
- `php -v` affiche 8.1 ou 8.3 → Herd n'est pas réglé sur PHP 8.4, ou PowerShell n'a pas été rouvert. Sur le **serveur**, la commande est `php84`, pas `php` (le `php` par défaut y est en 8.1).
- Sous PowerShell, `^` est avalé dans les contraintes Composer : écrire `~5.0` au lieu de `^5.0`.

---

## 2. Le voyage complet d'une requête

### À quoi ça sert

Comprendre ce chapitre, c'est savoir **où aller chercher** quand quelque chose ne marche pas. Suivons une requête réelle : un utilisateur connecté ouvre `http://webcup-2026.test/signalements`.

```
Navigateur
   │  GET /signalements
   ▼
public/index.php ......................... 1. porte d'entrée unique
   │  vendor/autoload.php                     (branche l'autoload Composer)
   ▼
bootstrap/app.php ........................ 2. construction de l'application
   │  withRouting / withMiddleware / withExceptions
   ▼
bootstrap/providers.php .................. 3. démarrage des services
   │  AppServiceProvider, AdminPanelProvider, FortifyServiceProvider
   ▼
pile de middlewares ...................... 4. filtres traversés dans l'ordre
   │  cookies → session → CSRF → auth → SecurityHeaders
   ▼
routes/web.php → routes/features.php ..... 5. quelle URL = quel composant
   │  Route::livewire('signalements', 'pages::signalements.index')
   ▼
composant Livewire ....................... 6. mount() : notre code démarre
   │  resources/views/pages/signalements/⚡index.blade.php
   ▼
Policy ................................... 7. a-t-il le droit ? sinon 403
   │  app/Policies/SignalementPolicy.php
   ▼
Eloquent → base de données ............... 8. lecture des données (SQL généré)
   │  Signalement::query()->with('user')->latest()->paginate(10)
   ▼
Blade + Flux ............................. 9. rendu du HTML dans le layout
   │  compilé en PHP dans storage/framework/views/
   ▼
réponse HTTP ............................. 10. en-têtes de sécurité, puis HTML
```

### Ce que fait notre code, étape par étape

**1. `public/index.php` — la porte d'entrée unique.** Toutes les URL du site passent par ce seul fichier (le serveur web y redirige tout, via `public/.htaccess`). Il fait quatre choses : vérifier le mode maintenance, charger l'autoload de Composer, construire l'application, et lui passer la requête.

```php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->handleRequest(Request::capture());
```

Conséquence pratique : **le dossier public est la seule partie visible depuis Internet**. Tout le reste (`app/`, `.env`, `vendor/`) est au-dessus de la racine web et donc inaccessible par URL. C'est pour cela qu'on ne met jamais un fichier sensible dans `public/`.

**2. `bootstrap/app.php` — la configuration de l'application.** Trois réglages chez nous :

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',                       // URL de supervision, répond sans authentification
)
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(SecurityHeaders::class);   // ajouté à TOUTES les réponses
})
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

**3. `bootstrap/providers.php` — les services qui démarrent avec l'application.** Un *provider* est une classe qui s'exécute au démarrage pour régler ou enregistrer quelque chose. Nous en avons trois :

- `AppServiceProvider` : les réglages généraux, dans `configureDefaults()`. Lisez-le, c'est court et instructif — il montre comment on durcit la production sans gêner le développement local :
  ```php
  Date::use(CarbonImmutable::class);                       // dates non modifiables par erreur
  if (app()->isProduction()) { URL::forceScheme('https'); } // jamais de lien http en ligne
  DB::prohibitDestructiveCommands(app()->isProduction());   // migrate:fresh REFUSÉ en production
  Password::defaults(...);                                  // 12 caractères mixtes en production, libre en local
  ```
  La troisième ligne est un garde-fou réel : même si quelqu'un lance `migrate:fresh` sur le serveur, Laravel refuse.
- `AdminPanelProvider` : construit l'espace admin Filament sur `/admin` (chapitre 7).
- `FortifyServiceProvider` : branche l'authentification (connexion, inscription, mot de passe oublié, 2FA).

**4. Les middlewares — les filtres.** Un *middleware* est une couche que la requête traverse avant d'atteindre notre code, et que la réponse retraverse en sortant. Les principaux, dans l'ordre : déchiffrement des cookies → démarrage de la **session** → vérification du **jeton CSRF** sur les requêtes POST → `auth` (déclaré dans `routes/features.php`) qui redirige vers `/login` si personne n'est connecté → et, en sortie, notre `SecurityHeaders` qui pose sur **chaque** réponse :

```php
X-Frame-Options: SAMEORIGIN                 // le site ne peut pas être affiché dans une iframe étrangère
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(self)
Strict-Transport-Security: ...              // en production seulement
```

Le jury vérifie ces en-têtes : ils sont déjà en place, ne les retirez pas.

**5. Le routeur.** `routes/web.php` est le point d'entrée des routes ; il déclare l'accueil et le tableau de bord, puis charge les deux autres fichiers :

```php
Route::view('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
require __DIR__.'/settings.php';   // profil, apparence, sécurité (Fortify)
require __DIR__.'/features.php';   // TOUT le métier, dans le groupe auth
```

Ce découpage en trois fichiers n'est pas cosmétique : il garantit que **toute nouvelle fonctionnalité atterrit par défaut dans un fichier entièrement protégé par `auth`**. On ne peut pas oublier la protection, il faut explicitement sortir du groupe pour l'enlever — et c'est alors une décision notée dans l'issue.

**6. La résolution du paramètre d'URL (*route model binding*).** Pour `signalements/{signalement}`, Laravel voit que le composant attend un `Signalement`, va chercher en base la ligne dont l'`id` vaut le segment d'URL, et la passe déjà chargée. Si elle n'existe pas : **404 automatique**, notre code n'est même pas exécuté.

Attention à l'**ordre des routes** : `signalements/{signalement}` déclarée avant `signalements/create` capturerait « create » comme identifiant. L'ordre de `routes/features.php` (index, create, show, edit) est le bon ; le générateur le respecte.

**7 à 9.** Le composant démarre (`mount()`), demande à la Policy, lit les données avec Eloquent, et le HTML est rendu par Blade. C'est l'objet des chapitres 3, 5 et 6.

### Les codes de réponse, et ce qu'ils veulent dire ici

| Code | Signification chez nous | Où regarder |
|---|---|---|
| **302** vers `/login` | personne n'est connecté, le middleware `auth` a renvoyé | normal ; en test, c'est ce qu'attend `assertRedirect('/login')` |
| **403** | la **Policy** a répondu non | `app/Policies/…` et l'appel `$this->authorize(...)` |
| **404** | l'identifiant d'URL ne correspond à aucune ligne, ou l'ordre des routes est mauvais | `php artisan route:list --path=…` |
| **419** | jeton CSRF ou session expiré (page laissée ouverte trop longtemps) | recharger la page ; en test, vérifier qu'on est bien `actingAs` |
| **500** | vraie erreur PHP | `tail -n 60 storage/logs/laravel.log` |

Les erreurs de **validation** ne produisent pas de code d'erreur dans une page Livewire : elles reviennent sous forme de messages affichés dans le formulaire, sans rechargement (chapitre 3).

### Erreurs fréquentes

- « Route [signalements.index] not defined » → nom mal orthographié dans un `route(...)`. Vérifier avec `php artisan route:list --path=signalements`.
- Un changement de route ou de configuration qui « ne prend pas » → `php artisan optimize:clear`.
- Une page publique créée **dans** le groupe `auth` : elle renvoie 302 au jury non connecté.

---

## 3. Le protocole Livewire, et pourquoi il dicte nos règles de sécurité

### À quoi ça sert

C'est **le** chapitre à comprendre. Nos règles de sécurité (`$this->authorize()` dans chaque méthode, `#[Locked]`, `#[Fillable]` explicite) ne sont pas des précautions de principe : elles découlent mécaniquement de la façon dont Livewire fonctionne. Qui comprend ce chapitre n'oublie plus jamais un `authorize()`.

**Livewire** permet d'écrire des interfaces réactives (filtres, modales, suppression sans rechargement) **en PHP**, sans écrire de JavaScript et sans API REST séparée. En échange, le navigateur dialogue en permanence avec notre code PHP.

### Ce que fait notre code

**Premier temps : le rendu initial.** La requête du chapitre 2 produit une page HTML complète. Dans ce HTML, Livewire glisse un **snapshot** : l'état du composant (toutes ses propriétés publiques) sérialisé en JSON, accompagné d'une **signature cryptographique** (un *checksum*, calculé avec la clé `APP_KEY` de l'application).

**Second temps : chaque interaction.** Quand l'utilisateur tape dans le champ de recherche (`wire:model`) ou clique sur un bouton (`wire:click`), le navigateur envoie **une seule requête POST, toujours la même**, contenant trois choses :

```
snapshot : l'état du composant tel que le navigateur le détient
updates  : les propriétés que l'utilisateur a modifiées   (ex. search = "pont")
calls    : les méthodes à exécuter, avec leurs arguments  (ex. delete(42))
```

Cette route s'appelle `livewire.update` ; son URL porte un préfixe propre à chaque application (chez nous `livewire-d3b91a35/update`, visible avec `php artisan route:list --name=livewire`), ne la codez donc jamais en dur.

Le serveur **reconstruit** alors un composant neuf à partir du snapshot, **vérifie le checksum** (si l'état a été bricolé dans la console du navigateur, la requête est rejetée), applique les `updates`, exécute les `calls`, re-rend la vue, et renvoie le nouveau snapshot plus la liste des modifications à appliquer au DOM.

Le mécanisme est dans `vendor/livewire/livewire/src/Mechanisms/HandleComponents/` si vous voulez le lire : `Checksum.php` pour la signature, et `SecurityPolicy.php` qui tient une **liste noire de classes** interdites à la reconstruction (commandes console, `Symfony\...\Process`, `Mailable`, jobs de file d'attente…). Autrement dit : les auteurs de Livewire se protègent contre des chaînes d'exploitation réelles. Ce n'est pas un risque théorique.

### Les quatre conséquences, et la règle qui en découle

**1. Toute méthode publique d'un composant est une route POST déguisée.** N'importe qui peut ouvrir la console du navigateur et appeler `delete(42)` avec le `42` qu'il veut — sans jamais voir le bouton. Un bouton caché par `@can` n'est **pas** une protection : c'est du confort d'affichage.

D'où la règle : **chaque méthode publique appelle `$this->authorize()` avant de toucher aux données**. Notre `⚡index.blade.php` en est l'illustration exacte :

```php
public function delete(int $id): void
{
    $record = Signalement::findOrFail($id);   // 404 si l'id n'existe pas
    $this->authorize('delete', $record);      // 403 si ce n'est pas le propriétaire ni un admin
    // ... et seulement ensuite la suppression
}
```

L'identifiant vient du navigateur, donc il est **suspect par nature** : on le résout, puis on demande la permission. Jamais l'inverse, jamais l'un sans l'autre. Même raison dans `⚡form.blade.php`, où `authorize()` est appelé **deux fois** : dans `mount()` (à l'ouverture de la page) **et** dans `save()` (à l'enregistrement). Les deux sont des requêtes distinctes, séparées par le temps : entre les deux, les droits ont pu changer, et surtout rien ne garantit que `mount()` a été exécuté avant l'appel à `save()`.

**2. Toute propriété publique est modifiable par le client.** Elle fait partie du snapshot ; le navigateur peut tenter de la changer. Pour l'enregistrement d'un composant, ce serait catastrophique : changer `record` reviendrait à modifier le signalement de quelqu'un d'autre. D'où `#[Locked]` :

```php
#[Locked]
public ?Signalement $record = null;     // resources/views/pages/signalements/⚡form.blade.php
```

`#[Locked]` dit à Livewire : « cette propriété ne peut être changée que par le serveur ». Toute tentative venant du client lève une exception. **Tout modèle porté par un composant doit être `#[Locked]`.**

**3. L'état traverse le réseau.** Ne mettez jamais dans une propriété publique ce que l'utilisateur ne doit pas voir : clé d'API, mot de passe en clair, données d'un autre utilisateur. Pour une valeur de travail qui ne doit pas circuler, utilisez une variable locale ou une méthode `#[Computed]` (recalculée côté serveur à chaque fois, jamais envoyée).

**4. Le modèle ne doit pas accepter n'importe quel champ.** Si l'on écrit `Signalement::create($donnéesVenantDuFormulaire)`, un champ ajouté par un attaquant (`user_id`, `role`, `statut`) serait enregistré : c'est l'*assignation de masse*. Notre protection est explicite, sur le modèle lui-même :

```php
// app/Models/Signalement.php — user_id est volontairement ABSENT de la liste
#[Fillable(['titre', 'description', 'niveau', 'zone', 'photo', 'date_incident', 'latitude', 'longitude'])]
class Signalement extends Model
```

`user_id`, `role` et tout champ réservé sont **assignés dans le code** (`$x->user()->associate(auth()->user())`), jamais remplis depuis une requête.

### Le cycle de vie d'un composant, vu de notre code

`resources/views/pages/signalements/⚡index.blade.php` contient tout le vocabulaire utile :

| Élément | Rôle | Quand il s'exécute |
|---|---|---|
| `mount()` | initialisation, premier contrôle de droits | **une seule fois**, à l'ouverture de la page |
| `#[Url] public string $search` | la propriété est reflétée dans l'URL (page partageable, bouton Retour) | à chaque changement |
| `updatedSearch()` | réaction automatique au changement de `$search` : ici `resetPage()` pour revenir page 1 | à chaque changement de `$search` |
| `#[Computed] public function items()` | valeur calculée, mise en cache pendant la requête, accessible en Blade par `$this->items` | à chaque rendu, une seule fois par requête |
| méthode publique (`delete()`) | action déclenchée par l'utilisateur | sur `wire:click` |
| la vue, après `?>` | le HTML du composant | à chaque aller-retour |

Détail qui compte : `mount()` ne tourne **pas** aux requêtes suivantes. C'est précisément pourquoi un contrôle de droits placé uniquement dans `mount()` ne protège rien d'autre que le premier affichage.

### Erreurs fréquentes

- Un contrôle de droits dans `mount()` seulement → les actions sont ouvertes à tous. **La faute la plus grave et la plus facile à faire.**
- Oublier `#[Locked]` sur un modèle porté par le composant.
- Croire que `@can('update', $record)` autour du bouton protège l'action : il cache le bouton, rien de plus.
- Mettre une donnée volumineuse en propriété publique : elle fait l'aller-retour à chaque clic, la page devient lente.
- `Checksum has been tampered with` en développement : généralement un `APP_KEY` qui a changé ou un cache obsolète → `php artisan optimize:clear` et rechargement complet.

---

## 4. Où vit quoi, et pourquoi

### La carte du dépôt, par responsabilité

```
routes/features.php        « quelle URL mène où »           → chapitre 2
resources/views/pages/     « la page : son état + son HTML » → chapitre 3
app/Policies/              « qui a le droit de faire quoi »  → chapitre 7
app/Models/                « une classe = une table »        → chapitre 5
database/migrations/       « la structure des tables »       → chapitre 5
database/factories/        « de fausses données crédibles »  → chapitre 5
app/Services/              « parler au monde extérieur »     → chapitre 8
app/Concerns/              « comportements réutilisables »   → chapitre 8
app/Filament/              « l'espace admin /admin »         → chapitre 7
config/ + .env             « les réglages »                  → chapitre 9
tests/Feature/             « la preuve que ça marche »        → chapitre 11
```

### Une particularité à connaître : pas de contrôleur, pas d'API

Dans beaucoup de projets Laravel, une page = une route + un **contrôleur** + une **vue** (trois fichiers). Ici, **une page = un seul fichier** : le composant Livewire *single-file*, qui contient sa logique PHP en haut et son HTML en bas.

```php
// resources/views/pages/signalements/⚡index.blade.php
<?php
new #[Title('Signalements')] class extends Component {
    // toute la logique : propriétés, mount(), méthodes
}; ?>

<section>
    {{-- tout le HTML --}}
</section>
```

La route les relie par leur **chemin de vue**, pas par un nom de classe :

```php
Route::livewire('signalements', 'pages::signalements.index')->name('signalements.index');
```

`'pages::signalements.index'` se lit : le dossier `resources/views/pages/`, puis `signalements/`, puis le fichier `⚡index.blade.php`. Le préfixe `⚡` dans le nom de fichier est la marque qui dit à Livewire « ceci est un composant, pas une vue ordinaire » (voir `vendor/livewire/livewire/src/Finder/Finder.php`). C'est inhabituel mais pratique : tout ce qui concerne une page est à un seul endroit. Conséquence shell : ces noms de fichiers contiennent un caractère spécial, **mettez-les entre guillemets** dans vos commandes.

Il n'y a donc **ni API REST ni front séparé** : pas de JSON à produire, pas de CORS, pas de jetons à gérer. En contrepartie, tout le dialogue passe par l'unique route `livewire.update` — relire le chapitre 3.

Le dossier `app/Http/Controllers/` existe mais ne contient que la classe de base, vide : **on ne crée pas de contrôleur** dans ce projet.

---

## 5. Les données

### À quoi ça sert

Trois notions suffisent : le **modèle** (manipuler les données en PHP), la **migration** (définir la structure des tables), la **factory** (fabriquer de fausses données pour les tests et les démos).

### Ce que fait notre code

**Le modèle** est une classe qui représente une table. Il n'y a pas de « repository » séparé : c'est le modèle lui-même qui interroge la base.

```php
Signalement::find(3);                          // SELECT * FROM signalements WHERE id = 3
Signalement::where('niveau', 'critique')->get();
$signalement->user->name;                      // suit la relation vers la table users
$signalement->save();
```

Notre `app/Models/Signalement.php` tient en 45 lignes et contient quatre choses à repérer :

```php
#[Fillable([...])]                                        // champs remplissables (chapitre 3)
public const NIVEAU_OPTIONS = ['faible', 'moyen', 'critique'];   // la liste des valeurs permises
public function user(): BelongsTo                         // la relation : un signalement appartient à un utilisateur
protected function casts(): array                         // conversions automatiques
```

Les **casts** convertissent les données dans les deux sens : `'date_incident' => 'date'` fait qu'on récupère un objet date (`$s->date_incident->format('d/m/Y')`) et non une chaîne. `'decimal:7'` sur les coordonnées garde 7 décimales, soit une précision d'environ un centimètre.

La constante `NIVEAU_OPTIONS` existe pour être utilisée **partout** : dans la validation (`Rule::in(Signalement::NIVEAU_OPTIONS)`), dans le `<select>`, dans l'admin. Une seule liste, donc aucune divergence possible entre le formulaire et ce que le serveur accepte.

**La migration** est un fichier horodaté qui décrit une modification de structure (`database/migrations/2026_09_29_192329_create_signalements_table.php`). Les migrations forment un historique versionné, rejouable à l'identique sur la machine de chacun et sur le serveur.

```bash
php artisan migrate               # applique les migrations pas encore appliquées
php artisan migrate:status        # où en est la base ?
php artisan migrate:fresh --seed  # VIDE tout et recrée + données de démo — LOCAL UNIQUEMENT
```

`migrate:fresh` supprime toutes les tables. En production, ce serait la perte des données du jury ; c'est pour cela que `AppServiceProvider` appelle `DB::prohibitDestructiveCommands()` (chapitre 2).

**Factory et seeder** fabriquent les données de démo. La factory décrit à quoi ressemble un enregistrement crédible ; le seeder en crée en nombre. Notre `DatabaseSeeder` mérite un coup d'œil, pour son garde-fou :

```php
if (! app()->isProduction()) {       // les comptes à mot de passe connu n'existent QUE en local
    User::factory()->admin()->create(['email' => 'admin@example.com']);
    User::factory()->create(['email' => 'user@example.com']);
}
User::factory(8)->create(['password' => Str::password(32)]);   // les autres : mot de passe aléatoire
Signalement::factory(20)->recycle($users)->create();
```

### Le piège N+1

Afficher 10 signalements avec le nom de leur auteur :

```php
$signalements = Signalement::latest()->paginate(10);   // 1 requête
foreach ($signalements as $s) { echo $s->user->name; } //  + 10 requêtes !
```

Chaque `$s->user` déclenche sa propre requête : 11 requêtes au lieu de 2. Avec 200 lignes, la page s'écroule. La solution est `with()`, et c'est ce que fait notre `⚡index.blade.php` :

```php
return $this->filteredQuery()->with('user')->latest()->paginate(10);
```

**Règle** : dès qu'une boucle Blade lit une relation, la requête doit avoir le `with()` correspondant. Et jamais de requête à l'intérieur d'une boucle.

### Deux bases, une application

En local : **SQLite**, un simple fichier (`database/database.sqlite`), zéro installation. En production : **MariaDB 10.11** sur l'hébergement Hodi. Les deux ne sont pas rigoureusement identiques (types, tri, contraintes), et une requête qui passe sur l'une peut échouer sur l'autre. C'est pourquoi `.github/workflows/tests.yml` contient **deux jobs** : toute la suite est rejouée sur MariaDB 10.11 en plus de SQLite. Un test rouge seulement sur le job `mariadb` signale exactement ce genre de différence — ne le négligez pas, c'est la base du serveur qui parle.

### Erreurs fréquentes

- `no such table` / `Column not found` → migration non appliquée : `php artisan migrate`.
- Un champ ajouté à la migration mais pas à `#[Fillable]` → il reste vide sans message d'erreur.
- Modifier une migration **déjà appliquée** chez les autres : créer une nouvelle migration à la place.

---

## 6. Du PHP au pixel

### À quoi ça sert

Quatre couches produisent l'écran : **Blade** (le HTML), **Flux** (les composants prêts à l'emploi), **Tailwind** (la mise en forme), **Vite** (la compilation).

### Blade

Blade est du HTML augmenté de quelques directives, compilé en PHP et mis en cache dans `storage/framework/views/` (d'où ces fichiers aux noms illisibles : c'est normal, c'est généré).

```blade
{{ $signalement->titre }}        {{-- échappe le HTML : SÛR --}}
{!! $signalement->titre !!}      {{-- n'échappe PAS : INTERDIT sur une donnée saisie --}}
@if / @foreach / @can('update', $record)
```

`{{ }}` transforme `<script>` en texte inoffensif. `{!! !!}` l'exécute : c'est une faille XSS ouverte, et le jury la cherchera. **Dans ce projet, on écrit toujours `{{ }}`.** Pour du texte injecté en JavaScript : `textContent`, jamais `innerHTML`.

Le layout `resources/views/layouts/app.blade.php` fournit le cadre (menu de gauche, zone principale) ; la sidebar est `resources/views/layouts/app/sidebar.blade.php`.

### Flux et Tailwind

**Flux** est la bibliothèque de composants officielle de Livewire : `<flux:button>`, `<flux:input>`, `<flux:table>`, `<flux:modal>`, `<flux:badge>`… Nous avons la **version gratuite** : on se limite à ces composants-là, et on ne réinvente pas un bouton à la main.

**Tailwind 4** habille par petites classes utilitaires directement dans le HTML (`class="flex items-center gap-4"`) : pas de fichier CSS à maintenir en parallèle.

La **couleur d'accent** se change à **un seul endroit**, le bloc `@theme` de `resources/css/app.css` (actuellement `amber`, à adapter au sujet le jour J), plus `Color::Amber` dans `app/Providers/Filament/AdminPanelProvider.php` pour l'admin. Ne codez jamais une couleur en dur dans une page : le jour où le thème change, il faudrait repasser partout.

### Vite

`vite.config.js` déclare ce qui est compilé : `resources/css/app.css`, `resources/js/app.js`, `resources/js/carte.js`, plus la police Instrument Sans. En développement, `composer run dev` lance trois processus en parallèle (serveur PHP, file d'attente, Vite) et recharge le navigateur à chaque sauvegarde. En production, `npm run build` écrit des fichiers figés dans `public/build/`.

Attention : notre `package.json` utilise **`vite-plus`** (`vp build`, `vp dev`), pas `vite` directement. Passez par `npm run build` / `composer run dev`, jamais par `npx vite`.

### Erreurs fréquentes

- **Page sans style, affichage cassé** : `composer run dev` n'est pas lancé (ou `npm run build` pas fait), puis **Ctrl+F5**. C'est le problème le plus fréquent de la semaine.
- Une image envoyée mais invisible : `php artisan storage:link` (crée le lien entre `storage/app/public` et `public/storage`).
- Un `{!! !!}` oublié : à chercher avant chaque livraison.

---

## 7. Authentification, rôles et espace admin

### Qui est connecté

**Fortify** fournit toute l'authentification sans écrire une ligne : connexion, inscription, mot de passe oublié, confirmation de mot de passe, et **2FA** (double facteur). Les options actives sont dans `config/fortify.php` (`Features::registration()`, `Features::resetPasswords()`, `Features::twoFactorAuthentication(...)`). Les pages de réglages correspondantes sont nos composants de `resources/views/pages/settings/`, montés par `routes/settings.php` — noter que la page sécurité exige une re-saisie du mot de passe via le middleware `password.confirm`.

En PHP : `auth()->user()` donne l'utilisateur connecté, `auth()->id()` son identifiant, `null` si personne.

### Les rôles

Il n'y a pas de système de rôles complexe : une simple colonne `role` sur la table `users`, et deux méthodes dans `app/Models/User.php` :

```php
public function isAdmin(): bool { return $this->role === 'admin'; }
public function canAccessPanel(Panel $panel): bool { return $this->isAdmin(); }
```

`role` est **absent** de `#[Fillable]` : personne ne peut se promouvoir administrateur en ajoutant un champ à un formulaire. Un admin se crée à la main (`tinker` ou seeder).

### Les Policies : le cœur du contrôle d'accès

Une **Policy** est une classe qui répond oui ou non à « cet utilisateur peut-il faire cette action sur cet objet ? ». Une méthode par action. `app/Policies/SignalementPolicy.php` au complet :

```php
public function viewAny(User $user): bool { return true; }   // tout connecté voit la liste
public function view(User $user, Signalement $s): bool { return true; }
public function create(User $user): bool { return true; }

public function update(User $user, Signalement $s): bool
{
    return $user->isAdmin() || $s->user_id === $user->id;     // propriétaire OU admin
}

public function delete(User $user, Signalement $s): bool
{
    return $user->isAdmin() || $s->user_id === $user->id;
}
```

Trois choses à comprendre :

1. Laravel relie automatiquement `Signalement` à `SignalementPolicy` (convention de nommage) : rien à déclarer.
2. `$this->authorize('update', $record)` dans un composant appelle `update()` de cette Policy. Faux → exception **403**.
3. `@can('update', $record)` en Blade appelle la **même** méthode, mais pour décider d'afficher ou non un bouton. Les deux vont ensemble : `@can` pour l'apparence, `authorize` pour la sécurité. Une seule des deux sources de vérité, donc aucun risque d'incohérence entre ce qui est affiché et ce qui est permis.

Le `viewAny`/`view` à `true` de notre exemple est le réglage par défaut du générateur, **à adapter au sujet** : si les données sont personnelles, il faut les restreindre. Les données personnelles d'autrui (e-mail, téléphone) ne s'affichent jamais sans une règle de Policy dédiée.

### Filament : l'espace admin

**Filament 5** est un back-office prêt à l'emploi sur `/admin`, réservé aux admins par `canAccessPanel()`. On le génère (`php artisan make:filament-resource Nom --generate`) plutôt que de l'écrire. Un *resource* = un dossier dans `app/Filament/Resources/<Pluriel>/` avec trois sous-dossiers : `Pages/` (liste, création, édition), `Schemas/` (le formulaire), `Tables/` (les colonnes). Nous avons déjà `UserResource`, `SignalementResource`, `ActionLogResource` et un widget de statistiques.

Attention : sans `canAccessPanel()`, Filament ouvre `/admin` à **tout** utilisateur connecté. C'est le premier endroit que le jury testera. Une fois une ressource admin créée, vérifiez-le avec un compte non-admin (le test attendu : 403).

---

## 8. La boîte à outils du projet

### À quoi ça sert

Six briques sont déjà écrites, testées et dans `main`. Les réutiliser fait gagner des heures le jour J — et évite de réintroduire une faille dans du code écrit à 4 h du matin. **Avant d'écrire quoi que ce soit de transverse, regardez ici.**

| Brique | Où | Appel |
|---|---|---|
| Export CSV | `app/Concerns/ExportsCsv.php` | `$this->streamCsv('viewAny', Signalement::class, $query, $colonnes, 'signalements')` |
| Limitation d'appels | `app/Concerns/ThrottlesPerUser.php` | `$this->throttlePerUser('ia', maxAttempts: 5, decaySeconds: 60)` |
| IA (OpenRouter) | `app/Services/Ai.php` | `app(Ai::class)->ask($systeme, $question)` → `?string` |
| API de l'orga | `app/Services/OrgaApi.php` | `app(OrgaApi::class)->get(...)` → tableau |
| Journal d'actions | `app/Models/ActionLog.php` | `ActionLog::record('deleted', $signalement)` |
| Carte Leaflet | `app/Models/Concerns/HasCoordinates.php` + `<x-carte>` | `scopeGeolocalises()`, `scopeProches($lat, $lng)`, `pointCarte()` |

### Le point à ne pas rater : la dégradation silencieuse

`Ai` et `OrgaApi` ne lèvent **jamais** d'exception. Sans clé configurée, en cas d'erreur réseau ou de délai dépassé, ils renvoient `null` (pour `Ai`) ou `[]` (pour `OrgaApi`). C'est un choix délibéré : **une fonctionnalité secondaire indisponible ne doit jamais casser une page devant le jury**.

La contrepartie est à votre charge : la page doit prévoir le cas vide, avec un message honnête (« Suggestion indisponible pour le moment », « Données momentanément indisponibles »). Si vous oubliez, le jury voit une zone blanche inexpliquée — pire qu'un message.

Deux autres détails utiles : `Ai` met les réponses en cache 1 h (même question = un seul appel facturé), et `ActionLog::record()` encapsule son écriture dans un `try/catch` — journaliser ne doit jamais faire échouer l'action journalisée.

Toute action sensible (appel d'IA, envoi d'e-mail, export, signalement) doit être limitée par `throttlePerUser()` : le jury teste explicitement l'absence de limitation (force brute, abus d'API).

---

## 9. Configuration et environnements

### La chaîne `.env` → `config/` → `config()`

```
.env                    valeurs de CETTE machine, jamais committé (secrets, mots de passe, clés)
   ↓ lu une seule fois
config/*.php            fichiers de configuration, committés : ils lisent .env avec env()
   ↓
config('services.orga.url')     ce qu'on écrit dans le code
```

**Règle** : `env()` ne s'utilise **que** dans `config/`. Dans le reste du code, toujours `config(...)`. Raison concrète : en production, la configuration est mise en cache (`config:cache`), et un `env()` appelé ailleurs renvoie alors `null` — bug silencieux, très difficile à diagnostiquer à 3 h du matin.

Nos clés externes sont dans `config/services.php` :

```php
'openrouter' => ['key' => env('OPENROUTER_API_KEY'), 'model' => env('OPENROUTER_MODEL')],
'orga' => ['url' => env('ORGA_API_URL'), 'token' => env('ORGA_API_TOKEN')],
```

### Local contre production

| Réglage | Local | Production (Hodi, mutualisé, 2 Go) |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | `mariadb` |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` | `database` (pas de Redis) |
| `QUEUE_CONNECTION` | `database` | **`sync`** : aucun *worker*, les tâches s'exécutent immédiatement |
| `APP_DEBUG` | `true` | **`false`** (sinon la trace d'erreur s'affiche au jury) |
| E-mails | selon `.env` | `sendmail` |

Conséquence directe du `sync` : pas de tâche de fond, donc **pas de temps réel par websocket**. Pour rafraîchir une page, on utilise `wire:poll` (le navigateur redemande l'état toutes les N secondes).

Les variables de production se changent **dans Hodifly** (Modifier → Variables → Déployer), jamais dans un `.env` sur le serveur : il est réécrit à chaque déploiement.

---

## 10. Le générateur `make:feature`

### À quoi ça sert

`app/Console/Commands/MakeFeature.php` (~1150 lignes, écrit pour ce projet) produit une fonctionnalité CRUD complète en une commande. Ce n'est pas un gadget : c'est notre principal avantage de vitesse le jour J, et il écrit du code **déjà sécurisé**, ce qu'on ne garantit pas à la main sous pression.

```bash
php artisan make:feature Signalement \
  --fields="titre:string,description:text?,niveau:enum(faible/moyen/critique),photo:image?,latitude:decimal?,longitude:decimal?" \
  --icon=exclamation-triangle
php artisan migrate
```

Types disponibles : `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `enum(a/b/c)`, `image`. Suffixe `?` = facultatif. Sous PowerShell, séparez les valeurs d'enum par `/` (le `|` est intercepté par le shell).

### Ce qu'il écrit

Migration, modèle (avec `#[Fillable]`, casts, relation `user`), factory, ligne de seeder, **Policy**, les trois pages `⚡index` / `⚡form` / `⚡show`, et **6 tests Pest** — dont « un autre utilisateur ne peut pas modifier » et « un autre utilisateur ne peut pas supprimer ».

### Les trois marqueurs, et la règle qui va avec

Le générateur doit aussi **brancher** la fonctionnalité sur l'existant. Il le fait en insérant son bloc juste avant un commentaire-repère :

```php
// make:feature:routes          dans routes/features.php
{{-- make:feature:nav --}}      dans resources/views/layouts/app/sidebar.blade.php
// make:feature:seeders         dans database/seeders/DatabaseSeeder.php
```

Deux règles en découlent :

1. **Ne supprimez jamais ces trois commentaires.** Sans eux, le générateur avertit et il faut tout brancher à la main.
2. **N'éditez pas ces trois endroits à la main** : utilisez le générateur. Plusieurs personnes (et plusieurs sessions Claude) travaillent en parallèle sur ces mêmes lignes ; c'est le point de conflit Git numéro un du week-end. Si un conflit survient malgré tout : **gardez les deux blocs**, jamais un seul.

Le générateur refuse certains noms de champs réservés (`id`, `user_id`, `created_at`, `record`, `search`, `page`…) car ils entreraient en collision avec le code généré ou avec les propriétés du composant.

Après génération : **relire** la migration (index, `nullable`), la factory (données crédibles) et surtout la **Policy** (les droits par défaut sont permissifs, à resserrer selon le sujet). Options détaillées au tome 1, chapitre 25.

---

## 11. Tests, qualité et intégration continue

### Ce qu'un test fait vraiment

Un test Pest est une petite fonction qui simule un vrai visiteur et vérifie le résultat. `tests/Pest.php` applique automatiquement `RefreshDatabase` à tous les tests de `tests/Feature/` : **la base est recréée vide avant chaque test**, les tests sont donc indépendants et ne polluent pas votre base de développement. Vous n'avez rien à importer.

```php
test('un autre utilisateur ne peut pas modifier', function () {
    $proprietaire = User::factory()->create();
    $intrus = User::factory()->create();
    $signalement = Signalement::factory()->for($proprietaire)->create();

    $this->actingAs($intrus)
        ->get(route('signalements.edit', $signalement))
        ->assertForbidden();                              // on attend un 403
});
```

Les noms de tests sont **en français** et décrivent un comportement, pas une méthode : voyez `tests/Feature/SignalementTest.php` (« le propriétaire peut ouvrir la modification », « un admin peut supprimer »).

**Minimum par fonctionnalité** : un test « le propriétaire peut » **et** un test « un autre utilisateur → 403 ». Ces deux tests sont exactement ce que le jury va tenter à la main.

### Les commandes, et un piège

```bash
php artisan test --compact                       # les tests seuls, affichage court
vendor/bin/pest --filter="un admin peut supprimer"   # un seul test
vendor/bin/pest tests/Feature/SignalementTest.php    # un seul fichier
vendor/bin/pint                                  # formatage automatique du code
composer types:check                             # analyse statique PHPStan
composer test                                    # TOUT ce que lance la CI
```

**Le piège** : `composer test` enchaîne `config:clear` + `pint --test` + `phpstan` + `artisan test`. Donc **`php artisan test` seul ne reproduit pas la CI** : elle peut échouer sur un simple problème de formatage ou de typage alors que tous vos tests passent. Avant un push, lancez `vendor/bin/pint` puis `composer test`.

### La CI et le déploiement

`.github/workflows/tests.yml` tourne à chaque *pull request* et à chaque push sur `main`, en deux jobs : le premier sur SQLite (`composer setup` puis `composer ci:check`), le second sur **MariaDB 10.11**, la base du serveur.

Pourquoi c'est vital : **chaque merge sur `main` est déployé en ligne automatiquement** par Hodifly en 1 à 2 minutes. Donc **jamais de merge sans CI verte** — et, de là, la règle du projet : vous ne committez pas, ne poussez pas et ne mergez pas vous-même ; vous proposez, l'équipe exécute (Randy, ou Judicaël quand Randy dort).

---

## 12. Déboguer : le réflexe par symptôme

### Les quatre commandes qui règlent la majorité des cas

```bash
php artisan route:list --path=signalements    # quelles routes existent vraiment
tail -n 60 storage/logs/laravel.log           # la vraie erreur, avec sa trace
php artisan optimize:clear                    # vider tous les caches (config, routes, vues)
php artisan tinker                            # console interactive dans le contexte de l'app
```

`php artisan pail` affiche le journal en direct, pratique pendant qu'on clique dans l'interface.

### Le tableau

| Symptôme | Cause la plus probable | Action |
|---|---|---|
| Redirection vers `/login` alors qu'on est connecté | session perdue, ou route dans le groupe `auth` alors qu'elle doit être publique | vérifier `routes/features.php` |
| **403** | la Policy dit non | lire `app/Policies/…`, vérifier le `user_id` de l'enregistrement |
| **404** sur `/signalements/create` | route `{signalement}` déclarée avant `create` | remettre l'ordre index → create → show → edit |
| **419** | jeton CSRF / session expirée | recharger la page complètement |
| **500** | erreur PHP | `tail -n 60 storage/logs/laravel.log` — **les 20 dernières lignes suffisent** pour demander de l'aide |
| `no such table` / `Column not found` | migration non appliquée | `php artisan migrate` |
| Page sans style, mise en page cassée | Vite n'est pas lancé | `composer run dev`, puis **Ctrl+F5** |
| Photo envoyée mais invisible | lien de stockage absent | `php artisan storage:link` |
| « Route [x] not defined » | faute de frappe dans un `route(...)` | `php artisan route:list` |
| Modification de code sans effet | caches | `php artisan optimize:clear` |
| `Checksum has been tampered with` | `APP_KEY` changée ou cache obsolète | `optimize:clear` + rechargement complet |
| Site `.test` inaccessible | Firefox, VPN, ou Herd arrêté | Chrome, Herd → *Start all* |
| `could not find driver (sqlite)` | extension PHP désactivée | Herd → PHP 8.4 → activer `pdo_sqlite` |

Sur le **serveur** (Terminal cPanel) : toujours `cd ~/app` d'abord, et `php84 artisan …` (le `php` par défaut y est en 8.1). Ne modifiez jamais un fichier directement sur le serveur : il est remplacé au déploiement suivant.

Pour demander de l'aide — à l'équipe ou à Claude — collez **les 20 dernières lignes du journal**, pas la trace entière, et dites ce que vous avez fait juste avant.

---

## 13. Les dix phrases à retenir

1. **PHP oublie tout à chaque requête** : ce qui doit survivre est en base ou en session.
2. **Tout passe par `public/index.php`** : le reste du dépôt n'est pas joignable par URL.
3. **Toute route métier est dans le groupe `auth` de `routes/features.php`** ; une page publique est une décision écrite dans l'issue.
4. **Chaque méthode publique d'un composant Livewire est une route POST ouverte au monde** : `$this->authorize()` dedans, à chaque fois, pas seulement dans `mount()`.
5. **Un identifiant qui vient du navigateur est suspect** : `findOrFail()` puis `authorize()`, ou filtrage par propriétaire.
6. **`#[Locked]` sur tout modèle porté par un composant** ; `user_id`, `role`, `statut` jamais dans `#[Fillable]`.
7. **Toujours `{{ }}` en Blade**, jamais `{!! !!}` sur une donnée saisie.
8. **Une boucle qui lit une relation exige un `with()`** dans la requête.
9. **`php artisan test` seul ne suffit pas** : la CI lance aussi Pint et PHPStan (`composer test`), et rejoue tout sur MariaDB.
10. **Un merge sur `main` part en ligne tout seul** : jamais de merge sans CI verte, et une fonctionnalité sûre et finie vaut mieux que deux à moitié.

---

**Pour continuer** : tome 1 (`10-laravel-complet.md`) pour les recettes chapitre par chapitre, tome 2 (`20-playbook-competition.md`) pour l'organisation du week-end, `CONVENTION.md` pour les règles courtes à relire avant chaque PR, et votre guide de rôle (`01-` à `04-`).
