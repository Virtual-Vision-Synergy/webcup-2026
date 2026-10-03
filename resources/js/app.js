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

    /**
     * Aides contextuelles (F35) : préférence gardée dans le navigateur, sans donnée serveur.
     * `actives` = interrupteur global ; `vues` = aides déjà masquées une à une.
     */
    const lire = (cle, defaut) => {
        try {
            const valeur = window.localStorage.getItem(cle);

            return valeur === null ? defaut : JSON.parse(valeur);
        } catch (e) {
            return defaut;
        }
    };
    const ecrire = (cle, valeur) => {
        try {
            window.localStorage.setItem(cle, JSON.stringify(valeur));
        } catch (e) {}
    };

    window.Alpine.store('aides', {
        actives: lire('tn.aides.actives', true) !== false,
        vues: Array.isArray(lire('tn.aides.vues', [])) ? lire('tn.aides.vues', []) : [],
        visible(id) {
            return this.actives && ! this.vues.includes(id);
        },
        masquer(id) {
            this.vues = [...this.vues, id];
            ecrire('tn.aides.vues', this.vues);
        },
        basculer(actif) {
            this.actives = actif;
            if (actif) {
                this.vues = [];
                ecrire('tn.aides.vues', this.vues);
            }
            ecrire('tn.aides.actives', actif);
        },
    });
});

// Changement de page (wire:navigate) : la feuille se referme.
document.addEventListener('livewire:navigate', () => {
    window.Alpine?.store('menu')?.fermer();
});
