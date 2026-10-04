import { etatReseau, pagesHorsLigne } from './hors-ligne.js';

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

    /**
     * Bandeaux de messages (D18 / F29) masqués automatiquement pendant la visite : permet de les rouvrir.
     */
    window.Alpine.store('tnBandeaux', {
        masques: [],
        signaler(cle, masque) {
            this.masques = this.masques.filter((c) => c !== cle);
            if (masque) {
                this.masques.push(cle);
            }
        },
        reafficher() {
            window.dispatchEvent(new CustomEvent('tn-bandeaux-reafficher'));
        },
        /**
         * F104 : une perturbation est annoncée → la page « Infos essentielles » (consignes, numéros d'urgence) est rechargée
         * une fois par visite pour que le service worker (F93) en garde la dernière version, lisible hors ligne.
         */
        garderInfosEssentielles(cle) {
            try {
                if (window.sessionStorage.getItem('tn.infos-essentielles') === String(cle)) {
                    return;
                }
                window.sessionStorage.setItem('tn.infos-essentielles', String(cle));
            } catch (e) {}

            window.fetch('/infos-essentielles', { headers: { Accept: 'text/html' }, credentials: 'same-origin' }).catch(() => {});
        },
    });

    window.Alpine.data('tnBandeau', bandeauAnnonce);

    /**
     * F104 : compte à rebours jusqu'au début estimé d'une perturbation (« dans 12 min 05 s »), mis à jour chaque seconde.
     */
    window.Alpine.data('tnCompteARebours', (cible) => ({
        texte: '',
        minuteur: null,
        init() {
            this.actualiser();
            this.minuteur = window.setInterval(() => this.actualiser(), 1000);
        },
        destroy() {
            window.clearInterval(this.minuteur);
        },
        actualiser() {
            const secondes = Math.max(0, Math.round((cible - Date.now()) / 1000));
            if (secondes === 0) {
                this.texte = '(maintenant)';
                window.clearInterval(this.minuteur);

                return;
            }

            const heures = Math.floor(secondes / 3600);
            const minutes = Math.floor((secondes % 3600) / 60);
            const reste = String(secondes % 60).padStart(2, '0');
            this.texte = '(dans ' + (heures > 0 ? heures + ' h ' : '') + minutes + ' min ' + reste + ' s)';
        },
    }));

    // F93 : état du réseau, brouillons hors ligne et page « Vous êtes hors ligne ».
    window.Alpine.data('tnEtatReseau', etatReseau);
    window.Alpine.data('tnPagesHorsLigne', pagesHorsLigne);
});

/**
 * Un bandeau de message (D18) ou d'alerte (F29) : fermeture, repli, dépliage et disparition automatique.
 *
 * - mode « fermable » : × mémorisé dans un cookie lu par le serveur (le message n'est plus rendu) ;
 * - mode « repliable » (alerte grave du quartier de l'habitant) : « Replier » mémorisé dans le navigateur ;
 * - la disparition automatique applique la même fermeture / le même repli, mais pour la visite seulement
 *   (sessionStorage) : un message non lu n'est jamais perdu définitivement.
 * Le minuteur ne tourne que si le bandeau est à l'écran, l'onglet visible, sans survol, focus ni dépliage.
 */
const CLE_MASQUES = 'tn.annonces.masquees';

const lireMasques = () => {
    try {
        return JSON.parse(window.sessionStorage.getItem(CLE_MASQUES) ?? '[]');
    } catch (e) {
        return [];
    }
};

const ecrireMasques = (cles) => {
    try {
        window.sessionStorage.setItem(CLE_MASQUES, JSON.stringify(cles.slice(-50)));
    } catch (e) {}
};

function bandeauAnnonce({ cle, mode, duree, cookie }) {
    return {
        ouvert: true,
        replie: false,
        deplie: false,
        duree: duree * 1000,
        restant: duree * 1000,
        survol: false,
        focus: false,
        aLEcran: false,
        minuteur: null,
        observateur: null,

        init() {
            if (mode === 'repliable') {
                try {
                    this.replie = window.localStorage.getItem(this.cleRepli()) === '1';
                } catch (e) {}
            }

            if (lireMasques().includes(cle)) {
                this.appliquerMasque();
                this.$store.tnBandeaux.signaler(cle, true);

                return;
            }

            // Déjà replié par l'habitant : pas de minuteur.
            if (this.replie) {
                return;
            }

            this.observateur = new IntersectionObserver(([entree]) => {
                this.aLEcran = entree.isIntersecting;
            });
            this.observateur.observe(this.$el);
            this.minuteur = window.setInterval(() => this.tic(), 100);
        },

        destroy() {
            this.arreter();
        },

        get enPause() {
            return this.survol || this.focus || this.deplie || ! this.aLEcran || document.visibilityState !== 'visible';
        },

        get progression() {
            return Math.max(0, this.restant / this.duree);
        },

        tic() {
            if (this.enPause) {
                return;
            }

            this.restant -= 100;

            if (this.restant <= 0) {
                this.arreter();
                this.fermer(false);
            }
        },

        arreter() {
            window.clearInterval(this.minuteur);
            this.minuteur = null;
            this.observateur?.disconnect();
            this.observateur = null;
        },

        quitterFocus(evenement) {
            this.focus = this.$el.contains(evenement.relatedTarget);
        },

        cleRepli() {
            return 'tn.annonce.' + cle + '.replie';
        },

        appliquerMasque() {
            if (mode === 'repliable') {
                this.replie = true;
            } else {
                this.ouvert = false;
            }
        },

        /**
         * Fermeture (×) ou repli : memoriser = true → définitif (cookie / localStorage) ; false → pour la visite.
         */
        fermer(memoriser = true) {
            this.arreter();
            this.appliquerMasque();

            if (! memoriser) {
                ecrireMasques([...lireMasques().filter((c) => c !== cle), cle]);
                this.$store.tnBandeaux.signaler(cle, true);

                return;
            }

            if (mode === 'repliable') {
                try {
                    window.localStorage.setItem(this.cleRepli(), '1');
                } catch (e) {}

                return;
            }

            try {
                const brut = document.cookie.split('; ').find((c) => c.startsWith(cookie + '='));
                const cles = brut ? decodeURIComponent(brut.slice(cookie.length + 1)).split(',').filter((c) => c && c !== cle) : [];
                cles.push(cle);
                document.cookie = cookie + '=' + encodeURIComponent(cles.slice(-50).join(',')) + '; path=/; max-age=2592000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            } catch (e) {}
        },

        basculerRepli() {
            if (this.replie) {
                this.rouvrir();
            } else {
                this.fermer(true);
            }
        },

        /** Rouvre un bandeau masqué ou replié (sans relancer le minuteur). */
        rouvrir() {
            this.ouvert = true;
            this.replie = false;
            ecrireMasques(lireMasques().filter((c) => c !== cle));
            this.$store.tnBandeaux.signaler(cle, false);

            if (mode === 'repliable') {
                try {
                    window.localStorage.setItem(this.cleRepli(), '0');
                } catch (e) {}
            }
        },

        reafficher() {
            if (lireMasques().includes(cle)) {
                this.rouvrir();
            }
        },
    };
}

// Changement de page (wire:navigate) : la feuille se referme.
document.addEventListener('livewire:navigate', () => {
    window.Alpine?.store('menu')?.fermer();
});

// Changement de page : les bandeaux de la nouvelle page se signalent à nouveau.
document.addEventListener('livewire:navigating', () => {
    const bandeaux = window.Alpine?.store('tnBandeaux');
    if (bandeaux) {
        bandeaux.masques = [];
    }
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

/**
 * F96 : version légère — la carte (et Leaflet) n'est téléchargée qu'au clic sur « Afficher la carte ».
 * La configuration passe de data-carte-differee à data-carte, que resources/js/carte.js surveille.
 */
document.addEventListener('click', (evenement) => {
    const bouton = evenement.target instanceof Element ? evenement.target.closest('[data-carte-afficher]') : null;
    const carte = bouton?.closest('[data-carte-differee]');

    if (! carte) {
        return;
    }

    carte.dataset.carte = carte.dataset.carteDifferee;
    delete carte.dataset.carteDifferee;
    carte.querySelector('[data-carte-zone]')?.removeAttribute('hidden');
    bouton.remove();

    import('./carte.js').catch(() => {
        const message = carte.querySelector('[data-carte-message]');
        if (message) {
            message.textContent = 'Carte momentanément indisponible. Les adresses de la page restent valables.';
        }
    });
});

/**
 * F45 : carte ajoutée par une mise à jour Livewire (bouton « Carte » d'une liste). Le navigateur n'exécute pas
 * la balise <script> insérée avec le composant <x-carte> : on charge donc ici resources/js/carte.js,
 * qui repère ensuite la carte tout seul. Sans effet si le script est déjà chargé.
 */
const chargerCarteAjoutee = () => {
    window.Livewire.hook('morphed', ({ el }) => {
        if (el.querySelector?.('[data-carte]')) {
            import('./carte.js').catch(() => {});
        }
    });
};

if (window.Livewire) {
    chargerCarteAjoutee();
} else {
    document.addEventListener('livewire:init', chargerCarteAjoutee, { once: true });
}
