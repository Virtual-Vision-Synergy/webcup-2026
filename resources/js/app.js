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
 * Accessibilité des formulaires : chaque message d'erreur Flux est relié à son champ
 * (aria-describedby + aria-invalid) et annoncé par les lecteurs d'écran (role="alert").
 */
function relierErreursAuxChamps() {
    document.querySelectorAll('[data-flux-field]').forEach((champ) => {
        const controle = champ.querySelector('input, textarea, select, [data-flux-control]');
        if (!controle) {
            return;
        }

        const erreurs = [...champ.querySelectorAll('[data-flux-error]')];
        const reference = controle.getAttribute('data-erreurs-ids');

        if (erreurs.length === 0) {
            if (reference) {
                const restants = (controle.getAttribute('aria-describedby') || '')
                    .split(' ')
                    .filter((id) => id && !reference.split(' ').includes(id));
                restants.length ? controle.setAttribute('aria-describedby', restants.join(' ')) : controle.removeAttribute('aria-describedby');
                controle.removeAttribute('aria-invalid');
                controle.removeAttribute('data-erreurs-ids');
            }
            return;
        }

        const ids = erreurs.map((erreur, index) => {
            erreur.id = erreur.id || `erreur-${controle.name || controle.id || 'champ'}-${index}`;
            erreur.setAttribute('role', 'alert');
            return erreur.id;
        });

        const existants = (controle.getAttribute('aria-describedby') || '')
            .split(' ')
            .filter((id) => id && !ids.includes(id) && !(reference || '').split(' ').includes(id));

        controle.setAttribute('aria-describedby', [...existants, ...ids].join(' '));
        controle.setAttribute('aria-invalid', 'true');
        controle.setAttribute('data-erreurs-ids', ids.join(' '));
    });
}

let relierPlanifie = false;
function planifierLiaison() {
    if (relierPlanifie) {
        return;
    }
    relierPlanifie = true;
    requestAnimationFrame(() => {
        relierPlanifie = false;
        relierErreursAuxChamps();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    relierErreursAuxChamps();
    new MutationObserver(planifierLiaison).observe(document.body, { childList: true, subtree: true });
});
document.addEventListener('livewire:navigated', relierErreursAuxChamps);
