# Données de test du jury

Jeu de données rechargé par **/admin → Réinitialiser les données** (page `admin/donnees-de-test`), ou en local par `php artisan migrate:fresh --seed`.

- **Effacé puis recréé** : toutes les tables sauf les comptes, rôles, quartiers, sessions, cache et files d'attente.
- **Conservés** : tous les comptes. Les comptes de démo ci-dessous sont remis dans leur état d'origine (nom, rôle, mot de passe, compte réactivé…) ; le compte de l'admin qui lance l'opération n'est pas touché et reste connecté.
- Une **sauvegarde** de la base est faite juste avant (visible dans /admin → Sauvegardes). Si elle échoue, rien n'est effacé. Toute erreur pendant l'opération annule tout (transaction).
- Limité à 3 réinitialisations par admin toutes les 10 minutes, tracé dans le journal d'actions (« Données de test réinitialisées »).

> **Production** : les comptes `@example.com` (mot de passe connu) ne sont **jamais** créés en ligne. La réinitialisation recrée les données publiques (services, actualités, annonces, signalements, projets, idées, partenaires, transports, avis…), mais pas les scénarios rattachés à `user@example.com`. Les comptes jury créés à la main sont conservés.
> Les factories utilisent Faker (dépendance de développement) : si le serveur l'a installé sans `--dev`, la page affiche « Réinitialisation impossible » et rien n'est modifié.

## Comptes (local / préproduction, mot de passe : `password`)

| Compte | Rôle | Pour tester |
|---|---|---|
| `admin@example.com` | Administrateur | /admin (Filament), espace agent, réinitialisation |
| `agent@example.com` | Agent, tous les services | Espace agent complet, agenda du jour, réponses aux remontées |
| `jury.agent@example.com` | Agent, État civil seulement | Refus d'accès aux dossiers des autres services (F70) |
| `agent.etat-civil@example.com` | Agent, État civil | Modération des avis État civil (dont un avis masqué) |
| `agent.social@example.com` | Agent, Action sociale (CCAS) | Dossiers confidentiels de l'Action sociale |
| `user@example.com` | Citoyen, prise en main terminée | Compte principal : presque tous les parcours citoyens (voir plus bas) |
| `voisin@example.com` | Citoyen | Ses propres demandes : ouvrir l'URL d'une demande de `user@` → 403 |
| `sans.demande@example.com` | Citoyen | « Mes demandes » vide (état vide) |
| `nouveau@example.com` | Citoyen tout neuf | Redirigé vers /bienvenue (prise en main jamais vue) |
| `parcours@example.com` | Citoyen à mi-parcours | Prise en main 1/3 (profil complet) |
| `passe@example.com` | Citoyen | A passé la prise en main |
| `sud@example.com` / `nord@example.com` | Citoyens quartier Sud / Nord | Alerte ciblée « Montée des eaux » : visible au Sud, pas au Nord |
| `desactive@example.com` | Citoyen désactivé | Connexion refusée |
| `hanitra.rakoto@example.com`, `tojo.andria@…`, `awa.diallo@…`, `lucas.moreau@…`, `fanja.razaf@…`, `ines.benali@…`, `mamy.rasolofo@…`, `chloe.martin@…`, `kevin.ramanantsoa@…` | Citoyens | Recherche de citoyens côté agent (noms variés) |

## Parcours à tester

### Citoyen — `user@example.com`

| Où | Ce qu'on doit voir |
|---|---|
| `/dashboard` | Espace personnel rempli, cloche avec notifications lues et non lues |
| `/demarches` | 4 démarches + 2 urgences médicales ; fils d'échanges : « Réponse envoyée », « En attente de réponse », sans réponse |
| `/mes-demandes` | 4 signalements aux états variés, avec étapes datées |
| `/signalements` | Dont un lampadaire cassé tout juste signalé (« Rue des Lumières ») |
| `/rendez-vous` | 2 à venir, 1 annulé, 1 passé ; un rendez-vous État civil dans ~24 h dont le rappel part ~10 min après la réinitialisation (planificateur) |
| `/mes-remontees` | 3 remontées : Reçue, Prise en compte, Répondue |
| `/idees` | 6 idées à des états variés ; la première est proposée par `user@` |
| `/mes-avis/services` | Démarche Urbanisme traitée sans avis → « Donnez votre avis » |
| `/profil/appareils` | 2 appareils connus et une alerte « nouvel appareil » non lue |
| `/services` | Annuaire : État civil en incident, Médiathèque en maintenance/indisponible, Urbanisme perturbé, hôpitaux |
| `/transports`, `/projets`, `/partenaires`, `/urgences`, `/orientation` | Lignes de transport, 5 projets, 4 partenaires, établissements de santé, assistant d'orientation |
| Bandeau en haut | Annonce en cours « Coupure d'eau à Ambohijanahary » ; une alerte « Danger » programmée ~5 min après la réinitialisation |

### Agent — `agent@example.com` (et `jury.agent@example.com` pour les refus)

| Où | Ce qu'on doit voir |
|---|---|
| `/agent/tableau-de-bord` | Compteur « À traiter en priorité » (urgences médicales en tête) |
| `/agent/rendez-vous` | Agenda du jour avec plusieurs créneaux réservés |
| `/agent/signalements-similaires` | Groupes de signalements décrivant le même problème |
| `/agent/securite/connexions` | Échecs isolés, une IP qui essaie plusieurs comptes, un blocage sur `user@` |
| `/agent/journal` | ~30 opérations sur 5 jours, filtrables par type, auteur, élément, dates |
| `/agent/donnees/remontees` | Les 3 remontées de `user@` |
| `/agent/avis` | 10 avis sur 3 services, 2 réponses, 1 avis masqué |
| `/agent/services/{service}/disponibilite` | Interruptions en cours et historique |
| `/agent/idees`, `/agent/partenaires`, `/agent/annonces`, `/agent/citoyens` | Gestion des idées, partenaires, messages généraux (en cours / programmé / expiré), citoyens |

### Admin — `admin@example.com`

| Où | Ce qu'on doit voir |
|---|---|
| Menu citoyen → **Espace admin** | Lien vers /admin (visible uniquement pour un admin) |
| /admin → **Espace citoyen** | Retour vers le tableau de bord du site |
| `/admin/users` | Tous les comptes, rôles modifiables, compte désactivé |
| `/admin/robots-bloques` | Vague de robots sur l'inscription, envois directs, rafales |
| `/admin/action-logs`, `/admin/securite/*` | Journal d'actions, événements de sécurité, comptes suspects |
| `/admin/sauvegardes` | Une sauvegarde nommée « interface » créée par la réinitialisation |
| `/admin/donnees-de-test` | Le bouton **Réinitialiser les données** |

### Sécurité (à vérifier après une réinitialisation)

- Connecté en `voisin@example.com`, ouvrir `/mes-demandes/{id}` d'une demande de `user@` → **403**.
- Connecté en citoyen, ouvrir `/admin` ou `/admin/donnees-de-test` → **403** ; `/agent` → **403**.
- `jury.agent@example.com` ne voit pas les données confidentielles des dossiers de l'Action sociale.
- `desactive@example.com` / `password` → connexion refusée.
