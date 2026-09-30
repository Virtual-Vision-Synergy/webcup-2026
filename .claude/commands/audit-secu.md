---
description: Audit de sécurité de l'application avant livraison au jury
argument-hint: [zone à auditer, optionnel]
---
Fais un audit de sécurité de l'application (zone ciblée : $ARGUMENTS — si vide, toute l'application).

Vérifie point par point, en citant fichier et ligne pour chaque problème :
1. Routes : toutes les routes métier sont-elles derrière `auth` ? Liste les routes publiques et justifie-les (`php artisan route:list`).
2. Contrôle d'accès : chaque action Livewire (mount, save, delete, toute méthode publique) appelle-t-elle `authorize` ? Un utilisateur peut-il agir sur la ressource d'un autre en changeant un ID ?
3. Assignation de masse : `role`, `user_id`, statuts sensibles absents de `#[Fillable]` ? Propriétés Livewire sensibles marquées `#[Locked]` ?
4. Validation : toutes les entrées validées côté serveur ? Uploads limités (type, taille) ?
5. XSS : aucun `{!! !!}` sur une donnée utilisateur.
6. Fuite de données : mots de passe, secrets 2FA, e-mails d'autres utilisateurs, `APP_DEBUG` ; réponses qui exposent trop de colonnes.
7. Brute force : limitation des tentatives sur la connexion et sur les actions sensibles.
8. Admin Filament : accès réservé aux admins, pas d'auto-rétrogradation, ressources qui n'exposent pas de secrets.
9. Tests : chaque fonctionnalité a-t-elle son test « autre utilisateur → 403 » ?

Classe les problèmes par gravité (critique / important / mineur), propose la correction de chacun, puis applique les corrections critiques et lance `vendor/bin/pint` et `php artisan test`.
