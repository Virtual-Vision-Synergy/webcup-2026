/*
 * F93 : service worker Terra Nova — les fonctions essentielles restent lisibles pendant une coupure de réseau.
 *
 * - Pages essentielles (accueil, urgences, services, mes demandes…) : réseau d'abord, copie gardée en cache à chaque visite
 *   avec sa date ; hors ligne (ou réseau trop lent), la copie est servie avec un bandeau « Version enregistrée le … ».
 * - Page jamais consultée hors ligne : page « Vous êtes hors ligne » (/hors-ligne, mise en cache à l'installation).
 * - Fichiers statiques (CSS, JS, polices, icônes) : servis depuis le cache puis rafraîchis en arrière-plan.
 * - Jamais mis en cache : envois (POST), Livewire, administration, espace agent, connexion et pages de modification.
 * Les pages personnelles sont effacées du cache quand l'utilisateur change (déconnexion), voir resources/js/app.js.
 */
const VERSION = 'v1';
const CACHE_PAGES = `tn-pages-${VERSION}`;
const CACHE_STATIQUE = `tn-statique-${VERSION}`;
const PAGE_HORS_LIGNE = '/hors-ligne';
const PRECACHE = [PAGE_HORS_LIGNE, '/', '/urgences', '/lexique'];
const DELAI_RESEAU_LENT = 5000;

/** Pages gardées pour la consultation hors ligne (lecture et formulaires essentiels, jamais l'édition). */
const PAGES_ESSENTIELLES = [
    /^\/$/,
    /^\/hors-ligne$/,
    /^\/urgences$/,
    /^\/lexique$/,
    /^\/dashboard$/,
    /^\/services$/,
    /^\/services\/(?!create$)[^/]+$/,
    /^\/mes-demandes(\/\d+)?$/,
    /^\/demarches(\/\d+)?$/,
    /^\/demarches\/create$/,
    /^\/messages\/create$/,
    /^\/signalements\/create$/,
    /^\/alertes\/[^/]+$/,
    /^\/rendez-vous$/,
    /^\/notifications$/,
];

const STATIQUE = /\.(?:css|js|woff2?|ttf|svg|png|jpe?g|webp|ico)$/;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_PAGES);
        const statique = await caches.open(CACHE_STATIQUE);

        await Promise.all(PRECACHE.map(async (chemin) => {
            try {
                const reponse = await fetch(chemin, { credentials: 'same-origin' });
                if (! estCacheable(reponse)) {
                    return;
                }

                // Les CSS / JS de la page hors ligne sont mis en cache tout de suite : elle reste mise en forme.
                const html = await reponse.clone().text();
                const fichiers = [...html.matchAll(/(?:href|src)="([^"]+\.(?:css|js)[^"]*)"/g)]
                    .map((m) => new URL(m[1], self.location.origin))
                    .filter((url) => url.origin === self.location.origin);
                await Promise.all(fichiers.map((url) => statique.add(url.href).catch(() => {})));

                await cache.put(chemin, await horodater(reponse));
            } catch (e) {
                // Installation hors ligne : la page sera mise en cache à la prochaine visite.
            }
        }));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const noms = await caches.keys();
        await Promise.all(noms.filter((nom) => nom.startsWith('tn-') && ! [CACHE_PAGES, CACHE_STATIQUE].includes(nom)).map((nom) => caches.delete(nom)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const requete = event.request;
    const url = new URL(requete.url);

    if (requete.method !== 'GET' || url.origin !== self.location.origin || /^\/(livewire|admin|agent|login|logout|register)/.test(url.pathname)) {
        return;
    }

    if (estPage(requete)) {
        event.respondWith(page(event, requete, url));
    } else if (STATIQUE.test(url.pathname)) {
        event.respondWith(statique(event, requete));
    }
});

function estPage(requete) {
    return requete.mode === 'navigate'
        || requete.headers.has('X-Livewire-Navigate')
        || (requete.headers.get('Accept') ?? '').includes('text/html');
}

function estEssentielle(url) {
    return PAGES_ESSENTIELLES.some((motif) => motif.test(url.pathname));
}

function estCacheable(reponse) {
    return reponse.ok && ! reponse.redirected && reponse.type === 'basic'
        && (reponse.headers.get('Content-Type') ?? '').includes('text/html');
}

async function page(event, requete, url) {
    const cache = await caches.open(CACHE_PAGES);
    const enregistree = estEssentielle(url) ? await cache.match(requete) : undefined;
    const reseau = fetch(requete).then(async (reponse) => {
        if (estEssentielle(url) && estCacheable(reponse)) {
            await cache.put(requete, await horodater(reponse.clone()));
        }

        return reponse;
    });

    // La mise à jour du cache continue même si la copie a été servie (réseau lent).
    event.waitUntil(reseau.catch(() => {}));

    try {
        if (! enregistree) {
            return await reseau;
        }

        return await Promise.race([
            reseau,
            new Promise((resolve) => setTimeout(() => resolve(marquer(enregistree, 'lent')), DELAI_RESEAU_LENT)),
        ]);
    } catch (e) {
        if (enregistree) {
            return marquer(enregistree, 'hors-ligne');
        }

        const horsLigne = await cache.match(PAGE_HORS_LIGNE);

        return horsLigne ?? new Response('<h1>Vous êtes hors ligne</h1><p>Réessayez quand la connexion sera revenue.</p>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
}

async function statique(event, requete) {
    const cache = await caches.open(CACHE_STATIQUE);
    const enregistre = await cache.match(requete);
    const reseau = fetch(requete).then((reponse) => {
        if (reponse.ok && reponse.type === 'basic') {
            cache.put(requete, reponse.clone());
        }

        return reponse;
    });

    if (enregistre) {
        event.waitUntil(reseau.catch(() => {}));

        return enregistre;
    }

    return reseau;
}

/** Copie de la réponse avec la date d'enregistrement (lue par marquer() et par la page /hors-ligne). */
async function horodater(reponse) {
    const entetes = new Headers(reponse.headers);
    entetes.set('X-TN-Enregistre-Le', new Date().toISOString());

    return new Response(await reponse.blob(), { status: reponse.status, statusText: reponse.statusText, headers: entetes });
}

/** Ajoute en haut de la copie un bandeau lisible par le lecteur d'écran : hors ligne + date de mise à jour. */
async function marquer(reponse, raison) {
    const date = new Date(reponse.headers.get('X-TN-Enregistre-Le') ?? Date.now());
    const quand = date.toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' });
    const titre = raison === 'lent' ? 'Connexion très lente' : 'Vous êtes hors ligne';
    const bandeau = `<div role="alert" data-tn-copie-hors-ligne style="position:sticky;top:0;z-index:80;background:#9A1238;color:#fff;padding:.6rem 1rem;font:600 14px/1.4 system-ui,sans-serif;text-align:center">${titre} : version enregistrée le ${quand}. Les informations ont pu changer depuis ; les envois seront possibles au retour du réseau.</div>`;
    const html = (await reponse.clone().text()).replace(/<body([^>]*)>/i, (balise) => balise + bandeau);
    const entetes = new Headers(reponse.headers);
    entetes.delete('Content-Length');

    return new Response(html, { status: 200, headers: entetes });
}
