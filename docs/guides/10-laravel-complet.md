# Tome 1 — Laravel de A à Z, avec notre projet

_Pour les 4 membres. Ce cours part de zéro et s'appuie uniquement sur **notre** repo : chaque exemple existe (ou a existé) dans `webcup-2026`. Chaque chapitre suit le même plan : **à quoi ça sert**, **comment on l'écrit chez nous**, **les erreurs fréquentes**. Les exercices sont au chapitre 26 : faites-les avant samedi, c'est là que se gagne la vitesse._

**Ordre de lecture conseillé** : tout le monde lit les chapitres 1 à 12 et 25. Ensuite, selon le rôle : Judicaël 13, 14, 18, 22 ; Tsoa 11, 15, 16, 21 ; Njaraniaina 13 et 22 ; Randy 23 et 24.

| # | Chapitre | # | Chapitre |
|---|---|---|---|
| 1 | Laravel pour un développeur Spring / Angular | 14 | Filament : l'espace admin |
| 2 | Le trajet d'une requête | 15 | Fichiers et images |
| 3 | Les dossiers à connaître | 16 | Carte et géolocalisation |
| 4 | Routes | 17 | Notifications et e-mails |
| 5 | Eloquent : modèles et requêtes | 18 | Services externes : API de l'orga, IA |
| 6 | Relations | 19 | Cache, tâches planifiées, file d'attente |
| 7 | Migrations | 20 | Statistiques et export CSV |
| 8 | Validation | 21 | Données de démo : factories et seeders |
| 9 | Blade | 22 | Tests avec Pest |
| 10 | Livewire 4 | 23 | Déboguer |
| 11 | Flux, Tailwind et identité visuelle | 24 | Déployer |
| 12 | Authentification, rôles et Policies | 25 | Le générateur `make:feature` |
| 13 | Sécurité : les règles et les attaques | 26 | Exercices |

## 1. Laravel pour un développeur Spring / Angular

Vous connaissez déjà les concepts ; seuls les noms changent.

| Spring Boot / Angular | Laravel (chez nous) | Où |
|---|---|---|
| `@Entity` + JPA | **Modèle Eloquent** | `app/Models/` |
| `JpaRepository` | Le modèle lui-même : `Signalement::where(...)->get()` | — |
| Flyway / Liquibase | **Migrations** | `database/migrations/` |
| `@Controller` + Thymeleaf | **Composant Livewire** (PHP + vue dans un fichier) | `resources/views/pages/` |
| Composant Angular | Composant Livewire (état + template + événements) | idem |
| `@PreAuthorize` | **Policy** + `$this->authorize()` | `app/Policies/` |
| Bean Validation | `rules()` | dans chaque composant |
| `application.properties` | **`.env`** + `config/` | racine |
| `@Service` | Classe dans `app/Services/`, injectée ou via `app(...)` | `app/Services/` |
| Données de test | **Factory** + **Seeder** | `database/factories/`, `database/seeders/` |
| JUnit | **Pest** | `tests/Feature/` |
| `@Scheduled` | **Scheduler** | `routes/console.php` |
| Back-office maison | **Filament** (généré) | `app/Filament/` → `/admin` |
| `mvn` / `ng` CLI | **`php artisan`** | — |

**Différence majeure** : pas d'API REST + front séparé. Livewire rend le HTML côté serveur et envoie de petites requêtes AJAX à chaque clic. **Conséquence de sécurité** : chaque méthode publique d'un composant est appelable depuis le navigateur, avec n'importe quel argument.

## 2. Le trajet d'une requête

Quand un utilisateur ouvre `/signalements` :

1. **Route** (`routes/features.php`) : l'URL pointe vers le composant `pages::signalements.index`. Le middleware `auth` exige une connexion, sinon redirection vers `/login`.
2. **Composant Livewire** (`resources/views/pages/signalements/⚡index.blade.php`) : `mount()` s'exécute et vérifie les droits avec `$this->authorize('viewAny', Signalement::class)`.
3. **Policy** (`app/Policies/SignalementPolicy.php`) : répond oui ou non. Non → **403**.
4. **Modèle** : la propriété calculée `items()` lit les données (`Signalement::query()->with('user')->latest()->paginate(10)`).
5. **Vue** (bas du même fichier) : HTML rendu avec les composants **Flux** (`<flux:table>`, `<flux:button>`…) dans le layout `layouts/app` (menu de gauche).
6. **Ensuite**, chaque saisie (`wire:model`) ou clic (`wire:click`) renvoie une petite requête AJAX : Livewire exécute la méthode PHP et met à jour seulement ce qui a changé.

## 3. Les dossiers à connaître

```
app/Models/                    ← entités (une classe = une table)
app/Policies/                  ← qui a le droit de faire quoi
app/Filament/                  ← espace admin (/admin)
app/Services/                  ← appels externes (IA, API de l'orga)
app/Console/Commands/          ← nos commandes (make:feature)
app/Providers/                 ← configuration au démarrage (sécurité, Fortify)
config/                        ← configuration (lit le .env)
database/migrations/           ← structure des tables
database/factories/            ← fausses données réalistes
database/seeders/              ← remplissage de la base de démo
lang/fr.json, lang/fr/         ← traductions françaises
resources/css/app.css          ← thème (couleur d'accent)
resources/views/pages/         ← toutes nos pages (composants Livewire)
resources/views/layouts/app/sidebar.blade.php   ← menu de gauche
resources/views/welcome.blade.php               ← page d'accueil
routes/web.php, routes/features.php             ← routes
routes/console.php             ← tâches planifiées
tests/Feature/                 ← tests Pest
.env                           ← secrets et réglages locaux (jamais commité)
```

On ne touche **pas** à `vendor/`, `node_modules/`, `public/build/`, `storage/` (générés).

## 4. Routes

Nos fonctionnalités sont dans `routes/features.php`, **dans le groupe `auth`** :

```php
Route::middleware(['auth'])->group(function () {
    Route::livewire('signalements', 'pages::signalements.index')->name('signalements.index');
    Route::livewire('signalements/create', 'pages::signalements.form')->name('signalements.create');
    Route::livewire('signalements/{signalement}', 'pages::signalements.show')->name('signalements.show');
    Route::livewire('signalements/{signalement}/edit', 'pages::signalements.form')->name('signalements.edit');

    // make:feature:routes
});
```

- `'pages::signalements.index'` = le fichier `resources/views/pages/signalements/⚡index.blade.php`.
- `{signalement}` : Laravel charge automatiquement le `Signalement` correspondant (*route model binding*) et renvoie **404** s'il n'existe pas.
- `->name(...)` : on génère toujours les URL par leur nom : `route('signalements.show', $signalement)`.
- **Page publique** (décision explicite) : la déclarer **en dehors** du groupe `auth`, en haut du fichier.
- Le commentaire `// make:feature:routes` est un **marqueur** du générateur : ne pas le supprimer.

**Erreurs fréquentes** : « Route [x] not defined » → nom mal écrit, vérifier avec `php artisan route:list --path=signalements`. Une route `{signalement}` déclarée **avant** `create` capture « create » comme un ID : garder l'ordre ci-dessus.

## 5. Eloquent : modèles et requêtes

```php
#[Fillable(['titre', 'description', 'niveau', 'zone', 'photo', 'date_incident', 'latitude', 'longitude'])]
class Signalement extends Model
{
    use HasFactory;

    public const NIVEAU_OPTIONS = ['faible', 'moyen', 'critique'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'date_incident' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
```

- **`#[Fillable]`** : les seuls champs remplissables en masse (`new Signalement($data)`, `->update($data)`). `user_id`, `role`, `statut` réservés n'y sont **jamais** : c'est une protection, pas un oubli (chapitre 13).
- **`casts()`** : conversions automatiques (`date` → objet Carbon, `boolean`, `decimal:7`, `array` pour du JSON).
- **Constantes d'options** : une liste par enum, réutilisée dans la validation, les filtres, l'admin.
- Table déduite du nom : `Signalement` → `signalements`.

**Requêtes courantes**

```php
Signalement::all();                                    // tout (à éviter sur de grosses tables)
Signalement::find(5);                                  // par id, ou null
Signalement::findOrFail(5);                            // par id, ou 404
Signalement::where('niveau', 'critique')->latest()->get();
Signalement::whereBelongsTo(auth()->user())->count();  // ceux de l'utilisateur connecté
Signalement::query()
    ->when($niveau, fn ($q) => $q->where('niveau', $niveau))           // filtre optionnel
    ->when($search, fn ($q) => $q->where('titre', 'like', "%{$search}%"))
    ->latest()
    ->paginate(10);
$s->update(['titre' => 'Nouveau']);
$s->delete();
```

**Scope** (filtre réutilisable) :

```php
// dans le modèle
public function scopeCritiques(Builder $query): void
{
    $query->where('niveau', 'critique');
}
// utilisation
Signalement::critiques()->latest()->get();
```

**Erreurs fréquentes** : « Add [titre] to fillable property » → le champ manque dans `#[Fillable]` (s'il est légitime). Un champ qui reste vide après `update()` → même cause. `Attempt to read property on null` → `find()` a renvoyé `null` : utiliser `findOrFail()`.

## 6. Relations

```php
// Un utilisateur a plusieurs signalements — app/Models/User.php
public function signalements(): HasMany
{
    return $this->hasMany(Signalement::class);
}

// Un signalement concerne une zone — app/Models/Signalement.php
public function zone(): BelongsTo
{
    return $this->belongsTo(Zone::class);
}

// Plusieurs-à-plusieurs : un événement a des participants
public function participants(): BelongsToMany
{
    return $this->belongsToMany(User::class)->withTimestamps();
}
```

Migrations correspondantes :

```php
$table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();   // BelongsTo facultatif
$table->foreignId('user_id')->constrained()->cascadeOnDelete();            // BelongsTo obligatoire

// table pivot pour BelongsToMany (nom au singulier, ordre alphabétique : evenement_user)
Schema::create('evenement_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('evenement_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['evenement_id', 'user_id']);
});
```

Utilisation :

```php
$signalement->zone->nom;                       // relation chargée à la demande
$zone->signalements()->create($data);          // crée en remplissant zone_id
$evenement->participants()->attach($user->id); // inscrire
$evenement->participants()->detach($user->id); // désinscrire
$evenement->participants()->toggle($user->id); // basculer
```

**Le problème N+1** : afficher 10 signalements avec `$s->user->name` fait 11 requêtes. Toujours charger à l'avance :

```php
Signalement::with(['user', 'zone'])->latest()->paginate(10);
Zone::withCount('signalements')->get();        // $zone->signalements_count
```

**Relation polymorphe** (commentaires sur plusieurs types d'entités) : `morphMany` / `morphTo`, colonnes `$table->morphs('commentable')`. À n'utiliser que si le sujet l'exige.

## 7. Migrations

```php
Schema::create('signalements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('titre');
    $table->text('description')->nullable();
    $table->string('niveau', 50)->index();
    $table->string('statut', 30)->default('en_attente')->index();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->boolean('ouvert')->default(true);
    $table->date('date_incident');
    $table->timestamps();
});
```

| Commande | Effet |
|---|---|
| `php artisan make:migration add_statut_to_signalements_table` | Nouvelle migration |
| `php artisan migrate` | Applique les nouvelles |
| `php artisan migrate:status` | Liste ce qui est passé |
| `php artisan migrate:fresh --seed` | **LOCAL uniquement** : tout effacer et recréer |

**Ajouter une colonne à une table existante** :

```php
public function up(): void
{
    Schema::table('signalements', function (Blueprint $table) {
        $table->string('statut', 30)->default('en_attente')->index();
    });
}

public function down(): void
{
    Schema::table('signalements', function (Blueprint $table) {
        $table->dropColumn('statut');
    });
}
```

**Règles d'or**

1. **Ne jamais modifier une migration déjà déployée** : en créer une nouvelle.
2. Une colonne ajoutée à une table **qui a déjà des lignes** doit être `nullable()` ou avoir un `default(...)`.
3. Tester sur MariaDB (en ligne) : SQLite est plus tolérant (types, `ALTER`, fonctions de date).

## 8. Validation

Dans un composant Livewire :

```php
protected function rules(): array
{
    return [
        'titre' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:5000'],
        'niveau' => ['required', Rule::in(Signalement::NIVEAU_OPTIONS)],
        'capacite' => ['required', 'integer', 'min:0', 'max:100000'],
        'email_contact' => ['nullable', 'email', 'max:255'],
        'date_incident' => ['required', 'date', 'before_or_equal:today'],
        'zone_id' => ['nullable', 'exists:zones,id'],
        'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ];
}

public function save(): void
{
    $validated = $this->validate();   // données propres, ou erreurs affichées sous les champs
    // ...
}
```

- Les messages sont **en français** (`lang/fr/validation.php`). Nom lisible d'un champ : section `attributes` du même fichier (`'date_incident' => 'date de l’incident'`).
- Règle sur mesure en une ligne : `'titre' => ['required', function ($attr, $value, $fail) { if (str_contains($value, 'http')) $fail('Pas de lien dans le titre.'); }]`.
- Unicité en modification : `Rule::unique('zones', 'nom')->ignore($this->record?->id)`.

**Toujours valider côté serveur**, même si le champ HTML a `required` : le jury enverra des requêtes à la main.

## 9. Blade

```blade
{{ $signalement->titre }}                          {{-- affiche en échappant le HTML (sûr) --}}
{{ $signalement->created_at->diffForHumans() }}    {{-- « il y a 3 heures » --}}
{{ $signalement->date_incident?->format('d/m/Y') }}

@if ($liste->isEmpty()) ... @else ... @endif
@foreach ($liste as $item) ... @endforeach
@forelse ($liste as $item) ... @empty Aucun élément. @endforelse

@auth ... @endauth            @guest ... @endguest
@can('update', $signalement) ... @endcan         {{-- selon la Policy --}}

<a href="{{ route('signalements.show', $signalement) }}" wire:navigate>Voir</a>
<img src="{{ Storage::url($signalement->photo) }}" alt="{{ $signalement->titre }}">
{{ __('Log out') }}                                {{-- texte traduit via lang/fr.json --}}
```

**Jamais `{!! $variable !!}`** avec une donnée saisie par un utilisateur (faille XSS).

Composant Blade réutilisable (`resources/views/components/stat.blade.php`) :

```blade
@props(['label', 'valeur', 'icone' => 'chart-bar'])
<flux:card class="flex items-center gap-4">
    <flux:icon :name="$icone" class="size-8 text-accent" />
    <div>
        <flux:text>{{ $label }}</flux:text>
        <flux:heading size="xl">{{ $valeur }}</flux:heading>
    </div>
</flux:card>
```

Utilisation : `<x-stat label="Signalements" :valeur="$total" icone="exclamation-triangle" />`.

## 10. Livewire 4

Un fichier = la logique en haut, la vue en bas. Exemple réel (formulaire généré, simplifié) :

```php
<?php

use App\Models\Signalement;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Signalement')] class extends Component {
    use WithFileUploads;

    #[Locked]                                   // le navigateur ne peut pas le changer
    public ?Signalement $record = null;

    public string $titre = '';                  // lié à un champ par wire:model
    public string $niveau = 'faible';
    public $photo = null;

    public function mount(?Signalement $signalement = null): void   // au chargement
    {
        if ($signalement?->exists) {
            $this->authorize('update', $signalement);
            $this->record = $signalement;
            $this->titre = $signalement->titre;
            $this->niveau = $signalement->niveau;
        } else {
            $this->authorize('create', Signalement::class);
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'niveau' => ['required', Rule::in(Signalement::NIVEAU_OPTIONS)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function save(): void                // appelé par wire:submit="save"
    {
        $this->record
            ? $this->authorize('update', $this->record)
            : $this->authorize('create', Signalement::class);

        $validated = $this->validate();

        if ($this->record) {
            $this->record->update($validated);
            $record = $this->record;
        } else {
            $record = new Signalement($validated);
            $record->user()->associate(auth()->user());   // jamais depuis le formulaire
            $record->save();
        }

        Flux::toast(variant: 'success', text: 'Signalement enregistré.');
        $this->redirectRoute('signalements.show', $record, navigate: true);
    }
}; ?>

<section class="w-full max-w-2xl space-y-6">
    <flux:heading size="xl" level="1">{{ $record ? 'Modifier' : 'Ajouter' }} un signalement</flux:heading>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="titre" label="Titre" required />
        <flux:select wire:model="niveau" label="Niveau">
            @foreach (\App\Models\Signalement::NIVEAU_OPTIONS as $option)
                <flux:select.option :value="$option">{{ ucfirst($option) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
    </form>
</section>
```

**Les attributs à connaître**

| Attribut | Rôle |
|---|---|
| `#[Title('...')]` | Titre de l'onglet |
| `#[Locked]` | Propriété non modifiable depuis le navigateur (IDs, enregistrements) |
| `#[Computed]` | Propriété calculée, mise en cache pendant la requête : `$this->items` |
| `#[Url]` | Propriété synchronisée avec l'URL (`?search=...`) : filtres partageables |
| `#[On('evenement')]` | Méthode appelée quand un événement est émis |

**Liste avec recherche et pagination** (extrait réel de `⚡index`) :

```php
use WithPagination;

#[Url(except: '')]
public string $search = '';

public function updatedSearch(): void { $this->resetPage(); }   // revenir à la page 1

#[Computed]
public function items(): LengthAwarePaginator
{
    return Signalement::query()
        ->with('user')
        ->when($this->search !== '', fn ($q) => $q->where('titre', 'like', '%'.$this->search.'%'))
        ->latest()
        ->paginate(10);
}

public function delete(int $id): void
{
    $record = Signalement::findOrFail($id);
    $this->authorize('delete', $record);     // ← sans cette ligne : faille
    $record->delete();
    Flux::toast(variant: 'success', text: 'Signalement supprimé.');
}
```

**Les gestes côté vue**

| Besoin | Code |
|---|---|
| Lier un champ | `wire:model="titre"` |
| Mettre à jour à chaque frappe / après une pause | `wire:model.live` / `wire:model.live.debounce.300ms` |
| Appeler une méthode | `wire:click="toggleFavori({{ $item->id }})"` |
| Envoyer un formulaire | `<form wire:submit="save">` |
| Demander confirmation | `wire:confirm="Supprimer cet élément ?"` |
| Afficher pendant le chargement | `<div wire:loading>Chargement…</div>` (`wire:loading.delay` pour éviter le clignotement) |
| Cibler une action | `wire:loading wire:target="save"` |
| Désactiver pendant l'envoi | `wire:loading.attr="disabled"` |
| Navigation sans rechargement | `wire:navigate` sur les liens |
| Rafraîchir toutes les 10 s | `<div wire:poll.10s>` |
| Clé de ligne dans une boucle | `wire:key="row-{{ $item->id }}"` |
| Ne pas toucher à une zone (carte, graphique JS) | `wire:ignore` |

**Événements entre composants** : `$this->dispatch('signalement-cree');` puis, dans un autre composant, `#[On('signalement-cree')] public function rafraichir(): void { unset($this->items); }`.

**Erreurs fréquentes** : « Cannot mutate locked property » → propriété `#[Locked]` modifiée : normal, c'est la protection. Une propriété qui ne se met pas à jour → `wire:model` sans `.live` ne s'envoie qu'à la prochaine action. « Property type not supported » → type non gérable par Livewire (utiliser `string`, `int`, `bool`, `array`, un modèle).

## 11. Flux, Tailwind et identité visuelle

**Flux** (version gratuite ; documentation : fluxui.dev, filtrer « free ») :

```blade
<flux:heading size="xl" level="1">Titre de page</flux:heading>
<flux:subheading>Sous-titre</flux:subheading>
<flux:text>Paragraphe secondaire</flux:text>

<flux:button variant="primary" icon="plus" :href="route('signalements.create')" wire:navigate>Ajouter</flux:button>
<flux:button variant="ghost" size="sm" icon="pencil-square" />
<flux:button variant="danger" wire:click="delete({{ $item->id }})" wire:confirm="Supprimer ?">Supprimer</flux:button>

<flux:input wire:model="titre" label="Titre" description="Court et précis" required />
<flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Rechercher…" />
<flux:textarea wire:model="description" label="Description" rows="5" />
<flux:select wire:model.live="niveau" label="Niveau">
    <flux:select.option value="">Tous</flux:select.option>
    <flux:select.option value="critique">Critique</flux:select.option>
</flux:select>
<flux:checkbox wire:model="ouvert" label="Ouvert" />
<flux:switch wire:model.live="mine" label="Seulement les miens" />

<flux:badge color="red" size="sm">Critique</flux:badge>
<flux:card>Contenu encadré</flux:card>
<flux:callout icon="information-circle">Message d'information</flux:callout>
<flux:separator />
<flux:link :href="route('signalements.index')" wire:navigate>&larr; Retour</flux:link>
```

Tableau paginé :

```blade
<flux:table :paginate="$this->items">
    <flux:table.columns>
        <flux:table.column>Titre</flux:table.column>
        <flux:table.column>Niveau</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach ($this->items as $item)
            <flux:table.row wire:key="row-{{ $item->id }}">
                <flux:table.cell>{{ $item->titre }}</flux:table.cell>
                <flux:table.cell><flux:badge size="sm">{{ $item->niveau }}</flux:badge></flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
```

Fenêtre modale :

```blade
<flux:modal.trigger name="confirmer"><flux:button>Ouvrir</flux:button></flux:modal.trigger>

<flux:modal name="confirmer" class="md:w-96">
    <flux:heading>Confirmer ?</flux:heading>
    <flux:button wire:click="valider" variant="primary">Oui</flux:button>
</flux:modal>
```

Message après une action (PHP) : `Flux::toast(variant: 'success', text: 'Enregistré.');` (variantes : `success`, `warning`, `danger`). Icônes : noms Heroicons (heroicons.com) : `map-pin`, `bell`, `heart`, `chart-bar`, `exclamation-triangle`.

**Les 4 états de chaque écran** (le jury les remarque) :

```blade
@if ($this->items->isEmpty())
    <flux:card class="py-12 text-center">
        <flux:heading>Aucun signalement pour le moment</flux:heading>
        <flux:text class="mt-2">Soyez le premier à signaler un incident.</flux:text>
        <flux:button class="mt-4" variant="primary" :href="route('signalements.create')" wire:navigate>Signaler</flux:button>
    </flux:card>
@else
    {{-- tableau --}}
@endif
<div wire:loading.delay class="text-sm text-zinc-500">Chargement…</div>
```

**Tailwind — l'essentiel**

```
Espacements   p-4 px-6 py-2 m-4 mt-2 gap-4 space-y-6
Mise en page  flex flex-col items-center justify-between  grid grid-cols-1 md:grid-cols-3
Tailles       w-full max-w-2xl h-40 size-10
Texte         text-sm text-lg font-medium font-semibold text-zinc-500
Formes        rounded-lg rounded-xl border shadow-sm
Images        object-cover aspect-video
Sombre        dark:bg-zinc-900 dark:text-white
Responsive    sm: (≥640px)  md: (≥768px)  lg: (≥1024px)
```

**Mobile d'abord** : écrire pour le téléphone, puis ajouter `md:` / `lg:` : `grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3`.

**Identité visuelle du sujet** — deux endroits seulement :

```css
/* resources/css/app.css : bloc @theme (mode clair) ET le bloc du mode sombre juste en dessous */
--color-accent: var(--color-orange-500);
--color-accent-content: var(--color-orange-600);
--color-accent-foreground: var(--color-white);
```

Changer les **deux** blocs (clair et sombre) ; en sombre, prendre une teinte plus claire (`orange-400`) pour garder le contraste.

```php
// app/Providers/Filament/AdminPanelProvider.php
->colors(['primary' => Color::Orange])
->brandName(config('app.name'))
```

Plus : `APP_NAME` dans `.env`, le logo (`resources/views/components/app-logo.blade.php`), la page d'accueil (`welcome.blade.php`).

**Ajouter une entrée au menu** (le générateur le fait) : dans `sidebar.blade.php`, **au-dessus** du marqueur `{{-- make:feature:nav --}}` :

```blade
<flux:sidebar.item icon="map" :href="route('carte')" :current="request()->routeIs('carte')" wire:navigate>
    Carte
</flux:sidebar.item>
```

**Erreur fréquente** : une classe Tailwind sans effet → `composer run dev` ne tourne pas ou le build est ancien : `npm run build`, puis Ctrl+F5.

## 12. Authentification, rôles et Policies

**Authentification** : fournie par le starter kit (Fortify). Déjà en place : inscription, connexion, mot de passe oublié, **double authentification (2FA)**, confirmation du mot de passe, **limite de 5 essais par minute** (`FortifyServiceProvider`), mots de passe hachés. Le jury compte ces éléments comme des fonctionnalités de sécurité : **on les déclare**.

```php
auth()->user();          // l'utilisateur connecté (ou null)
auth()->id();            // son id
auth()->check();         // connecté ?
```

**Rôles** : une colonne `role` sur `users` (`user` ou `admin`) et une méthode sur le modèle :

```php
public function isAdmin(): bool
{
    return $this->role === 'admin';
}
```

`role` n'est **pas** dans `#[Fillable]` : un utilisateur ne peut pas s'inscrire « admin ». Pour promouvoir : `/admin/users` (admin), ou sur le serveur :

```bash
php84 artisan tinker --execute="App\Models\User::where('email', 'x@y.z')->update(['role' => 'admin']);"
```

**Policies** : une classe par modèle, découverte automatiquement (`App\Policies\SignalementPolicy` pour `App\Models\Signalement`).

```php
class SignalementPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Signalement $s): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Signalement $s): bool
    {
        return $user->isAdmin() || $s->user_id === $user->id;
    }
    public function delete(User $user, Signalement $s): bool
    {
        return $user->isAdmin() || $s->user_id === $user->id;
    }
    public function moderer(User $user): bool          // action métier sur mesure
    {
        return $user->isAdmin();
    }
}
```

Utilisation :

```php
$this->authorize('update', $signalement);             // composant Livewire → 403 si refusé
$this->authorize('create', Signalement::class);       // action sans instance
$user->can('moderer', Signalement::class);            // test booléen
```

```blade
@can('update', $signalement) <flux:button>Modifier</flux:button> @endcan
```

**Règle réservée aux admins sans modèle** (page de statistiques, par exemple) : dans `AppServiceProvider::boot()`, `Gate::define('admin', fn (User $user) => $user->isAdmin());`, puis sur la route `->middleware('can:admin')` et dans Blade `@can('admin')`.

**Données privées** : une donnée visible seulement par son propriétaire → `view()` renvoie `$user->isAdmin() || $s->user_id === $user->id`, **et** la liste filtre : `->when(! auth()->user()->isAdmin(), fn ($q) => $q->whereBelongsTo(auth()->user()))`.

## 13. Sécurité : les règles et les attaques

**Ce que le jury teste, et où c'est géré chez nous**

| Famille testée | Protection | Où |
|---|---|---|
| Authentification | Fortify : hash, 2FA, confirmation | starter kit |
| Brute force | 5 essais/minute sur la connexion (réponse 429) | `FortifyServiceProvider`, `SecurityTest` |
| Contrôle d'accès | Policies + `authorize` dans chaque action | `app/Policies/`, pages |
| Rôles | `role` hors `#[Fillable]`, `/admin` réservé | `User`, Filament |
| Validation | `rules()` sur chaque formulaire | pages `⚡form` |
| Endpoints | Routes métier dans le groupe `auth` | `routes/features.php` |
| Exposition de données | `APP_DEBUG=false`, `#[Hidden]` sur les secrets de `User` | `.env`, `User` |
| En-têtes HTTP | X-Frame-Options, nosniff, Referrer-Policy, HSTS, HTTPS forcé | `SecurityHeaders`, `AppServiceProvider` |
| CSRF | Automatique (formulaires Blade et Livewire) | Laravel |

**Les 5 attaques qu'un juré fera en 10 minutes**

1. **Changer l'ID dans l'URL** (`/signalements/12/edit` avec le compte d'un autre) → **403**.
2. **Appeler une action Livewire à la main** (`delete(12)` depuis la console du navigateur) → **403**.
3. **Ajouter un champ** à la requête (`role=admin`, `user_id=1`, `statut=valide`) → **ignoré**.
4. **Ouvrir `/admin`** avec un compte normal → **403**.
5. **Injecter du HTML/JS** (`<script>alert(1)</script>`) → affiché comme du texte.

**Les parades, dans le code**

```php
// 1 et 2 : vérifier les droits dans CHAQUE méthode publique
public function delete(int $id): void
{
    $signalement = Signalement::findOrFail($id);
    $this->authorize('delete', $signalement);
    $signalement->delete();
}

// 1 : verrouiller l'enregistrement du composant
#[Locked]
public ?Signalement $record = null;

// 3 : champs sensibles assignés dans le code, jamais depuis le formulaire
$signalement = new Signalement($validated);
$signalement->user()->associate(auth()->user());
$signalement->statut = 'en_attente';
$signalement->save();
```

**Limiter une action sensible** (envoi, signalement, appel IA) :

```php
use Illuminate\Support\Facades\RateLimiter;

$cle = 'signalement:'.auth()->id();
if (RateLimiter::tooManyAttempts($cle, 10)) {
    $this->addError('titre', 'Trop de signalements. Réessayez dans une minute.');
    return;
}
RateLimiter::hit($cle, 60);
```

**Injection SQL** : Eloquent protège automatiquement. Seul danger : `whereRaw` / `DB::raw` avec une valeur utilisateur concaténée. Toujours des paramètres : `->whereRaw('LOWER(titre) LIKE ?', ['%'.$s.'%'])`.

**Autres règles** : uploads limités (chapitre 15) ; messages d'erreur génériques ; aucune donnée personnelle d'autrui affichée sans règle ; clés d'API uniquement côté serveur ; texte inséré en JavaScript via `textContent`, jamais `innerHTML`.

**Annonces « sécurité » probables et réponses prêtes**

| Annonce | Réponse |
|---|---|
| « Authentification sécurisée », « Limiter les tentatives de connexion » | Déjà là → **déclarer** et montrer |
| « Rôles utilisateur / administrateur » | Déjà là → déclarer, montrer `/admin/users` |
| « Protéger une zone sensible » | Policy ou `can:admin` + test 403 (15 min) |
| « Validation renforcée » | Compléter `rules()` (tailles, formats, `Rule::in`) |
| « Journal des actions » | Modèle `ActionLog` + ressource Filament en lecture seule (30 min) |
| « Limiter le spam » | `RateLimiter` (15 min) |
| « Masquer les données sensibles » | Policy `view` restrictive + masquage dans les vues |

**Journal des actions** (exemple complet) :

```php
// migration
$table->id();
$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
$table->string('action', 50);              // created, updated, deleted, login…
$table->string('subject_type')->nullable();
$table->unsignedBigInteger('subject_id')->nullable();
$table->string('ip', 45)->nullable();
$table->timestamps();

// app/Models/ActionLog.php
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'ip'])]
class ActionLog extends Model
{
    public static function record(string $action, ?Model $subject = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip' => request()->ip(),
        ]);
    }
}
```

Appel : `ActionLog::record('deleted', $signalement);`. Ici `user_id` est remplissable car il ne vient **jamais** d'un formulaire.

## 14. Filament : l'espace admin

Tout ce qui est dans `/admin` est réservé aux admins (`User::canAccessPanel()`), et les Policies s'y appliquent aussi.

```bash
php artisan make:filament-resource Signalement --generate
```

Crée `app/Filament/Resources/Signalements/` : la ressource, `Schemas/SignalementForm.php` (formulaire), `Tables/SignalementsTable.php` (liste), `Pages/` (liste, création, modification). **Le code généré doit toujours être corrigé** :

**Formulaire**

```php
use Filament\Forms\Components\{DatePicker, FileUpload, Select, Textarea, TextInput, Toggle};

return $schema->components([
    TextInput::make('titre')->required()->maxLength(255),
    Textarea::make('description')->columnSpanFull(),
    Select::make('niveau')
        ->options(array_combine(Signalement::NIVEAU_OPTIONS, array_map('ucfirst', Signalement::NIVEAU_OPTIONS)))
        ->required(),
    FileUpload::make('photo')->image()->disk('public')->directory('signalements')->maxSize(2048),
    DatePicker::make('date_incident')->required(),
    Toggle::make('ouvert'),
]);
```

⚠️ Le générateur Filament ajoute `Select::make('user_id')`. Comme `user_id` est **hors** `#[Fillable]`, la création échouerait. Deux choix : **supprimer ce champ** (l'admin modère, il ne crée pas au nom des autres), ou assigner explicitement dans `Pages/CreateSignalement.php` :

```php
protected function handleRecordCreation(array $data): Model
{
    $record = new Signalement($data);
    $record->user_id = $data['user_id'];
    $record->save();

    return $record;
}
```

**Tableau**

```php
use Filament\Actions\{Action, EditAction};
use Filament\Tables\Columns\{IconColumn, ImageColumn, TextColumn};
use Filament\Tables\Filters\SelectFilter;

return $table
    ->columns([
        ImageColumn::make('photo')->disk('public')->circular(),
        TextColumn::make('titre')->searchable()->limit(40),
        TextColumn::make('user.name')->label('Auteur')->searchable(),
        TextColumn::make('niveau')->badge()->color(fn (string $state) => match ($state) {
            'critique' => 'danger', 'moyen' => 'warning', default => 'gray',
        }),
        IconColumn::make('ouvert')->boolean(),
        TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
    ])
    ->defaultSort('created_at', 'desc')
    ->filters([
        SelectFilter::make('niveau')->options(array_combine(Signalement::NIVEAU_OPTIONS, Signalement::NIVEAU_OPTIONS)),
    ])
    ->recordActions([
        Action::make('valider')
            ->label('Valider')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Signalement $record) => $record->statut === 'en_attente')
            ->action(function (Signalement $record) {
                $record->statut = 'valide';      // hors Fillable : assignation explicite
                $record->save();
            }),
        EditAction::make(),
    ]);
```

**Libellés en français** dans la ressource : `protected static ?string $modelLabel = 'signalement';`, `protected static ?string $pluralModelLabel = 'signalements';`, icône `protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;`.

**Ressource en lecture seule** (journal) : `public static function canCreate(): bool { return false; }` et pas d'`EditAction`.

**Statistiques sur le tableau de bord admin**

```bash
php artisan make:filament-widget StatsOverview --stats-overview
```

```php
use Filament\Widgets\StatsOverviewWidget\Stat;

protected function getStats(): array
{
    return [
        Stat::make('Signalements', Signalement::count()),
        Stat::make('Critiques', Signalement::where('niveau', 'critique')->count())->color('danger'),
        Stat::make('Utilisateurs', User::count()),
    ];
}
```

Graphique : `php artisan make:filament-widget SignalementsParJour --chart` puis `getType()` → `'line'` et `getData()` → `['datasets' => [['label' => 'Signalements', 'data' => $valeurs]], 'labels' => $jours]`.

**Rappels** : ne jamais afficher un mot de passe, un secret 2FA ou un jeton ; un champ hors `#[Fillable]` modifié depuis Filament doit être assigné explicitement (voir `Pages/EditUser.php`), sinon il est ignoré sans erreur.

## 15. Fichiers et images

```php
use Livewire\WithFileUploads;

use WithFileUploads;
public $photo = null;

// règle
'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

// enregistrement (nom aléatoire, disque public)
if ($this->photo) {
    if ($this->record?->photo) {
        Storage::disk('public')->delete($this->record->photo);   // supprimer l'ancienne
    }
    $validated['photo'] = $this->photo->store('signalements', 'public');
} else {
    unset($validated['photo']);   // ne pas écraser la photo existante par null
}
```

```blade
<flux:input type="file" wire:model="photo" label="Photo" accept="image/jpeg,image/png,image/webp" />
<div wire:loading wire:target="photo"><flux:text>Envoi en cours…</flux:text></div>
@if ($photo)
    <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="h-40 rounded-lg object-cover">
@endif

{{-- affichage --}}
<img src="{{ Storage::url($item->photo) }}" alt="{{ $item->titre }}" class="aspect-video w-full rounded-xl object-cover">
```

Plusieurs fichiers : `public array $photos = [];`, règle `'photos.*' => ['image', 'max:2048']`, `<flux:input type="file" wire:model="photos" multiple />`.

`php artisan storage:link` (une fois en local ; en ligne, Hodifly le fait à chaque déploiement) : sans lui, les images sont invisibles. Toujours un `alt`, toujours `object-cover`.

## 16. Carte et géolocalisation

Fonctionnalité fréquente (points de regroupement, incidents, services proches). Le projet a un **kit carte** prêt à l'emploi, basé sur **Leaflet** + tuiles **OpenStreetMap** (gratuit, sans clé API) :

| Pièce | Rôle |
|---|---|
| `resources/js/carte.js` | Entrée Vite **séparée** : Leaflet + sa CSS, icônes de marqueurs corrigées pour Vite. Chargée **uniquement** sur les pages qui affichent `<x-carte>` (pas de poids en plus ailleurs, bon pour Lighthouse). |
| `<x-carte>` (`app/View/Components/Carte.php` + `resources/views/components/carte.blade.php`) | Le composant Blade à poser dans une page. |
| `App\Models\Concerns\HasCoordinates` | Trait de modèle : `geolocalises()`, `proches()`, `pointCarte()`. |

> Une entité générée avec des champs `latitude` et `longitude` a déjà tout (chapitre 25) : ce chapitre sert à comprendre et à ajouter une carte ailleurs.

### Afficher des points (mode lecture)

```blade
<x-carte :points="$this->points" label="Carte des signalements" />
```

Chaque point est un tableau `['lat' => -18.91, 'lng' => 47.52, 'titre' => 'Analakely', 'url' => route('signalements.show', $s)]` (`titre` et `url` facultatifs : la bulle affiche le titre et un lien « Voir le détail »). Le plus simple est de passer par le trait :

```php
#[Computed]
public function points(): array
{
    return Signalement::geolocalises()
        ->latest()
        ->limit(200)                       // jamais toute la table
        ->get()
        ->map(fn (Signalement $s) => $s->pointCarte($s->titre, route('signalements.show', $s)))
        ->filter()                         // pointCarte() renvoie null sans position
        ->values()
        ->all();
}
```

Propriétés du composant :

| Propriété | Défaut | Rôle |
|---|---|---|
| `points` | `[]` | Marqueurs. Avec plusieurs points, la carte cadre automatiquement sur eux. |
| `mode` | `lecture` | `lecture` ou `choix` (voir plus bas). |
| `centre` | `[-18.91, 47.52]` (Antananarivo) | Centre quand il n'y a aucun point. |
| `zoom` | `13` | De 1 à 19. |
| `hauteur` | `20rem` | Valeur CSS simple (`px`, `rem`, `vh`, `%`…) ; toute autre valeur est ignorée. |
| `label` | `Carte` | `aria-label` de la carte (accessibilité : dites ce qu'elle montre). |
| `champ-lat` / `champ-lng` | `latitude` / `longitude` | Propriétés Livewire remplies en mode choix. |

### Choisir un emplacement (mode choix, formulaire)

Dans un composant Livewire qui a `public string $latitude = ''` et `public string $longitude = ''` :

```blade
<flux:input wire:model="latitude" label="Latitude" type="number" step="any" />
<flux:input wire:model="longitude" label="Longitude" type="number" step="any" />
<x-carte mode="choix" label="Emplacement du signalement" />
```

- Un **clic** sur la carte place le repère et remplit `latitude` / `longitude` (comme un `wire:model` : les champs se mettent à jour).
- Le bouton **« Me localiser »** utilise `navigator.geolocation` ; si l'utilisateur refuse, un message clair lui dit de cliquer sur la carte. Nécessite HTTPS (le cas en ligne) ; autorisé par nos en-têtes (`Permissions-Policy: geolocation=(self)`).
- En modification, le repère est placé sur la position enregistrée ; si on tape des coordonnées à la main, il suit.
- Côté serveur, **toujours** valider : `'latitude' => ['nullable', 'numeric', 'between:-90,90']`, `'longitude' => ['nullable', 'numeric', 'between:-180,180']`.

### « Autour de moi » : trier par distance

```php
use App\Models\Concerns\HasCoordinates;

class Signalement extends Model
{
    use HasCoordinates;
}

Signalement::proches($lat, $lng, 5)->get();   // les 5 plus proches, du plus proche au plus lointain
Signalement::geolocalises()->count();         // seulement ceux qui ont une position
```

`proches()` trie par **distance au carré** (pas de racine ni de trigonométrie en SQL) : fonctionne à l'identique sur **SQLite** (local) et **MariaDB** (Hodi). L'écart de longitude est corrigé par `cos(latitude)`, calculé en PHP. Largement suffisant pour classer des lieux dans une ville ; pour afficher « à 1,2 km », calculer la distance en PHP sur les quelques résultats. Le trait suppose des colonnes nommées exactement `latitude` et `longitude`.

### Sécurité et pièges

- Les titres des bulles sont posés avec **`textContent`**, jamais `innerHTML` : un titre `<b>test</b>` s'affiche tel quel (testé dans `tests/Feature/CarteTest.php`).
- Les liens des bulles ne sont gardés que s'ils sont relatifs (`/…`) ou en `http(s)://` : un `javascript:` est ignoré.
- Le composant est en **`wire:ignore`** : Livewire ne redessine pas la carte. Quand les points changent (filtre de la liste), sa `wire:key` change et la carte est recréée proprement.
- Une carte dans un bloc replié (`<details>`, modal) se redimensionne toute seule à l'ouverture.
- Ne pas afficher la position **exacte** d'une personne (domicile…) sans règle de Policy dédiée (convention, règle 12).
- Style ou carte absente en local : `composer run dev` doit tourner (ou `npm run build`), puis Ctrl+F5.

## 17. Notifications et e-mails

Le projet contient un **kit e-mail / notifications** générique (aucun lien avec un sujet). Il faut le réutiliser, pas le réécrire.

| Élément | Fichier |
|---|---|
| Notification générique (application + e-mail) | `app/Notifications/Avis.php` |
| Table `notifications` | `database/migrations/2026_09_30_080000_create_notifications_table.php` |
| Cloche dans la sidebar (non lus, 10 dernières, « tout marquer comme lu ») | `resources/views/components/⚡cloche-notifications.blade.php` |
| Commande de contrôle d'envoi | `php artisan app:test-mail adresse@exemple.com` |
| E-mails Laravel en français (réinitialisation, vérification, « Bonjour ! », « Cordialement, »…) | `lang/fr.json` |
| Tests | `tests/Feature/KitEmailTest.php` |

**Envoyer un avis** (n'importe où : Policy, composant Livewire, commande) :

```php
use App\Notifications\Avis;

$user->notify(new Avis(
    sujet: 'Signalement validé',
    lignes: ['Votre signalement « '.$signalement->titre.' » a été publié.'],
    libelle: 'Voir le signalement',                       // facultatif
    url: route('signalements.show', $signalement),        // facultatif : sans URL, pas de bouton
));

Notification::send(User::where('role', 'admin')->get(), new Avis('Nouveau signalement critique', [$s->titre]));
```

Canaux : `database` (cloche) + `mail`. Le sujet et les lignes sont affichés avec `{{ }}` (échappés). Le lien n'est affiché que s'il commence par `http(s)://` ou `/`.

**La cloche** est un composant Livewire (`<livewire:cloche-notifications />`, déjà dans la sidebar). Elle ne lit que `auth()->user()->notifications()` : les notifications d'un autre utilisateur ne sont jamais chargées, et `markAsRead` fait un `findOrFail` sur *ses* notifications (l'identifiant d'un autre donne une 404). Pour une notification propre à un sujet, créer une classe dédiée (`php artisan make:notification`) sur le même modèle.

**Traductions** : Laravel envoie ses e-mails en anglais avec des chaînes du type `Reset Password Notification`. Les traductions vivent dans `lang/fr.json` (clé = texte anglais exact). Si un nouvel e-mail apparaît en anglais, chercher la chaîne dans `vendor/laravel/framework/src/Illuminate/{Auth,Notifications}` et l'ajouter au JSON. `APP_LOCALE=fr` doit être défini dans le `.env` du serveur.

**Configuration**

- Local : `MAIL_MAILER=log` → les e-mails s'écrivent dans `storage/logs/laravel.log`.
- Production (cPanel Hodi) : `MAIL_MAILER=sendmail`, `MAIL_FROM_ADDRESS="noreply@virtualvisionsy.madagascar.webcup.hodi.cloud"`, `QUEUE_CONNECTION=sync` (pas de worker sur l'hébergement mutualisé : les envois partent immédiatement).
- **Vérifier en production**, sans tinker : `cd ~/app && php84 artisan app:test-mail votre@adresse.com`, puis regarder la boîte de réception **et les spams**. En cas d'échec, la commande affiche l'erreur du transport ; sinon `tail -n 60 storage/logs/laravel.log`.
- Les variables de production se changent dans Hodifly (Modifier → Variables), puis **Déployer**. Les migrations passent toutes seules au déploiement.

**Tests** : `Notification::fake()` + `Notification::assertSentTo($user, ResetPassword::class)` pour vérifier qu'un envoi a lieu ; `Mail::fake()` + `Mail::assertSent(...)` pour un Mailable ; `app()->setLocale('fr')` dans le test pour vérifier le texte français (le `phpunit.xml` force `en`).

## 18. Services externes : API de l'orga, IA

**Configuration** : jamais d'URL ni de clé dans le code.

```
# .env
ORGA_API_URL=https://...
ORGA_API_TOKEN=...
OPENROUTER_API_KEY=...
OPENROUTER_MODEL=nom-du-modele:free
```

```php
// config/services.php
'orga' => ['url' => env('ORGA_API_URL'), 'token' => env('ORGA_API_TOKEN')],
'openrouter' => ['key' => env('OPENROUTER_API_KEY'), 'model' => env('OPENROUTER_MODEL')],
```

**Client de l'API de l'orga** (cache, délai, nouvel essai, repli) :

```php
// app/Services/OrgaApi.php
class OrgaApi
{
    /** @return array<int, array<string, mixed>> */
    public function alertes(): array
    {
        try {
            return Cache::remember('orga:alertes', now()->addMinutes(5), fn () => Http::baseUrl(config('services.orga.url'))
                ->withToken(config('services.orga.token'))
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 500)
                ->get('/alertes')
                ->throw()
                ->json('data', []));
        } catch (\Throwable $e) {
            report($e);
            return [];            // la page affiche « Données momentanément indisponibles »
        }
    }
}
```

Utilisation : `app(OrgaApi::class)->alertes()`. Importer en base : une commande `php artisan make:command SyncAlertes` avec `Alerte::updateOrCreate(['external_id' => $d['id']], [...])`, planifiée (chapitre 19).

**IA (OpenRouter)** — la clé ne quitte jamais le serveur :

```php
// app/Services/Ai.php
class Ai
{
    public function ask(string $system, string $prompt): ?string
    {
        if (! config('services.openrouter.key')) {
            return null;
        }

        return Cache::remember('ai:'.md5($system.$prompt), now()->addHour(), function () use ($system, $prompt) {
            $response = Http::withToken(config('services.openrouter.key'))
                ->timeout(25)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => config('services.openrouter.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            return $response->successful() ? $response->json('choices.0.message.content') : null;
        });
    }
}
```

Dans le composant : **limiter par utilisateur** (chapitre 13, 5 demandes/minute), afficher « Service indisponible, réessayez » si `null`, et afficher la réponse avec `{{ }}` (jamais comme du HTML). Limites OpenRouter : 20 requêtes/minute, 50/jour (1000 après un premier crédit). Le cache évite de consommer le quota deux fois pour la même question.

## 19. Cache, tâches planifiées, file d'attente

**Cache** : `Cache::remember('stats:accueil', 60, fn () => [...])` (60 secondes). Vider : `php artisan cache:clear`.

**Tâches planifiées** : dans `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-alertes')->everyFiveMinutes();
Schedule::call(fn () => Signalement::where('statut', 'en_attente')->where('created_at', '<', now()->subDays(7))->update(['statut' => 'expire']))->daily();
```

En ligne, **Hodifly ajoute lui-même** la tâche cron `schedule:run` (chaque minute) dès que `routes/console.php` planifie quelque chose. Elle est visible dans cPanel → Tâches Cron ; rien à faire à la main.

En local : `php artisan schedule:work` (ou `schedule:run` pour un passage). Liste : `php artisan schedule:list`.

**File d'attente** : en production, `QUEUE_CONNECTION=sync` (tout s'exécute tout de suite, pas de worker à surveiller). Suffisant pour nos volumes.

## 20. Statistiques et export CSV

```php
Signalement::count();
Signalement::where('niveau', 'critique')->count();
Signalement::selectRaw('niveau, count(*) as total')->groupBy('niveau')->pluck('total', 'niveau');
// ['faible' => 12, 'moyen' => 7, 'critique' => 3]
Signalement::where('created_at', '>=', now()->subDays(7))->count();
Zone::withCount('signalements')->orderByDesc('signalements_count')->take(5)->get();
```

Page de tableau de bord utilisateur : des `<x-stat>` (chapitre 9) dans une grille. Barres simples sans bibliothèque :

```blade
@foreach ($this->parNiveau as $niveau => $total)
    <div class="flex items-center gap-3">
        <span class="w-20 text-sm">{{ ucfirst($niveau) }}</span>
        <div class="h-3 rounded bg-accent" style="width: {{ $max ? round($total / $max * 100) : 0 }}%"></div>
        <span class="text-sm">{{ $total }}</span>
    </div>
@endforeach
```

**Export CSV** depuis un composant Livewire :

```php
public function export()
{
    $this->authorize('viewAny', Signalement::class);

    return response()->streamDownload(function () {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");                                // accents lisibles dans Excel
        fputcsv($out, ['Titre', 'Niveau', 'Auteur', 'Date'], ';');
        Signalement::with('user')->latest()->chunk(200, function ($lignes) use ($out) {
            foreach ($lignes as $s) {
                fputcsv($out, [$s->titre, $s->niveau, $s->user?->name, $s->created_at->format('d/m/Y')], ';');
            }
        });
        fclose($out);
    }, 'signalements-'.now()->format('Ymd').'.csv');
}
```

Bouton : `<flux:button wire:click="export" icon="arrow-down-tray">Exporter</flux:button>`.

## 21. Données de démo : factories et seeders

Une application vide paraît inachevée. Chaque entité a une factory **crédible** :

```php
// database/factories/SignalementFactory.php
public function definition(): array
{
    return [
        'user_id' => User::factory(),
        'titre' => fake()->randomElement([
            'Inondation rue Andrianampoinimerina', 'Arbre tombé à Isoraka', 'Coupure d’électricité à Ivandry',
        ]),
        'description' => fake('fr_FR')->paragraph(),
        'niveau' => fake()->randomElement(Signalement::NIVEAU_OPTIONS),
        'latitude' => fake()->latitude(-18.95, -18.85),
        'longitude' => fake()->longitude(47.48, 47.56),
        'created_at' => fake()->dateTimeBetween('-10 days', 'now'),
    ];
}

public function critique(): static        // état nommé
{
    return $this->state(fn () => ['niveau' => 'critique']);
}
```

```php
// database/seeders/DatabaseSeeder.php (sous le marqueur // make:feature:seeders)
Signalement::factory(20)->recycle($users)->create();
Signalement::factory(3)->critique()->recycle($users)->create();
```

`recycle($users)` réutilise les utilisateurs existants. En local : `php artisan migrate:fresh --seed` → comptes `admin@example.com` / `user@example.com`, mot de passe `password`.

**En production**, jamais `db:seed` complet (il ne crée d'ailleurs les comptes de test que hors production). Pour remplir l'application en ligne : un seeder dédié **sans aucun compte** :

```bash
php artisan make:seeder DemoContentSeeder
php84 artisan db:seed --class=DemoContentSeeder --force     # sur le serveur
```

Ou saisir les données de démo **via l'application**, avec les comptes jury : c'est aussi un test.

## 22. Tests avec Pest

```php
use App\Models\{Signalement, User};
use Livewire\Livewire;

test('un invité est redirigé vers la connexion', function () {
    $this->get(route('signalements.index'))->assertRedirect(route('login'));
});

test('un autre utilisateur ne peut pas modifier', function () {
    $signalement = Signalement::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('signalements.edit', $signalement))
        ->assertForbidden();
});

test('le formulaire refuse un niveau inventé', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::signalements.form')
        ->set('titre', 'Test')
        ->set('niveau', 'apocalypse')
        ->call('save')
        ->assertHasErrors(['niveau']);
});

test('on ne peut pas se donner un statut réservé', function () {
    Livewire::actingAs($user = User::factory()->create())
        ->test('pages::signalements.form')
        ->set('titre', 'Test')
        ->set('niveau', 'faible')
        ->call('save');

    expect(Signalement::where('user_id', $user->id)->first()->statut)->toBe('en_attente');
});

test('un admin peut valider', function () {
    $admin = User::factory()->admin()->create();
    // ...
});
```

**Simuler l'extérieur** :

```php
Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Résumé']]]])]);
Notification::fake();  /* ... */  Notification::assertSentTo($admin, SignalementCritique::class);
Storage::fake('public');
->set('photo', UploadedFile::fake()->image('photo.jpg'))
```

**Lancer** : `php artisan test` (tout), `php artisan test --filter=Signalement` (un fichier), `php artisan test --parallel` (plus rapide).

À savoir : les tests tournent en anglais (`APP_LOCALE=en` dans `phpunit.xml`) car ceux du starter kit vérifient des textes anglais. La CI lance aussi **Pint** et **PHPStan** : une erreur de type bloque la PR.

**Le minimum par fonctionnalité** : « le propriétaire peut », « un autre reçoit 403 », « une valeur invalide est refusée », et un test par règle métier.

## 23. Déboguer

| Symptôme | Réflexe |
|---|---|
| Page blanche / erreur 500 | `storage/logs/laravel.log` (la fin du fichier) |
| Changement de style invisible | `composer run dev` doit tourner, ou `npm run build` ; Ctrl+F5 |
| « Route not defined » | `php artisan route:list --path=...` |
| Champ non enregistré | Dans `#[Fillable]` ? Dans `rules()` ? |
| 403 inattendu | Relire la Policy et l'appel `authorize` |
| « Column not found » / « no such table » | `php artisan migrate` |
| Modification non prise en compte | `php artisan optimize:clear` |
| Livewire ne réagit pas | Console du navigateur (F12) ; `wire:model.live` ? `wire:key` dans les boucles ? |
| Une valeur mystère | `dd($variable);` (affiche et arrête) ou `logger($variable);` (dans le log) |

Outils : `php artisan tinker` (console PHP sur l'application : `Signalement::latest()->first()`), `php artisan about` (état général). Avec Claude Code : coller **l'erreur exacte** et les 20 dernières lignes du log, jamais « ça ne marche pas ». Retirer tous les `dd()` avant de commiter.

## 24. Déployer (Hodifly)

**Le principe** : chaque merge sur `main` est déployé **automatiquement** par Hodifly (outil de cPanel Hodi relié au dépôt GitHub). Réglages dans `hodifly.json` (PHP 8.4, `npm run build`, racine `public`, 10 versions gardées, pas d'aperçu des PR).

À chaque déploiement, Hodifly :

1. récupère `main` dans un nouveau dossier de version (`releases/…`) ;
2. écrit le `.env` à partir des **variables du projet** (hors du site public) ;
3. `composer install --no-dev`, puis `npm run build` ;
4. `migrate --force` ;
5. met en cache configuration et routes, relie `storage/` (persistant entre les versions) et `public/storage` ;
6. **bascule** le site sur la nouvelle version. Si une étape échoue, l'ancienne version reste en ligne.

Durée : 1 à 2 minutes. Journal complet : cPanel → Hodifly → **Journaux**. E-mail en cas d'échec.

**Les variables** (Hodifly → Modifier → Variables d'environnement), jamais dans Git :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://virtualvisionsy.madagascar.webcup.hodi.cloud
APP_KEY=base64:...            (ne jamais la changer : sessions et 2FA en dépendent)
APP_LOCALE=fr
APP_FAKER_LOCALE=fr_FR
DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS=noreply@virtualvisionsy.madagascar.webcup.hodi.cloud
OPENROUTER_API_KEY=...
```

Une variable modifiée n'est prise en compte qu'au déploiement suivant : bouton **Déployer**. Le fichier `.env` du serveur est **réécrit** à chaque déploiement : le modifier à la main ne sert à rien.

**Sur le serveur** (Terminal cPanel), `~/app` pointe toujours vers la version en ligne :

```bash
cd ~/app
php84 artisan migrate:status
tail -n 60 storage/logs/laravel.log
bash scripts/sauvegarde-base.sh avant-pr12     # sauvegarde manuelle avant une migration risquée
```

**Sauvegardes de la base** : `scripts/sauvegarde-base.sh` tourne toutes les 30 minutes (cron cPanel) et garde les 48 dernières dans `~/backups` ; JetBackup (cPanel) garde en plus des copies quotidiennes.

**Ce qu'on ne fait jamais en production** : `migrate:fresh`, `db:seed`, `key:generate`, `APP_DEBUG=true`, modifier du code ou le `.env` directement sur le serveur, supprimer le bloc `AddHandler` de `public/.htaccess`, merger une PR dont la CI est rouge.

**Revenir en arrière**

- Le code : Hodifly → **Restaurer** → version précédente (instantané), puis `git revert <sha>` en local et push pour que `main` corresponde.
- La base (dernier recours) : `gunzip -c ~/backups/<fichier>.sql.gz | mysql -u <utilisateur> -p <base>`.

## 25. Le générateur `make:feature`

```bash
php artisan make:feature PointRegroupement \
  --fields="nom:string,adresse:string,capacite:integer,ouvert:boolean,niveau:enum(faible/moyen/eleve),photo:image?,latitude:decimal?,longitude:decimal?" \
  --label="Point de regroupement" --plural="Points de regroupement" --icon=map-pin
php artisan migrate
```

(Sous PowerShell, tout sur **une seule ligne**, sans les `\`.)

| Type | Colonne | Champ du formulaire | Validation |
|---|---|---|---|
| `string` | `string` | `flux:input` | `string`, `max:255` |
| `text` | `text` | `flux:textarea` | `string`, `max:5000` |
| `integer` | `integer` | `flux:input type=number` | `integer` |
| `decimal` | `decimal(10,7)` si le champ s'appelle `latitude`/`longitude` (ou `lat`/`lng`/`lon`), sinon `decimal(12,2)` | `flux:input type=number step=any` | `numeric` (+ `between:-90,90` / `between:-180,180` pour les coordonnées) |
| `boolean` | `boolean` | `flux:checkbox` | `boolean` |
| `date` / `datetime` | `date` / `dateTime` | `flux:input type=date / datetime-local` | `date` |
| `enum(a/b/c)` | `string` indexé + constante `X_OPTIONS` | `flux:select` + filtre dans la liste | `Rule::in` |
| `image` | chemin | `flux:input type=file` + aperçu | `image`, `mimes`, `max:2048` |

Suffixe `?` = facultatif (colonne `nullable`, règle `nullable`).

**Ce qu'il génère** : migration (avec `user_id`), modèle (`#[Fillable]` sans `user_id`, casts, constantes), factory (`fr_FR`, coordonnées à Antananarivo), policy (lecture/création pour tous les connectés, modification/suppression par le propriétaire ou un admin), 3 pages (liste avec recherche, filtres, « mes éléments », pagination, suppression ; formulaire avec validation et upload ; détail), routes, entrée de menu, ligne de seeder, 6 tests.

**Entité géolocalisée** : si les champs s'appellent exactement `latitude` et `longitude` (type `decimal`), le générateur ajoute aussi automatiquement (chapitre 16) :

- le trait `HasCoordinates` au modèle (`geolocalises()`, `proches()`, `pointCarte()`) ;
- `<x-carte mode="choix">` dans le formulaire (clic sur la carte ou « Me localiser ») ;
- `<x-carte>` sur la page détail (si la position est renseignée) ;
- une carte **repliable** au-dessus de la liste, qui suit la recherche et les filtres (200 points au maximum).

Sans ces deux champs, rien de tout cela n'est généré. La requête de recherche/filtres de la liste est dans une méthode `filteredQuery()`, réutilisée par la liste et par la carte.

**Marqueurs** (ne jamais les supprimer) : `// make:feature:routes` (routes), `{{-- make:feature:nav --}}` (menu), `// make:feature:seeders` (seeder).

**Après génération, toujours** : relire la migration, adapter la policy au sujet, rendre la factory crédible, ajuster textes et colonnes, puis `make:filament-resource ... --generate` si l'admin doit gérer l'entité (et corriger le formulaire, chapitre 14). `--force` régénère en écrasant (perte des adaptations).

## 26. Exercices

À faire sur une branche `exo/<prénom>` (**jamais mergée**), en local, dans l'ordre. Chaque exercice indique le résultat attendu.

1. **Explorer** (15 min) — `php artisan route:list --path=signalements`, ouvrir chaque route, lire `⚡index.blade.php` de haut en bas. *Attendu* : savoir dire ce que fait chaque méthode.
2. **Ajouter une colonne** (20 min) — migration `statut` (`en_attente` par défaut) sur `signalements`, badge dans la liste. *Attendu* : les anciens signalements affichent « en_attente ».
3. **Générer une entité** (15 min) — `make:feature Zone --fields="nom:string,description:text?"`. *Attendu* : menu « Zones », CRUD complet, 6 tests verts.
4. **Relier deux entités** (30 min) — `zone_id` sur `signalements`, `flux:select` des zones dans le formulaire, filtre par zone dans la liste, `with('zone')`. *Attendu* : pas de N+1 (une seule requête pour les zones).
5. **Statut réservé à l'admin** (40 min) — action Filament « Valider », test « un utilisateur ne peut pas se valider lui-même ». *Attendu* : le test passe, l'action n'apparaît que sur les signalements en attente.
6. **Limiter une action** (20 min) — 3 signalements maximum par minute et par utilisateur, test associé. *Attendu* : le 4ᵉ affiche un message en français.
7. **Carte** (30 min) — page « Carte » avec les signalements géolocalisés (chapitre 16), entrée de menu. *Attendu* : un titre contenant `<b>test</b>` s'affiche tel quel dans la bulle.
8. **Export CSV** (20 min) — bouton sur la liste. *Attendu* : fichier lisible dans Excel, accents corrects.
9. **Le cycle complet** (20 min) — sur une vraie branche `docs/<prénom>`, une petite modification, PR avec `Refs #`, CI verte, merge par Randy, déploiement, vérification en ligne.

Bloqué plus de 20 minutes ? Demander à Judicaël ou à Claude Code, avec l'erreur exacte.
