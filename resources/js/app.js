/**
 * Menu mobile Terra Nova (bottom sheet) : état partagé entre le bouton « Menu », la feuille et la page qui recule.
 * Alpine est fourni par Livewire : on déclare le store à son initialisation.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('menu', {
        ouvert: false,
        ouvrir() {
            this.ouvert = true;
        },
        fermer() {
            this.ouvert = false;
        },
    });
});

// Changement de page (wire:navigate) : la feuille se referme.
document.addEventListener('livewire:navigate', () => {
    window.Alpine?.store('menu')?.fermer();
});

/**
 * Taille du texte (A / A+ / A++) : wire:navigate peut réécrire les attributs de <html>,
 * on réapplique donc la préférence mémorisée à chaque affichage de page.
 */
const appliquerTailleTexte = () => {
    try {
        const taille = window.localStorage.getItem('tn.text-size');
        if (['md', 'lg', 'xl'].includes(taille)) {
            document.documentElement.dataset.textSize = taille;
        }
    } catch (e) {}
};

appliquerTailleTexte();
document.addEventListener('livewire:navigated', appliquerTailleTexte);

/**
 * Accessibilité des formulaires (F42).
 * - Chaque message d'erreur ([data-flux-error] ou [data-erreur]) reçoit un id et est relié à son champ
 *   par aria-describedby ; le champ passe en aria-invalid tant que l'erreur est affichée.
 * - Après l'envoi d'un formulaire en erreur, le nombre d'erreurs est annoncé et le focus va au premier champ invalide.
 */
let compteurErreurs = 0;
let formulaireSoumis = null;

const estVisible = (el) => el.getClientRects().length > 0;

const champsLies = (erreur) => {
    const conteneur = erreur.closest('[data-flux-field]') ?? erreur.closest('fieldset');

    if (! conteneur) {
        return [];
    }

    return [...conteneur.querySelectorAll('input:not([type=hidden]), select, textarea, [data-flux-control]')]
        .filter((champ) => ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(champ.tagName));
};

const zoneAnnonce = () => {
    let zone = document.getElementById('tn-annonce-erreurs');

    if (! zone) {
        zone = document.createElement('div');
        zone.id = 'tn-annonce-erreurs';
        zone.setAttribute('role', 'status');
        zone.setAttribute('aria-live', 'assertive');
        zone.setAttribute('aria-atomic', 'true');
        zone.style.cssText = 'position:absolute;width:1px;height:1px;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0';
        document.body.appendChild(zone);
    }

    return zone;
};

const lierErreurs = () => {
    document.querySelectorAll('[data-flux-error], [data-erreur]').forEach((erreur) => {
        if (! erreur.id) {
            erreur.id = `tn-erreur-${++compteurErreurs}`;
        }

        const active = erreur.textContent.trim() !== '' && ! erreur.classList.contains('hidden');

        champsLies(erreur).forEach((champ) => {
            const ids = (champ.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => id && id !== erreur.id);

            if (active) {
                ids.push(erreur.id);
                champ.setAttribute('aria-invalid', 'true');
            } else if (! champ.hasAttribute('data-invalid')) {
                champ.removeAttribute('aria-invalid');
            }

            ids.length ? champ.setAttribute('aria-describedby', ids.join(' ')) : champ.removeAttribute('aria-describedby');
        });
    });

    if (! formulaireSoumis || ! document.contains(formulaireSoumis)) {
        return;
    }

    const invalides = [...formulaireSoumis.querySelectorAll('[aria-invalid="true"]')].filter(estVisible);

    if (invalides.length) {
        const nombre = invalides.length;
        zoneAnnonce().textContent = nombre > 1
            ? `Le formulaire contient ${nombre} erreurs. Corrigez les champs signalés.`
            : 'Le formulaire contient une erreur. Corrigez le champ signalé.';
        invalides[0].focus();
        formulaireSoumis = null;
    }
};

let lienEnAttente = false;
const planifierLiaison = () => {
    if (lienEnAttente) {
        return;
    }

    lienEnAttente = true;
    requestAnimationFrame(() => {
        lienEnAttente = false;
        lierErreurs();
    });
};

document.addEventListener('submit', (event) => {
    formulaireSoumis = event.target;
    zoneAnnonce().textContent = '';
    setTimeout(() => {
        if (formulaireSoumis === event.target) {
            formulaireSoumis = null;
        }
    }, 8000);
}, true);

document.addEventListener('DOMContentLoaded', () => {
    // Formulaire classique renvoyé par le serveur avec des erreurs (connexion, inscription…).
    const erreurServeur = [...document.querySelectorAll('form [data-flux-error], form [data-erreur]')]
        .find((erreur) => erreur.textContent.trim() !== '');
    formulaireSoumis = erreurServeur?.closest('form') ?? null;
    lierErreurs();
    new MutationObserver(planifierLiaison).observe(document.body, { childList: true, subtree: true, characterData: true });
});
document.addEventListener('livewire:navigated', () => {
    formulaireSoumis = null;
    planifierLiaison();
});
