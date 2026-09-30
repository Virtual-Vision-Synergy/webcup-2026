---
description: Implémenter une fonctionnalité en respectant la convention compétition
argument-hint: <description de la fonctionnalité>
---
Implémente cette fonctionnalité pour l'application Webcup :

$ARGUMENTS

Méthode obligatoire :
1. Reformule en 2-3 lignes et liste les fichiers que tu vas créer ou modifier. Si c'est un CRUD, commence par `php artisan make:feature` avec les bons champs, puis adapte.
2. Respecte CLAUDE.md et CONVENTION.md : routes dans le groupe `auth`, `$this->authorize()` dans chaque action, Policy, validation serveur, pas de champ sensible dans `#[Fillable]`, `{{ }}` en Blade, interface Flux en français et responsive.
3. Ajoute les données de démo (factory + seeder) si une nouvelle entité est créée.
4. Écris au minimum : un test du cas nominal, un test « un autre utilisateur → 403 », un test de validation.
5. Lance `vendor/bin/pint` puis `php artisan test` et corrige jusqu'à ce que tout soit vert.
6. Termine par : comment tester à la main (URL, compte, ce qu'on doit voir), et le message de commit proposé. Ne commite pas toi-même.
