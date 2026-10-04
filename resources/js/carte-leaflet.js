/**
 * Kit carte (Leaflet + OpenStreetMap).
 *
 * F61 : module chargé à la demande par resources/js/carte.js, seulement quand une carte
 * arrive à l'écran (Leaflet n'est ni téléchargé ni exécuté au chargement de la page).
 *
 * Chaque élément [data-carte] contient sa configuration JSON :
 * { points: [{lat, lng, titre, url, lignes, lien}], centre: [lat, lng], zoom, mode: 'lecture'|'choix', champLat, champLng }
 *
 * Sécurité : les textes des bulles sont posés avec textContent (jamais innerHTML),
 * et seules les URL http(s) ou relatives sont utilisées dans les liens.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/carte.css';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

// Vite renomme les images : on donne à Leaflet les bonnes URL des icônes de marqueurs.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl, iconUrl, shadowUrl });

const cartes = new Map();

// États reconnus : la couleur du marqueur indique un état (voir app/View/Components/Carte.php).
const ETATS = ['normal', 'perturbe', 'alerte', 'info'];

// F43 : l'état n'est jamais porté par la seule couleur : symbole dans le marqueur (CSS) + libellé dans le titre.
const LIBELLES_ETATS = { normal: 'normal', perturbe: 'perturbé', alerte: 'alerte', info: 'information' };

function urlTuiles() {
    const style = document.documentElement.classList.contains('dark') ? 'dark_all' : 'light_all';

    return `https://{s}.basemaps.cartocdn.com/${style}/{z}/{x}/{y}{r}.png`;
}

function urlSure(url) {
    if (typeof url !== 'string' || url === '') {
        return null;
    }

    try {
        const parsed = new URL(url, window.location.origin);

        return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : null;
    } catch {
        return null;
    }
}

function contenuBulle(point) {
    const bloc = document.createElement('div');
    const titre = document.createElement('strong');
    titre.textContent = point.titre ?? '';
    bloc.appendChild(titre);

    // Lignes d'information (adresse, horaires…) : un paragraphe par ligne, texte brut.
    (Array.isArray(point.lignes) ? point.lignes : []).forEach((texte) => {
        const ligne = document.createElement('p');
        ligne.textContent = String(texte);
        // Style en ligne : Leaflet impose une grande marge aux paragraphes des bulles.
        ligne.style.margin = '0.25rem 0 0';
        ligne.style.whiteSpace = 'pre-line';
        bloc.appendChild(ligne);
    });

    const url = urlSure(point.url);
    if (url) {
        const lien = document.createElement('a');
        lien.href = url;
        lien.textContent = typeof point.lien === 'string' && point.lien !== '' ? point.lien : 'Voir le détail';
        lien.className = 'mt-1 block';
        bloc.appendChild(lien);
    }

    return bloc;
}

function nombre(valeur) {
    const n = Number.parseFloat(valeur);

    return Number.isFinite(n) ? n : null;
}

function composantLivewire(el) {
    const racine = el.closest('[wire\\:id]');

    return racine && window.Livewire ? window.Livewire.find(racine.getAttribute('wire:id')) : null;
}

export function initialiser(el) {
    if (cartes.has(el)) {
        return;
    }

    let config;
    try {
        config = JSON.parse(el.dataset.carte || '{}');
    } catch {
        return;
    }

    const zone = el.querySelector('[data-carte-zone]');
    const message = el.querySelector('[data-carte-message]');
    const points = (config.points || []).filter((p) => nombre(p.lat) !== null && nombre(p.lng) !== null);

    const carte = L.map(zone, { scrollWheelZoom: false }).setView(config.centre, config.zoom);
    cartes.set(el, carte);

    // Tuiles claires (Positron) ou sombres (Dark Matter) selon le thème, échangées quand il change.
    const tuiles = L.tileLayer(urlTuiles(), {
        maxZoom: 19,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    }).addTo(carte);
    // F93 : fond de carte injoignable (réseau coupé ou fournisseur en panne) : message clair, repères conservés.
    tuiles.once('tileerror', () => {
        if (message) {
            message.textContent = 'Fond de carte momentanément indisponible (connexion coupée ou lente). Les repères et adresses restent affichés.';
        }
    });
    const observateurTheme = new MutationObserver(() => tuiles.setUrl(urlTuiles()));
    observateurTheme.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    carte.on('unload', () => observateurTheme.disconnect());

    const marqueurs = points.map((p) => {
        const options = { title: p.titre ?? '', alt: p.titre ?? '' };
        if (ETATS.includes(p.etat)) {
            const titre = [p.titre, `état : ${LIBELLES_ETATS[p.etat]}`].filter(Boolean).join(' — ');
            options.title = titre;
            options.alt = titre;
            options.icon = L.divIcon({ className: `tn-marqueur tn-marqueur-${p.etat}`, iconSize: [22, 22], iconAnchor: [11, 11], popupAnchor: [0, -12] });
        }
        const marqueur = L.marker([nombre(p.lat), nombre(p.lng)], options).addTo(carte);
        if (p.titre || p.url || p.lignes?.length) {
            marqueur.bindPopup(contenuBulle(p));
        }

        return marqueur;
    });

    if (marqueurs.length > 1 && config.mode === 'lecture') {
        carte.fitBounds(L.featureGroup(marqueurs).getBounds(), { padding: [30, 30], maxZoom: 16 });
    } else if (marqueurs.length === 1) {
        carte.setView(marqueurs[0].getLatLng(), Math.max(config.zoom, 15));
    }

    // Une carte dans un bloc replié a une taille nulle : on la recalcule quand il s'ouvre.
    new ResizeObserver(() => carte.invalidateSize()).observe(zone);

    if (config.mode === 'choix') {
        activerChoix(el, carte, config, marqueurs[0] ?? null, message);
    }
}

function activerChoix(el, carte, config, marqueurInitial, message) {
    let marqueur = marqueurInitial;
    // Livewire peut démarrer après ce module : le composant est cherché au moment où on en a besoin.
    const wire = () => composantLivewire(el);

    const afficher = (texte) => {
        if (message) {
            message.textContent = texte;
        }
    };

    const placer = (lat, lng, recentrer = false) => {
        if (marqueur) {
            marqueur.setLatLng([lat, lng]);
        } else {
            marqueur = L.marker([lat, lng], { title: 'Position choisie', alt: 'Position choisie' }).addTo(carte);
        }
        if (recentrer) {
            carte.setView([lat, lng], Math.max(carte.getZoom(), 15));
        }
    };

    const choisir = (lat, lng, recentrer = false) => {
        placer(lat, lng, recentrer);
        // Remplit les propriétés Livewire (comme un wire:model) : les champs du formulaire se mettent à jour.
        wire()?.$set(config.champLat, lat.toFixed(7), false);
        wire()?.$set(config.champLng, lng.toFixed(7));
        afficher(`Position choisie : ${lat.toFixed(5)}, ${lng.toFixed(5)}`);
    };

    carte.on('click', (e) => choisir(e.latlng.lat, e.latlng.lng));

    // Position déjà enregistrée (modification) ; puis le marqueur suit les champs saisis à la main.
    const suivre = (recentrer = false) => {
        const lat = nombre(wire()?.$get(config.champLat));
        const lng = nombre(wire()?.$get(config.champLng));
        if (lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180) {
            placer(lat, lng, recentrer);
        }
    };
    const surveiller = () => {
        suivre(true);
        wire()?.$watch(config.champLat, () => suivre());
        wire()?.$watch(config.champLng, () => suivre());
    };
    if (wire()) {
        surveiller();
    } else {
        document.addEventListener('livewire:initialized', surveiller, { once: true });
    }

    el.querySelector('[data-carte-localiser]')?.addEventListener('click', () => {
        if (!('geolocation' in navigator)) {
            afficher("Votre navigateur ne permet pas la géolocalisation. Cliquez sur la carte pour choisir l'emplacement.");

            return;
        }

        afficher('Localisation en cours…');
        navigator.geolocation.getCurrentPosition(
            (position) => choisir(position.coords.latitude, position.coords.longitude, true),
            (erreur) => {
                const textes = {
                    1: "Accès à la position refusé. Autorisez la localisation dans votre navigateur, ou cliquez sur la carte pour choisir l'emplacement.",
                    2: "Position introuvable pour le moment. Cliquez sur la carte pour choisir l'emplacement.",
                    3: "La localisation a pris trop de temps. Réessayez ou cliquez sur la carte.",
                };
                afficher(textes[erreur.code] ?? "Impossible de vous localiser. Cliquez sur la carte pour choisir l'emplacement.");
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    });
}

export function nettoyer() {
    for (const [el, carte] of cartes) {
        if (!el.isConnected) {
            carte.remove();
            cartes.delete(el);
        }
    }
}
