/**
 * Piste "Flow" (coverflow) du dashboard fidélité — cf. account::livewire.loyalty-page.
 *
 * Le positionnement des cartes n'est plus dérivé de la position de scroll native
 * du navigateur (scrollLeft) : `active` est un offset virtuel piloté par la
 * molette ou le drag, et chaque carte calcule sa propre transformation 3D en
 * fonction de sa distance à `active`. Ça permet de faire défiler/pivoter même
 * quand le contenu ne déborde pas (rien à scroller nativement), et de garder un
 * mouvement continu (pas de saut entre deux états fixes) pendant le drag.
 *
 * Exposé en fabrique globale plutôt que via `Alpine.data()` sur `alpine:init` —
 * voir le commentaire de `pickupMap` dans ce même dossier pour la course évitée.
 */
window.loyaltyFlow = ({ initialActive = 0, count = 1 } = {}) => ({
    active: initialActive,
    count,
    dragging: false,
    snapping: false,
    startX: 0,
    startActive: 0,
    pitch: 140,
    wheelTimer: null,

    init() {
        this.measure()
        window.addEventListener('resize', () => this.measure())
    },

    measure() {
        const sizer = this.$refs.sizer
        if (sizer) {
            this.pitch = sizer.offsetWidth * 0.98
        }
    },

    clampActive(value) {
        return Math.max(-0.5, Math.min(this.count - 1 + 0.5, value))
    },

    onWheel(e) {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) {
            return
        }
        e.preventDefault()
        this.snapping = false
        this.active = this.clampActive(this.active + e.deltaY / this.pitch)
        clearTimeout(this.wheelTimer)
        this.wheelTimer = setTimeout(() => this.snap(), 140)
    },

    onDown(e) {
        this.dragging = true
        this.snapping = false
        this.startX = e.touches ? e.touches[0].clientX : e.clientX
        this.startActive = this.active
    },

    onMove(e) {
        if (!this.dragging) {
            return
        }
        const x = e.touches ? e.touches[0].clientX : e.clientX
        this.active = this.clampActive(this.startActive - (x - this.startX) / this.pitch)
    },

    onUp() {
        if (!this.dragging) {
            return
        }
        this.dragging = false
        this.snap()
    },

    snap() {
        this.snapping = true
        this.active = Math.max(0, Math.min(this.count - 1, Math.round(this.active)))
        setTimeout(() => {
            this.snapping = false
        }, 320)
    },

    diff(index) {
        return index - this.active
    },

    cardStyle(index) {
        const d = this.diff(index)
        const abs = Math.abs(d)
        // La rampe s'étale sur tout l'écart entre deux cartes (|d| de 0 à 1) : la
        // rotation progresse tout du long du drag et atteint pile son maximum au
        // moment où la carte suivante prend le relais au centre — pas de palier
        // où elle serait déjà à plat avant d'être vraiment arrivée. Au repos
        // (snap sur un index entier), tous les voisins sont à |d|=1, donc tous au
        // même angle max : la bascule reste uniforme d'une carte latérale à l'autre.
        const tilt = Math.max(-58, Math.min(58, d * -58))
        const opacity = Math.max(0.6, 1 - abs * 0.18)
        const z = Math.round(100 - abs * 10)

        return `transform: translate(-50%, -50%) translateX(${d * this.pitch}px) rotateY(${tilt}deg); opacity: ${opacity}; z-index: ${z};`
    },
})
