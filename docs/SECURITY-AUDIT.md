# Audit de sécurité OWASP (F69)

_Audit défensif du code de la plateforme, réalisé le 4 octobre 2026 par lecture du code et par des tests automatisés en local. Aucun scan d'un système externe n'a été lancé._

Méthode :
- `php artisan route:list` route par route : middleware `auth`, Policy, `authorize` dans chaque action.
- Recherche dans le code : `DB::raw`, `*Raw`, `orderBy($…)`, `{!! !!}`, `innerHTML`, `x-html`, `@csrf`, `#[Fillable]`, `->store(`, `Log::`.
- Lecture des Policies, des composants Livewire et des middlewares.

## Trous trouvés

| # | Catégorie | Fichier / route | Problème | Gravité | Correction |
|---|---|---|---|---|---|
| A1 | Contrôle d'accès | `app/Policies/ServicePolicy.php` (`/services/create`) | N'importe quel habitant pouvait créer une fiche de l'annuaire officiel, puis la modifier ou la supprimer (faux numéro, hameçonnage) | Haute | `create`, `update` et `delete` réservés aux agents et aux admins |
| A2 | Upload / exposition | `pages/signalements/form`, disque `public` | La photo d'un signalement (privée selon la Policy) était lisible sans connexion via `/storage/signalements/…` | Haute | Disque privé, contenu chiffré, route `auth` + Policy `view` |
| A3 | Upload | `app/Services/OptimiseurImage.php` | Si la conversion échouait, le fichier d'origine était gardé tel quel (métadonnées EXIF/GPS, contenu non réencodé) | Moyenne | En mode privé, le fichier est refusé s'il ne peut pas être réencodé |
| A4 | Données sensibles | `users.telephone` | Téléphone stocké en clair, et visible en clair dans le journal d'audit | Haute | Colonne chiffrée (`telephone_chiffre`) + empreinte HMAC (`telephone_hash`) pour la connexion et l'unicité ; masqué dans le journal et les réponses JSON |
| A5 | Mots de passe | `Filament/Resources/Users/Schemas/UserForm.php` | Le mot de passe saisi dans l'admin n'exigeait que 8 caractères | Moyenne | `Password::defaults()` (12 caractères et complexité en production) |
| A6 | Intégrité | `pages/settings/profile` | Le numéro n'était pas vérifié comme unique : un habitant pouvait reprendre le numéro d'un autre et bloquer sa connexion par téléphone | Moyenne | Règle d'unicité sur l'empreinte du numéro |
| A7 | Journalisation (F37) | `Listeners/RecordLockout`, `Services/LoginAttemptRecorder` | Le compte visé n'était cherché que par e-mail : les connexions par identifiant HAB ou par téléphone n'étaient ni rattachées ni signalées au titulaire | Basse | `User::trouverPourConnexion()` |
| A8 | Contrôle d'accès | `Filament/Resources/ActionLogs` | Aucune Policy : seul `canAccessPanel` protégeait le journal | Basse | `ActionLogPolicy` (admin en lecture seule) |
| A9 | Injection (durcissement) | `pages/demarches/index`, `pages/signalements/index`, `pages/agent/demandes` | Filtres `#[Url]` sans liste blanche ; recherche LIKE sans échappement de `%` et `_` (requêtes déjà paramétrées : pas d'injection SQL) | Basse | Liste blanche + échappement des jokers |
| A10 | Verrouillage Livewire | `pages/annonces/index` (`$miseAJourId`) | Propriété d'ID non verrouillée (l'action revérifiait déjà la Policy) | Info | `#[Locked]` |
| A11 | En-têtes | `app/Http/Middleware/SecurityHeaders.php` | Pas de Content-Security-Policy ; `X-Frame-Options` en `SAMEORIGIN` | Moyenne | CSP à nonce, `frame-ancestors 'none'`, `X-Frame-Options: DENY` |
| A12 | Cookies / configuration | `config/session.php`, `.env.example` | `SESSION_SECURE_COOKIE` sans valeur par défaut et absent de `.env.example` : cookie de session non `Secure` si la variable est oubliée | Moyenne | `Secure` par défaut en production + `.env.example` complété |
| A13 | XSS / CSP | `pages/demarches/recapitulatif` | Gestionnaire `onclick` en ligne (bloqué par la CSP) | Info | `x-on:click` Alpine |

## Points vérifiés sans défaut

| Catégorie | Constat |
|---|---|
| IDOR | Chaque `mount()` appelle `authorize` ; les propriétés de modèle sont `#[Locked]` ; les actions par ID font `findOrFail` puis `authorize` ; les listes sont limitées au propriétaire (`whereBelongsTo`, `auth()->user()->…`). |
| Routes | Toutes les routes métier sont dans le groupe `auth` ; les exceptions publiques sont justifiées (vérifié par `tests/Feature/Security/RoutesArchitectureTest.php`). |
| Injection SQL | Aucun `DB::raw`, `whereRaw` ni `DB::select` ; tous les `orderByRaw`, `selectRaw` et `havingRaw` contiennent du SQL fixe avec des paramètres `?`. Aucun `exec`, `shell_exec` ni `eval`. |
| XSS | Un seul `{!! !!}`, sur le QR code SVG généré par Fortify (propriété `#[Locked]`). Aucun `x-html` ni `innerHTML` sur une donnée ; la carte utilise `textContent`. |
| CSRF | Tous les formulaires POST ont `@csrf` ; aucune exclusion CSRF. Routes GET qui modifient l'état : changement de langue (liste blanche + limitation de débit) et ouverture d'une notification (Policy) ; impact faible, accepté. |
| Mass assignment | `role_id`, `user_id`, `statut`, `deactivated_at`, `mis_en_avant` et `notified_at` ne sont dans aucun `#[Fillable]` ; il n'y a ni `$guarded = []` ni `$request->all()`. |
| Uploads | Règles `image`, `mimes:jpg,jpeg,png,webp` et `max:2048` (type réel détecté par le contenu) ; noms régénérés (`Str::random(40)`) ; réencodage WebP. |
| Exposition | `password`, les secrets 2FA, `remember_token` et `code_activation` sont dans `#[Hidden]` ; aucun `Log::` avec une donnée personnelle ; messages d'erreur génériques. |
| Données publiques | `services.telephone` et `services.adresse` sont les coordonnées publiques des administrations : elles ne sont pas chiffrées. Les images d'actualités sont publiques par nature. |
| Cookies maison | `appareil` (F54) et `langue` sont chiffrés et `HttpOnly`. Le cookie `annonces fermées` (D18) est écrit en JavaScript par choix ; il ne contient que des clés d'annonces, filtrées côté serveur. |

## Non traités (signalés)

- `app/Console/Commands/MakeFeature.php` génère pour les champs `image` un `store(…, 'public')` brut. Les futures fonctionnalités générées devront passer par `OptimiseurImage` (et par le mode privé si l'image est privée).
- Le middleware `verified` est posé sur quelques routes alors que `Features::emailVerification()` n'est pas activé dans Fortify : c'est sans effet sur la sécurité, mais à clarifier.
- Le journal d'audit F47 est lisible par tous les agents. Les valeurs sensibles y sont masquées, mais la minimisation des autres champs (contenu des messages) reste une décision métier.
