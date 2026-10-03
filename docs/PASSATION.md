# Passation : état du projet et du serveur

_Mis à jour le vendredi 2 octobre 2026. À lire en premier (humains et Claude). Les règles complètes sont dans `CONVENTION.md` et `docs/guides/`._

## Calendrier

- Compétition : **samedi 3/10 9 h → dimanche 4/10 9 h**. Tout le code est **figé à 9 h** (tag `v1.0-rendu` à 8 h 45, déploiement automatique désactivé à 8 h 45).
- Livrables : dimanche 11 h → lundi 11 h (URL, comptes jury, récap `docs/recap.md`, vidéo de 3 min).
- Rôles : Randy (chef, intégration, déploiement), Manakasina Judicaël (adjoint, sécurité, Filament, chef par intérim 2 h → 5 h 30), Tsoa / Voa-hary (exécuteur, interface), Njaraniaina (scrum master, recette, récap, vidéo).

## Stack

Laravel 13 / PHP 8.4, Livewire 4 (composants ), Flux (gratuit), Tailwind 4, Filament 5 (`/admin`), Fortify, Pest / Pint / PHPStan. Local : SQLite (Herd). Production : MariaDB 10.11, sessions et cache en base, `QUEUE_CONNECTION=sync`, e-mails par `sendmail`. Pas de Redis ni de worker ; temps réel par `wire:poll`.

## Ce qui est prêt dans le dépôt

- Générateur `php artisan make:feature` (tome 1, chapitre 25).
- Kit e-mail (`app:test-mail`, notifications, cloche), kit carte (Leaflet, `<x-carte>`, trait `HasCoordinates`).
- Boîte à outils (PR #34) : service IA OpenRouter, service API de l'orga, journal d'actions, widget de stats, export CSV.
- Page d'accueil (PR #33) : textes et couleur d'accent à adapter au sujet le jour J.
- CI GitHub (SQLite + MariaDB). Backlog sur le board GitHub (label `prépa`).

## Déploiement (Hodifly)

- **Chaque merge sur `main` est déployé automatiquement** en 1 à 2 minutes. Donc : **jamais de merge sans CI verte**.
- Échec de build ou de migration : l'ancienne version reste en ligne, e-mail à Randy, détail dans cPanel → Hodifly → Journaux.
- Retour arrière : Hodifly → **Restaurer** (testé). Restaure le code, pas la base.
- Variables de production : Hodifly → Modifier → Variables, puis **Déployer**. Jamais dans le `.env` du serveur (réécrit à chaque déploiement).
- Sur le serveur (Terminal cPanel) : `~/app` = version en ligne. `cd ~/app && php84 artisan …` ; journal : `tail -n 60 ~/app/storage/logs/laravel.log`.
- Sauvegarde de la base toutes les 30 min dans `~/backups` (cron). Avant de merger une migration risquée : `bash ~/app/scripts/sauvegarde-base.sh avant-prNN`.

Site : https://virtualvisionsy.madagascar.webcup.hodi.cloud

## Reste à faire avant samedi

- Chacun : projet installé (`INSTALLATION.md`), une petite PR mergée, Claude Code connecté avec **son propre compte**.
- Randy : comptes jury en production (user par `/register`, admin par `tinker`), identifiants notés hors Git ; retirer l'exemple Signalement ; test à vide (petite PR → déploiement → Restaurer).
- Judicaël : clé OpenRouter dans les variables Hodifly, options du générateur. Tsoa : Lighthouse mobile. Njaraniaina : modèles récap/vidéo, OBS, logistique.
- Questions Discord en attente : code générique préparé autorisé ? fuseau horaire ?

## Utiliser Claude efficacement

- Une conversation par sujet, `/clear` entre deux tâches ; donner le texte exact de la carte et le numéro d'issue.
- Erreur : coller les 20 dernières lignes du journal, pas tout.
- Les grosses fonctionnalités passent par les sessions cloud de Randy (une session = une branche `feat/…` = une PR).

## Règles qu'on ne casse jamais

Jamais de mot de passe ou de clé dans le code, le chat ou Git · jamais `migrate:fresh`, `db:seed` ni `key:generate` en production · `APP_DEBUG=false` en ligne · ne pas toucher au bloc `AddHandler … ea-php84` de `public/.htaccess` · `role`, `user_id`, `statut` jamais dans `#[Fillable]` · `$this->authorize()` dans chaque méthode publique · rien n'est déclaré au jury sans recette en ligne par Njaraniaina.
