# Sécurité de la plateforme (F69)

Rapport détaillé de l'audit : `docs/SECURITY-AUDIT.md`. Tous les tests de sécurité sont dans `tests/Feature/Security/` (`php artisan test --compact tests/Feature/Security`).

## Ce qui a été audité

Audit défensif OWASP du code, mené en lisant chaque route, chaque composant Livewire, chaque Policy et chaque modèle. Six familles de failles ont été passées en revue : contrôle d'accès (IDOR), injection, XSS, CSRF, mass assignment et upload de fichiers. L'exposition de données (réponses, journaux, erreurs) a aussi été vérifiée. 13 points ont été trouvés et corrigés.

## Trous trouvés et corrigés

| # | Problème | Correction | Test |
|---|---|---|---|
| A1 | Un habitant pouvait créer ou modifier une fiche de l'annuaire des services | Création et modification réservées aux agents et aux admins, suppression aux admins | `AuditCorrectionsTest` « A1 », `ServiceTest` |
| A2 | Photo d'un signalement lisible sans connexion via `/storage` | Disque privé, contenu chiffré, route `auth` + Policy | `ChiffrementTest` « A2 » |
| A3 | Une image impossible à réencoder était gardée telle quelle (EXIF/GPS) | Refusée et journalisée | `ChiffrementTest` « A3 » |
| A4 | Téléphone en clair en base et dans le journal | Chiffré (`telephone_chiffre`), empreinte HMAC (`telephone_hash`), « [masqué] » dans le journal, absent du JSON | `ChiffrementTest` « A4 » |
| A5 | Mot de passe de 8 caractères accepté dans l'admin | `Password::default()` (12 caractères et complexité en production) | `AuditCorrectionsTest` « A5 » |
| A6 | Numéro de téléphone réutilisable par un autre compte | Règle `TelephoneUnique` | `ChiffrementTest` « A6 » |
| A7 | Blocage F37 non rattaché au compte lors d'une connexion par téléphone ou identifiant | `User::trouverPourConnexion()` | `AuditCorrectionsTest` « A7 » |
| A8 | Journal des actions sans Policy | `ActionLogPolicy` (admin, lecture seule) | `AuditCorrectionsTest` « A8 » |
| A9 | Filtres d'URL sans liste blanche, jokers `%` `_` non échappés | Listes blanches + `addcslashes` | `AuditCorrectionsTest` « A9 » |
| A10 | Identifiant Livewire modifiable par le navigateur | `#[Locked]` | `AuditCorrectionsTest` « A10 » |
| A11 | Pas de Content-Security-Policy, `X-Frame-Options: SAMEORIGIN` | CSP à nonce, `frame-ancestors 'none'`, `DENY` | `EnTetesTest` |
| A12 | Cookie de session non `Secure` si la variable est oubliée | `Secure` par défaut en production | `EnTetesTest`, `security:check` |
| A13 | `onclick` en ligne | Remplacé par Alpine | `AuditCorrectionsTest` « A13 » |

Contrôles transverses couverts par des tests :
- toute route est `auth` ou figure dans une liste blanche justifiée (`RoutesArchitectureTest`) ;
- un autre habitant reçoit 403 sur chaque ressource possédée (`ProprieteRessourcesTest`) ;
- les champs réservés envoyés en masse sont ignorés ;
- `<script>` est affiché comme du texte ;
- un formulaire sans jeton CSRF est refusé avec 419.

## Protection perceptible

Chaque refus est enregistré dans le journal d'audit F47, avec le type, le compte ou « invité », une IP approximative (`a.b.c.x`) et la route. Le contenu de la requête n'y figure jamais. Sont comptés :
- les accès interdits (403) ;
- les pages expirées (419) ;
- les liens signés altérés ;
- les fichiers refusés ;
- les limites de débit dépassées ;
- les blocages de connexion (F37) ;
- les paramètres contenant `../` ou `<script`.

Seuils (`config/security.php`) :
- un compte connecté est bloqué au-delà de 10 événements en 5 minutes ;
- une IP au-delà de 30 événements (seuil plus large, car une IP peut être partagée) ;
- le blocage dure 15 minutes et renvoie une page 429 en français ;
- les administrateurs reçoivent une notification, au plus une par compte ou IP et par heure ;
- un administrateur n'est jamais bloqué ;
- un 403 isolé, une page expirée ou une faute de frappe ne bloquent personne.

Côté admin :
- `/admin/alertes-securite` liste les événements, avec un badge rouge indiquant le nombre d'alertes des dernières 24 h ;
- `/admin/securite` affiche cette page et le résultat de `security:check` ;
- ces deux pages sont réservées aux administrateurs : un agent ou un citoyen reçoit 403. Les agents ne voient pas non plus ces événements dans `/agent/journal`.

## Données sensibles et APP_KEY

- Le téléphone et les photos de signalement sont chiffrés avec `APP_KEY` (AES-256).
- **Ne jamais changer `APP_KEY` en production sans recopier l'ancienne clé dans `APP_PREVIOUS_KEYS`**, sinon ces données deviennent illisibles.
- L'empreinte `telephone_hash` dépend aussi de la clé : après une rotation, il faut la recalculer, sinon la connexion par téléphone échoue.
- Migration des données existantes :
  - les téléphones sont chiffrés automatiquement au déploiement (migration `chiffrer_telephones_users`, par lots, réversible) ;
  - les anciennes photos se migrent une fois en ligne avec `php84 artisan security:migrer-photos`.
- Impact visible : la recherche d'un habitant par téléphone (F34) exige désormais le numéro complet.

## En-têtes et cookies

- **Content-Security-Policy** : `default-src 'self'`, scripts avec nonce, `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`.
  - `'unsafe-eval'` est conservé car Alpine n'est pas compilé en version CSP.
  - `/admin` (Filament) autorise `'unsafe-inline'` pour ses propres scripts.
  - En cas de problème en ligne : variable `SECURITY_CSP_REPORT_ONLY=true` dans Hodifly.
- **Autres en-têtes** : `Strict-Transport-Security` (HTTPS uniquement), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` restrictive.
- **Cookies** : session `HttpOnly`, `SameSite=Lax`, `Secure` en production. Le cookie d'appareil F54 suit la même règle `Secure`.

## Comment le vérifier soi-même

1. **En-têtes** : ouvrir le site, puis F12 → onglet Réseau → première requête → en-têtes de réponse. On peut aussi utiliser un vérificateur d'en-têtes en ligne (securityheaders.com) sur l'URL publique.
2. **Configuration** : sur le serveur, `cd ~/app && php84 artisan security:check` ; tout doit être OK.
3. **IDOR** : connecté en habitant, ouvrir un rendez-vous, puis changer l'identifiant dans l'URL → 403.
4. **XSS** : saisir `<script>alert(1)</script>` dans un signalement → affiché comme du texte.
5. **Chiffrement** : dans la base, `SELECT telephone, telephone_chiffre FROM users` montre une chaîne chiffrée, alors que la page « Mon espace » affiche le numéro.
6. **Blocage** : connecté en habitant, ouvrir une douzaine de fois `/agent/tableau-de-bord` (403) → page « Activité inhabituelle détectée » (429), et une alerte dans la cloche de l'admin et dans `/admin/alertes-securite`.
7. **Tests** : `php artisan test --compact tests/Feature/Security`.
