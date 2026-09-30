---
description: Trier une annonce de fonctionnalité de l'orga (priorité, effort, plan)
argument-hint: <texte de l'annonce>
---
Voici une nouvelle fonctionnalité annoncée par l'organisation du Webcup :

$ARGUMENTS

Fais le triage en 10 minutes maximum, sans écrire de code :
1. Reformule la fonctionnalité en 2 phrases, et ce que le jury vérifiera concrètement.
2. Regarde le code existant (modèles, pages, policies, `routes/features.php`) : qu'est-ce qui existe déjà et peut être réutilisé ?
3. Estime l'effort (S < 30 min, M < 1h30, L > 1h30) et le risque de casser l'existant.
4. Propose une priorité : P0 (base obligatoire), P1 (forte valeur jury), P2 (si on a le temps), ou « on ne le fait pas », avec la raison.
5. Si c'est P0 ou P1 : plan en étapes numérotées, fichiers touchés, et si `php artisan make:feature` peut servir (donne la commande exacte).
6. Donne le titre d'issue GitHub à créer et le label (base / progressive / sécu).
