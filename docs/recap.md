# Récapitulatif des fonctionnalités — Virtual Vision Synergie

Application : https://virtualvisionsy.madagascar.webcup.hodi.cloud
Comptes jury : voir le README (section « Accès jury »).

> Uniquement les fonctionnalités **testées en ligne** (colonne « Fait & dans le récap » du board).
> B = fonctionnalité de base, A = fonctionnalité annoncée pendant le concours (numéro de l'annonce).

## Fonctionnalités de base

| # | Fonctionnalité | Où la voir | Compte | Comment tester |
|---|---|---|---|---|
| B1 | | | | |

## Fonctionnalités annoncées pendant le concours

| # | Fonctionnalité | Où la voir | Compte | Comment tester |
|---|---|---|---|---|
| F21 | Accessibilité au lecteur d'écran : lien « Aller au contenu », zones repérables (en-tête, navigation, contenu, pied de page), erreurs de formulaire reliées à leur champ, navigation au clavier avec focus visible | Toutes les pages (accueil, connexion, espace citoyen, espace agent) | Aucun compte (accueil, /login), puis user | 1. Sur l'accueil, appuyer sur Tab : le lien « Aller au contenu » apparaît, Entrée amène au contenu principal. 2. Parcourir la page avec Tab : chaque élément actif a un contour visible. 3. Sur /login, envoyer le formulaire vide : le lecteur d'écran annonce l'erreur avec le champ concerné (`aria-describedby`, `aria-invalid`). |

## Sécurité

| Protection | Où / comment la vérifier |
|---|---|
| Mots de passe hachés, double authentification (2FA) disponible | Paramètres > Sécurité |
| Connexion bloquée après 5 essais ratés | 6 mauvais mots de passe sur /login |
| Rôles utilisateur / administrateur, admin réservé | /admin avec le compte user → 403 |
| Chaque donnée protégée par des règles d'accès (Policies) | Changer l'ID dans une URL d'édition → 403 |
| Validation serveur de tous les formulaires | Envoyer un formulaire vide ou invalide |
| Protection XSS / CSRF, en-têtes de sécurité HTTP | Texte `<script>` affiché comme du texte |

## Non traitées (choix assumés)

| Annonce | Raison |
|---|---|
| | |
