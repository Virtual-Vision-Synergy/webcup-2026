# NOM DE L'APPLICATION

> Une phrase : ce que fait l'application, pour qui.

Réalisée en 24 h par **Virtual Vision Synergie** (Randy, Manakasina Judicaël, Tsoa, Njaraniaina) pour le **24h by Webcup 2026**.

## Accès jury

- Application : https://virtualvisionsy.madagascar.webcup.hodi.cloud
- Compte utilisateur : `…` / `…`
- Compte administrateur : `…` / `…` (espace admin : `/admin`)
- Vidéo de démonstration : …
- Récapitulatif des fonctionnalités : [docs/recap.md](docs/recap.md)

## Fonctionnalités

Voir [docs/recap.md](docs/recap.md) : chaque ligne indique où la voir et comment la tester.

## Architecture

- Laravel 13 (PHP 8.4), Livewire 4, Flux UI, Tailwind CSS 4, Filament 5 (administration), MariaDB.
- `app/Models` : entités · `app/Policies` : règles d'accès · `resources/views/pages` : pages (composants Livewire) · `app/Filament` : administration · `tests/Feature` : tests automatisés (Pest).
- Intégration continue GitHub Actions : formatage (Pint), analyse statique (PHPStan), tests.

## Sécurité

- Authentification Fortify : mots de passe hachés, 2FA, limitation des tentatives de connexion.
- Autorisations par Policies vérifiées dans chaque action ; identifiants verrouillés côté composant.
- Champs sensibles (propriétaire, rôle, statut) jamais remplis depuis un formulaire.
- Validation serveur de toutes les entrées ; affichage échappé (XSS) ; jetons CSRF.
- En-têtes HTTP de sécurité, HTTPS forcé, mode debug désactivé en production.
- Clés d'API uniquement côté serveur.

## Outils préparés avant la compétition

Conformément à la phase de préparation technique (J-7) : socle Laravel (authentification, administration des utilisateurs, en-têtes de sécurité, traductions), script de déploiement, intégration continue, et un générateur de code générique (`php artisan make:feature`). **Tout le code lié au sujet a été écrit pendant les 24 heures** (voir l'historique Git à partir du samedi 9 h).

## Installation locale

```
git clone https://github.com/Virtual-Vision-Synergy/webcup-2026.git
cd webcup-2026
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan storage:link
composer run dev
```
