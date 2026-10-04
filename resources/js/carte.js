/**
 * Chargeur du kit carte (F61 : rapide sur les appareils peu puissants).
 *
 * Inclus uniquement par le composant Blade <x-carte> (entrée Vite séparée). Ce petit fichier
 * ne contient pas Leaflet : il attend qu'une carte approche de l'écran pour télécharger et
 * exécuter resources/js/carte-leaflet.js (Leaflet + tuiles). Une carte en bas de page,
 * dans un onglet ou un bloc replié ne coûte donc rien tant qu'on ne va pas la voir.
 */
let chargement = null;
const chargerLeaflet = () => (chargement ??= import('./carte-leaflet.js'));

const observees = new WeakSet();

const observateur = 'IntersectionObserver' in window
    ? new IntersectionObserver((entrees) => {
        entrees.filter((entree) => entree.isIntersecting).forEach((entree) => {
            observateur.unobserve(entree.target);
            chargerLeaflet().then(({ initialiser }) => initialiser(entree.target));
        });
    }, { rootMargin: '200px 0px' })
    : null;

function surveiller() {
    if (chargement) {
        chargement.then(({ nettoyer }) => nettoyer());
    }

    document.querySelectorAll('[data-carte]').forEach((el) => {
        if (observees.has(el)) {
            return;
        }

        observees.add(el);

        if (observateur) {
            observateur.observe(el);
        } else {
            chargerLeaflet().then(({ initialiser }) => initialiser(el));
        }
    });
}

// Pages chargées normalement, navigation wire:navigate et cartes ajoutées par Livewire :
// une seule vérification par image, quel que soit le nombre de changements du DOM.
let planifie = false;
const planifier = () => {
    if (!planifie) {
        planifie = true;
        requestAnimationFrame(() => {
            planifie = false;
            surveiller();
        });
    }
};

surveiller();
new MutationObserver(planifier).observe(document.documentElement, { childList: true, subtree: true });
