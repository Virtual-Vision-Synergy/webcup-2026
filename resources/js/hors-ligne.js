/**
 * F93 : continuer à utiliser Terra Nova pendant une coupure de réseau.
 *
 * - enregistre le service worker (public/sw.js) qui garde les pages essentielles consultables hors ligne ;
 * - bandeau d'état du réseau (hors ligne / lent / rétabli), annoncé au lecteur d'écran ;
 * - formulaires marqués data-brouillon : rempli ou envoyé hors ligne → brouillon gardé dans ce navigateur,
 *   puis « Connexion rétablie — envoyer ? ». Le jeton d'envoi unique du formulaire (F82) empêche tout doublon ;
 * - pages et brouillons personnels effacés quand l'utilisateur change sur cet appareil (déconnexion).
 */
const CACHE_PAGES = 'tn-pages-';
const PREFIXE_BROUILLON = 'tn.brouillon.';
const CLE_SESSION = 'tn.session';

const lire = (cle) => {
    try {
        return JSON.parse(window.localStorage.getItem(cle) ?? 'null');
    } catch (e) {
        return null;
    }
};

const ecrire = (cle, valeur) => {
    try {
        valeur === null ? window.localStorage.removeItem(cle) : window.localStorage.setItem(cle, JSON.stringify(valeur));
    } catch (e) {}
};

/* ---------- Service worker ---------- */

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

/* ---------- Données personnelles : changement d'utilisateur sur l'appareil ---------- */

export async function purgerDonneesPersonnelles() {
    try {
        Object.keys(window.localStorage).filter((cle) => cle.startsWith(PREFIXE_BROUILLON)).forEach((cle) => window.localStorage.removeItem(cle));
    } catch (e) {}

    if ('caches' in window) {
        const noms = await caches.keys();
        await Promise.all(noms.filter((nom) => nom.startsWith(CACHE_PAGES)).map((nom) => caches.delete(nom)));
    }
}

const verifierSession = () => {
    const actuelle = document.querySelector('meta[name="tn-session"]')?.content ?? 'invite';
    const precedente = lire(CLE_SESSION);

    if (precedente && precedente !== 'invite' && precedente !== actuelle) {
        purgerDonneesPersonnelles();
    }

    ecrire(CLE_SESSION, actuelle);
};

verifierSession();
document.addEventListener('livewire:navigated', verifierSession);

/* ---------- Brouillons des formulaires ---------- */

const champsDe = (formulaire) => [...formulaire.querySelectorAll('input, textarea, select')]
    .filter((champ) => ! ['hidden', 'file', 'password', 'submit', 'button'].includes(champ.type))
    .map((champ) => ({ champ, nom: [...champ.attributes].find((a) => a.name.startsWith('wire:model'))?.value ?? champ.name }))
    .filter(({ nom }) => nom);

const lireChamps = (formulaire) => {
    const valeurs = {};

    champsDe(formulaire).forEach(({ champ, nom }) => {
        if (champ.type === 'radio') {
            if (champ.checked) {
                valeurs[nom] = champ.value;
            }
        } else {
            valeurs[nom] = champ.type === 'checkbox' ? champ.checked : champ.value;
        }
    });

    return valeurs;
};

const remplirChamps = (formulaire, valeurs) => {
    champsDe(formulaire).forEach(({ champ, nom }) => {
        if (! (nom in valeurs)) {
            return;
        }

        if (champ.type === 'radio') {
            champ.checked = champ.value === valeurs[nom];
        } else if (champ.type === 'checkbox') {
            champ.checked = Boolean(valeurs[nom]);
        } else {
            champ.value = valeurs[nom];
        }

        // wire:model se met à jour sur ces évènements.
        champ.dispatchEvent(new Event('input', { bubbles: true }));
        champ.dispatchEvent(new Event('change', { bubbles: true }));
    });
};

export function brouillons() {
    try {
        return Object.keys(window.localStorage)
            .filter((cle) => cle.startsWith(PREFIXE_BROUILLON))
            .map((cle) => lire(cle))
            .filter((b) => b && b.chemin);
    } catch (e) {
        return [];
    }
}

const enregistrerBrouillon = (formulaire) => {
    const cle = formulaire.dataset.brouillon;
    ecrire(PREFIXE_BROUILLON + cle, {
        cle,
        libelle: formulaire.dataset.brouillonLibelle ?? 'Formulaire',
        chemin: window.location.pathname + window.location.search,
        champs: lireChamps(formulaire),
        le: new Date().toISOString(),
    });
    window.dispatchEvent(new CustomEvent('tn-brouillons'));
};

const supprimerBrouillon = (cle) => {
    ecrire(PREFIXE_BROUILLON + cle, null);
    window.dispatchEvent(new CustomEvent('tn-brouillons'));
};

// Saisie pendant une coupure : le brouillon suit la saisie.
document.addEventListener('input', (evenement) => {
    const formulaire = evenement.target.closest?.('form[data-brouillon]');
    if (formulaire && ! navigator.onLine && evenement.isTrusted) {
        enregistrerBrouillon(formulaire);
    }
});

// Envoi pendant une coupure : bloqué avant Livewire (phase de capture), gardé en brouillon.
document.addEventListener('submit', (evenement) => {
    const formulaire = evenement.target;
    if (! formulaire.matches?.('form[data-brouillon]')) {
        return;
    }

    if (! navigator.onLine) {
        evenement.preventDefault();
        evenement.stopImmediatePropagation();
        enregistrerBrouillon(formulaire);
        window.dispatchEvent(new CustomEvent('tn-brouillon-garde'));

        return;
    }

    // Envoi réel : le brouillon n'a plus lieu d'être (le jeton F82 empêche un second enregistrement).
    supprimerBrouillon(formulaire.dataset.brouillon);
}, true);

// Retour sur la page d'un brouillon : la saisie est restaurée.
const restaurerBrouillons = () => {
    document.querySelectorAll('form[data-brouillon]').forEach((formulaire) => {
        const brouillon = lire(PREFIXE_BROUILLON + formulaire.dataset.brouillon);
        if (brouillon?.champs) {
            remplirChamps(formulaire, brouillon.champs);
        }
    });
};

document.addEventListener('livewire:navigated', () => setTimeout(restaurerBrouillons, 0));

/** Envoie un brouillon : sur sa page, champs remplis puis envoi ; ailleurs, ouverture de sa page. */
export function envoyerBrouillon(brouillon) {
    const formulaire = document.querySelector(`form[data-brouillon="${CSS.escape(brouillon.cle)}"]`);

    if (! formulaire) {
        window.location.href = brouillon.chemin;

        return;
    }

    remplirChamps(formulaire, brouillon.champs);
    setTimeout(() => formulaire.requestSubmit(), 50);
}

/* ---------- Bandeau d'état du réseau ---------- */

const connexionLente = () => {
    const connexion = navigator.connection;

    return Boolean(connexion && (connexion.saveData || ['slow-2g', '2g'].includes(connexion.effectiveType)));
};

export function etatReseau() {
    return {
        enLigne: navigator.onLine,
        lent: connexionLente(),
        retabli: false,
        garde: false,
        brouillons: [],
        annonce: '',

        init() {
            this.brouillons = brouillons();

            window.addEventListener('offline', () => {
                this.enLigne = false;
                this.retabli = false;
                document.querySelectorAll('form[data-brouillon]').forEach((f) => Object.keys(lireChamps(f)).length && enregistrerBrouillon(f));
                this.annonce = 'Vous êtes hors ligne. Les pages déjà consultées restent lisibles et vos formulaires sont gardés en brouillon.';
            });

            window.addEventListener('online', () => {
                this.enLigne = true;
                this.retabli = true;
                this.brouillons = brouillons();
                this.annonce = this.brouillons.length
                    ? 'Connexion rétablie. Un brouillon attend d’être envoyé.'
                    : 'Connexion rétablie.';
                setTimeout(() => (this.retabli = false), 6000);
            });

            window.addEventListener('tn-brouillons', () => (this.brouillons = brouillons()));
            window.addEventListener('tn-brouillon-garde', () => {
                this.garde = true;
                this.annonce = 'Pas de réseau : votre formulaire est gardé en brouillon sur cet appareil. Il vous sera proposé de l’envoyer au retour de la connexion.';
            });

            navigator.connection?.addEventListener?.('change', () => {
                this.lent = connexionLente();
                if (this.lent && this.enLigne) {
                    this.annonce = 'Connexion lente : les pages peuvent mettre du temps à s’afficher.';
                }
            });

            if (! this.enLigne) {
                this.annonce = 'Vous êtes hors ligne.';
            }
        },

        envoyer(brouillon) {
            this.annonce = 'Envoi du brouillon…';
            envoyerBrouillon(brouillon);
        },

        oublier(brouillon) {
            supprimerBrouillon(brouillon.cle);
        },

        date(iso) {
            return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
        },
    };
}

/* ---------- Page « Vous êtes hors ligne » : pages consultables et brouillons ---------- */

export function pagesHorsLigne() {
    return {
        pages: [],
        brouillons: [],

        async init() {
            this.brouillons = brouillons();

            if (! ('caches' in window)) {
                return;
            }

            const noms = (await caches.keys()).filter((nom) => nom.startsWith(CACHE_PAGES));
            const pages = [];

            for (const nom of noms) {
                const cache = await caches.open(nom);
                for (const requete of await cache.keys()) {
                    const url = new URL(requete.url);
                    if (url.pathname === '/hors-ligne') {
                        continue;
                    }
                    const reponse = await cache.match(requete);
                    const html = await reponse.clone().text();
                    const titre = (html.match(/<title>([^<]*)<\/title>/i)?.[1] ?? url.pathname).replace(/&amp;/g, '&').replace(/&#039;|&#39;/g, '’').replace(/&quot;/g, '"');
                    pages.push({ url: url.pathname + url.search, titre, le: reponse.headers.get('X-TN-Enregistre-Le') });
                }
            }

            this.pages = pages.sort((a, b) => (b.le ?? '').localeCompare(a.le ?? ''));
        },

        date(iso) {
            return iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' }) : '';
        },
    };
}
