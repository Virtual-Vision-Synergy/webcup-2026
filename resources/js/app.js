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
