/**
 * Carte des points relais du checkout (Alpine + Leaflet).
 *
 * Ce composant vivait auparavant en `x-data="{...}"` inline dans
 * `livewire/components/shipping-options.blade.php`. Deux raisons de l'en sortir :
 *
 * 1. Les gabarits JS contenaient des `class=\"...\"`. En HTML, `\"` n'est pas une
 *    séquence d'échappement : le backslash est littéral et le guillemet **ferme
 *    l'attribut**. Tout le corps du composant était donc recraché en texte brut
 *    au milieu de la page (bug visible dès que la liste a enfin renvoyé des points).
 * 2. Leaflet était chargé via `@push('scripts')`, or `layouts/checkout.blade.php`
 *    ne déclare aucun `@stack` — la CDN n'était jamais insérée sur la page de
 *    commande, la seule qui en a besoin.
 *
 * Leaflet reste chargé depuis la CDN à la demande (aucune dépendance npm, aucune
 * clé API) mais depuis le bundle Vite, présent sur tous les layouts.
 */

const LEAFLET_VERSION = '1.9.4'
const LEAFLET_CSS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`
const LEAFLET_JS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`
// Vérifiés le 2026-07-31 contre les fichiers servis par unpkg :
//   curl -s https://unpkg.com/leaflet@1.9.4/dist/leaflet.js | openssl dgst -sha256 -binary | openssl base64 -A
// Le hash JS repris de l'ancien bloc Blade était corrompu sur sa fin
// (…NV/XN/WPeE= au lieu de …NV1lvTlZBo=) : le navigateur bloquait donc le script
// en silence, et la carte restait un rectangle gris. Un SRI faux ne se voit qu'à
// l'exécution — toujours le recalculer, jamais le recopier.
const LEAFLET_CSS_SRI = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
const LEAFLET_JS_SRI = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='

/** Chargement unique, partagé par toutes les instances de carte de la page. */
let leafletLoader = null

function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L)
    }

    if (leafletLoader) {
        return leafletLoader
    }

    // La feuille de style doit être appliquée AVANT `L.map()` : sans elle,
    // `.leaflet-container` n'a pas de mise en page et les tuiles se retrouvent
    // positionnées hors cadre — visuellement, un rectangle gris.
    const css = new Promise((resolve, reject) => {
        const existing = document.querySelector(`link[href="${LEAFLET_CSS}"]`)
        if (existing) {
            resolve()

            return
        }

        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = LEAFLET_CSS
        link.integrity = LEAFLET_CSS_SRI
        link.crossOrigin = 'anonymous'
        link.onload = () => resolve()
        link.onerror = () => reject(new Error('Leaflet CSS failed to load'))
        document.head.appendChild(link)
    })

    const js = new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = LEAFLET_JS
        script.integrity = LEAFLET_JS_SRI
        script.crossOrigin = 'anonymous'
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('Leaflet JS failed to load'))
        document.head.appendChild(script)
    })

    leafletLoader = Promise.all([css, js]).then(() => window.L)

    return leafletLoader
}

/**
 * Marqueur « P » stylé aux tokens du design system (aucun hex en dur).
 */
function markerIcon(L, isSelected) {
    const base =
        'text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md cursor-pointer text-xs font-bold transition'
    const tone = isSelected
        ? 'bg-primary-600 ring-2 ring-primary-300'
        : 'bg-primary-400 hover:bg-primary-600'

    return L.divIcon({
        className: '',
        html: `<div class="${base} ${tone}">P</div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    })
}

function escapeHtml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c],
    )
}

/**
 * Exposé en fabrique globale plutôt que via `Alpine.data()` sur `alpine:init`.
 *
 * Alpine est embarqué dans le bundle Livewire, chargé par un `<script>` classique
 * en fin de `<body>`, alors que ce module part d'un `<script type="module">` du
 * `@vite` en `<head>` — donc différé. Selon le moment où Livewire démarre Alpine,
 * `alpine:init` peut avoir déjà été émis quand ce fichier s'exécute : le
 * `Alpine.data()` arrive alors trop tard et `x-data="pickupMap(…)"` ne résout rien
 * (conteneur vide, aucune erreur bruyante). Une fonction globale est résolue au
 * moment de l'évaluation de l'expression, ce qui supprime la course.
 */
window.pickupMap = ({ points = [], selectedId = null } = {}) => ({
    map: null,
    markers: {},
    points,
    selectedId,

    init() {
        const located = this.points.filter((p) => p.latitude && p.longitude)

        if (!located.length || !this.$refs.mapContainer) {
            return
        }

        loadLeaflet()
            .then((L) => this.$nextTick(() => this.render(L, located)))
            .catch((error) => {
                // CDN injoignable ou SRI invalide : la liste sous la carte reste
                // utilisable, c'est elle qui fait foi pour la sélection. On trace
                // quand même — un échec muet est exactement ce qui a coûté cher ici.
                console.error('[pickup-map]', error)
            })
    },

    render(L, located) {
        if (this.map || !this.$refs.mapContainer) {
            return
        }

        this.map = L.map(this.$refs.mapContainer)

        L.tileLayer(`https://tile.openstreetmap.org/{z}/{x}/{y}.png`, {
            maxZoom: 18,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(this.map)

        // Cadrage sur l'ensemble des points : centrer sur le premier laissait
        // une partie des pins hors écran.
        this.map.fitBounds(
            L.latLngBounds(located.map((p) => [p.latitude, p.longitude])),
            { padding: [30, 30], maxZoom: 15 },
        )

        located.forEach((p) => {
            const marker = L.marker([p.latitude, p.longitude], {
                icon: markerIcon(L, p.id === this.selectedId),
            })
                .addTo(this.map)
                .bindPopup(
                    `<strong class="text-sm">${escapeHtml(p.name)}</strong><br>` +
                        `<span class="text-xs text-neutral-500">${escapeHtml(p.address1)}, ` +
                        `${escapeHtml(p.postcode)} ${escapeHtml(p.city)}</span>`,
                    // autoPan désactivé : c'est selectPoint() qui recentre, sinon
                    // Leaflet recadre pour faire tenir la bulle et le point choisi
                    // ne finit pas au centre.
                    { autoPan: false },
                )

            marker.on('click', () => this.selectPoint(p.id))
            this.markers[p.id] = marker
        })
    },

    /** Clic sur un pin ou sur un item de la liste : les deux convergent ici. */
    selectPoint(id) {
        this.selectedId = id
        this.$wire.set('pickupPointId', id)

        if (!window.L) {
            return
        }

        Object.keys(this.markers).forEach((key) => {
            this.markers[key].setIcon(markerIcon(window.L, key === id))
        })

        this.focusMarker(id)
    },

    /**
     * Amène le point choisi au centre et n'y laisse qu'une bulle ouverte.
     *
     * Vaut aussi pour un clic sur le pin : Leaflet aurait ouvert la bulle sans
     * recentrer, le point restant collé au bord quand on clique en périphérie.
     */
    focusMarker(id) {
        const marker = this.markers[id]

        if (!this.map || !marker) {
            return
        }

        this.map.closePopup()
        this.map.panTo(marker.getLatLng(), { animate: true })
        marker.openPopup()
    },
})
