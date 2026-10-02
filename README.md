# Webcup 2026 — Virtual Vision Synergie

Application web Laravel 13 / Livewire 4 / Filament 5, construite pendant le 24h by Webcup 2026.
Règles de l'équipe : `CONVENTION.md`. État du projet et du serveur : `docs/PASSATION.md`.

## Base préparée avant la compétition

Code générique, sans lien avec le sujet, écrit avant l'ouverture officielle :

- **Authentification** : inscription, connexion, 2FA, confirmation de mot de passe, paramètres du profil (starter kit Livewire + Fortify), rôle `admin` / utilisateur (`User::isAdmin()`).
- **Administration** : panneau Filament sur `/admin`, réservé aux admins ; gestion des utilisateurs, consultation du journal d'actions, widget de statistiques (`UsersStatsOverview`).
- **Générateur** `php artisan make:feature` : modèle, migration, factory, policy, pages Livewire, routes, navigation, seeder, tests (options `--belongs-to`, `--statut`, `--public`, `--filament`).
- **Kit e-mail** : `app/Mail/TestMail.php`, commande `app:test-mail`, notifications `Avis` et cloche de notifications.
- **Kit carte** : composant `<x-carte>` (Leaflet) et trait `HasCoordinates` (`geolocalises`, `proches`).
- **Service IA** `app/Services/Ai.php` : client OpenRouter côté serveur, modèle de secours, cache.
- **Service API de l'organisation** `app/Services/OrgaApi.php`.
- **Journal d'actions** : modèle `ActionLog`.
- **Export CSV** : trait `ExportsCsv`. **Limitation par utilisateur** : trait `ThrottlesPerUser`.
- **Sécurité** : en-têtes HTTP (`SecurityHeaders`).
- **Outils** : commande `app:promouvoir-admin`, tests Pest, Pint, PHPStan, CI GitHub (SQLite et MariaDB), déploiement Hodifly.

Aucune ligne liée au sujet n'a été écrite avant l'ouverture officielle.