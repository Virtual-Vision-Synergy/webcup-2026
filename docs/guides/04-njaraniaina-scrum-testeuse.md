# Njaraniaina — Scrum master et testeuse

_Tu es la gardienne du **rythme** et de la **qualité**. Tu tiens le board, tu animes les points, tu protèges l'équipe du chaos, et surtout **rien n'est déclaré au jury sans que tu l'aies testé en ligne**. C'est toi qui construis le récap : sans toi, le jury ne trouve pas nos fonctionnalités._

À lire avec : tome 2 (playbook) en entier ; tome 1 chapitres 1, 2, 13 et 22 (pour comprendre ce que tu testes).

## 1. Tes responsabilités

| Tu es responsable de | Concrètement |
|---|---|
| **Le board** | Créer les issues après chaque triage, vérifier que chacun a une seule carte « En cours », repérer les cartes bloquées |
| **Le rythme** | Points de 5 min toutes les 2 h, rappel des créneaux de sommeil, rappel du gel |
| **La recette** | Tester chaque fonctionnalité déployée, en ligne, avec les comptes jury |
| **Le récap** | `docs/recap.md` : une ligne par fonctionnalité testée, puis fermer l'issue |
| **Les livrables** | Récap final, réalisation et montage de la vidéo |
| **La logistique** | Repas, eau, café, courant, connexion de secours |

## 2. Le board, au quotidien

**Après chaque triage** (Randy annonce « A4 : export CSV, P1, Tsoa »), en 3 minutes :

1. *New issue* → modèle **Fonctionnalité**.
2. Titre : `[A4] Export CSV des signalements`.
3. Coller le **texte exact** de l'annonce, remplir priorité, type, estimation, critères d'acceptation.
4. Label (`base` / `progressive` / `sécu`), responsable, ajouter au projet, **Priorité** P0/P1/P2.

**À chaque point**, regarder :

- une carte « En cours » depuis plus de 2 h → « Qu'est-ce qui te bloque ? » ;
- plus de 3 cartes « À tester sur Hodi » → demander un déploiement à Randy ;
- quelqu'un sans carte → lui proposer la P1 la plus haute (Randy valide).

## 3. Animer un point (5 minutes, montre en main)

Toutes les 2 h : 11 h, 13 h, 15 h, 17 h, 19 h, 21 h, 23 h, 1 h, 3 h, 5 h, 7 h.

1. Chacun en 30 secondes : **fait** / **en cours** / **bloqué**.
2. Lecture du board (1 min).
3. Rappel de l'heure qui vient : prochain déploiement, qui va dormir, temps avant le gel.
4. Fin. Les discussions longues se font après, à deux.

Tu as le droit (et le devoir) de **couper la parole** poliment : « On en parle après le point. »

## 4. La recette en ligne

Quand Randy dit « Déployé : #12, #14 », pour chaque issue :

1. **Navigation privée**, URL en ligne, Ctrl+F5.
2. Suivre les **critères d'acceptation** de l'issue avec le compte **jury user**.
3. Si la fonctionnalité a une partie admin : compte **jury admin**.
4. Avec un **deuxième compte user** (`testeur2@…`, créé par toi) : essayer d'ouvrir ou modifier les éléments du premier en changeant l'ID dans l'URL → doit être refusé (403 ou 404).
5. Formulaire : l'envoyer **vide** → messages en français ; mettre `<script>alert(1)</script>` dans un texte → affiché comme du texte.
6. **Téléphone** (le tien, ou F12 → mode responsive) : lisible, boutons cliquables.
7. Aucune erreur 500, aucun texte anglais, aucune page vide sans message.

**Réussi** → ligne dans `docs/recap.md` (voir §5) → fermer l'issue → la carte passe dans « Fait & dans le récap ».

**Échoué** → commentaire sur l'issue (étapes, ce qui est attendu, ce qui se passe, capture d'écran) → carte remise en « En cours » → prévenir le dev. Tu ne corriges pas toi-même.

## 5. Tenir le récap

`docs/recap.md` (modèle déjà prêt). Pour chaque fonctionnalité testée :

| # | Fonctionnalité | Où la voir | Compte | Comment tester |
|---|---|---|---|---|
| A4 | Export CSV des signalements | `/admin/signalements` | admin | Bouton « Exporter » en haut à droite |

Règles : des mots du **jury** (pas de jargon), un chemin **exact**, une action **concrète**. Les fonctionnalités **non traitées** vont dans la section « Choix assumés » avec une raison courte : cela montre au jury qu'on a **priorisé**, ce qui est évalué.

Tu envoies une modification du récap par une petite PR (`docs: récap A4`) ou tu la confies à Randy au moment du déploiement suivant.

## 6. La vidéo (dimanche)

- Outil : **OBS Studio** (installé et testé jeudi), 1080p, micro testé.
- Script (playbook §9) : problème en 20 s → parcours utilisateur 1 min 50 → admin 30 s → sécurité et fin 20 s. **3 minutes maximum.**
- Toujours sur l'URL **en ligne**, avec des données de démo propres.
- Randy parle, tu réalises : tu prépares les onglets, les comptes, l'ordre des écrans.
- Export MP4, mise en ligne (YouTube non répertorié ou Drive en lecture publique), **vérifier le lien en navigation privée**.

## 7. Ta journée

| Heure | Toi |
|---|---|
| 8 h 30 | Vocal, board vide prêt, café, prises |
| 9 h 10 | Animer la réunion de lancement (15 min) |
| 9 h 25 | Créer toutes les issues de base |
| 9 h 40 → 0 h 30 | Board, points de 2 h, recettes après chaque déploiement, récap |
| Dès le socle en ligne | Créer ton compte `testeur2`, vérifier les comptes jury |
| **0 h 30 → 4 h** | **Dormir** |
| 4 h → 6 h | Recettes en retard, rappel du gel à 6 h |
| 6 h → 8 h | **Recette complète** de tout le récap, dans l'ordre |
| 8 h | Récap finalisé |
| Dimanche | Récap final, vidéo, montage |

## 8. Préparation (avant samedi)

Voir tes issues `prépa` : animer la réunion de ce soir, installer le projet, modèles de livrables, OBS, logistique, animer la simulation et la rétro de jeudi.

**Rétro de jeudi (20 min)** : trois questions, une réponse par personne : « Qu'est-ce qui nous a fait perdre du temps ? », « Qu'est-ce qu'on garde ? », « Qu'est-ce qu'on change samedi ? ». Tu notes les décisions dans l'issue de la simulation.
