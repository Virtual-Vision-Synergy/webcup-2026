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
