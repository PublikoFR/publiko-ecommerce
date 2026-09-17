# Weklo Design System — source importée

Design System importé depuis **Claude Design** (projet « Weklo Design System »,
id `23c47be1-b454-45b2-b08d-1c629a6710bf`, propriétaire Rom). Ce dossier est la
**source de vérité** du design du Front Office (storefront). Toute évolution du
Front doit s'y référer.

> ⚠️ Le DS d'origine est livré en **React (JSX)**. Notre stack front est
> **Laravel 11 + Lunar + Livewire 3 + Alpine + Tailwind + Blade**. Les fichiers
> `.jsx` ci-dessous sont donc conservés comme **référence / spécification**, pas
> exécutés. L'implémentation réelle se fait en composants **Blade + Alpine +
> Tailwind** dans `packages/pko/storefront` et les autres packages front.

## Règles d'implémentation Front Office — obligatoires

Référencées par `AGENTS.md §3.3`. S'appliquent à **tout le Front Office** : pages, layouts, composants Blade, Livewire, e-mails transactionnels côté client.

1. **Tokens uniquement** : couleurs via les classes Tailwind mappées sur le DS (`primary` = forest `#00453e`, `accent`/`lime` = `#aac932`, `neutral` green-tinted, `success`/`warning`/`danger`/`info`) ou les variables CSS de `resources/css/app.css` (`var(--surface-*)`, `var(--text-*)`, `var(--shadow-*)`, `var(--radius-*)`…). **Jamais** de hex en dur dans une vue front.
2. **Typo** : `font-display` (Forno Waffle) pour les titres, `font-sans` (Hanken Grotesk) pour le corps, `font-mono` (IBM Plex Mono) pour les données (réf. produit, prix techniques).
3. **Formes & ombres** : coins arrondis généreux (`rounded-md/xl/2xl`), ombres forest-teintées (`shadow-sm/md/lg`, `shadow-accent` pour le CTA lime unique).
4. **Accent lime = parcimonie** : `bg-accent-500` réservé à **un seul CTA fort par vue**. Le reste des actions = `primary`.
5. **Icônes** : Lucide, trait 2px arrondi, jamais d'emoji.
6. **Composants réutilisables** : étendre les composants Blade existants (`packages/pko/storefront/resources/views/components/ui/*` et `storefront/*`), alignés sur `design-system/components/*.prompt.md`. Ne pas dupliquer un composant existant.
7. **Habillage** : motif via `public/img/habillage.svg` + helpers `.wk-decor*`, en filigrane (5–12 % d'opacité), jamais dominant (détail plus bas).
8. **Feature absente du prototype** `ui_kits/ecommerce/` : l'implémenter avec les mêmes tokens/composants, sans inventer de style.
9. **Branding dynamique** : cf. « Point de vigilance branding » plus bas.

## Comment les tokens sont branchés dans le projet

| Token DS | Où c'est câblé dans le projet |
|---|---|
| Couleurs (forest/lime/neutrals/sémantiques) | `tailwind.config.js` → `primary`=forest, `accent`/`lime`, `neutral` (green-tinted), `success/warning/danger/info` |
| Fonts (Forno Waffle / Hanken Grotesk / IBM Plex Mono) | `tailwind.config.js` (`fontFamily`) + `resources/css/app.css` (`@font-face` + `@import`) |
| Radius / shadows | `tailwind.config.js` (`borderRadius`, `boxShadow`) |
| Variables CSS (`--surface-*`, `--text-*`, `--shadow-*`, `--radius-*`…) | `resources/css/app.css` (`:root`) |
| Habillage (motif concentrique) | `public/img/habillage.svg` (vrai SVG graphiste, anneaux) + helpers `.wk-decor*` dans `app.css` |

Classes Tailwind à utiliser côté front : `bg-primary-600` (forest), `bg-accent-500`
(lime, **un seul CTA lime par vue**), `text-neutral-*`, `rounded-xl`, `shadow-md`,
`font-display` (Forno Waffle pour les titres), `font-mono` (réf. produit / data).

## Contenu importé

- `styles.css` — point d'entrée DS (importe tous les tokens).
- `tokens/` — `colors.css`, `typography.css`, `spacing.css`, `effects.css`,
  `fonts.css`, `base.css`, `decor.css` (habillage).
- `components/` — specs des primitives (`*.prompt.md`) : forms (Button, IconButton,
  Input, Select, Checkbox, Switch), data-display (Badge, Tag, Card, Avatar,
  ProgressBar), feedback (Alert), navigation (Tabs).
- `ui_kits/ecommerce/` — **prototype ecommerce complet** (React) : `index.html`,
  `shared.jsx`, `Header/Footer/CartDrawer/HomePage/CategoryPage/ProductPage.jsx`,
  `README.md`. C'est la référence visuelle des pages à reproduire en Blade.
- `assets/habillage.svg` — motif de marque.
- `SKILL.md`, `readme.md` — guidelines de marque (voix, iconographie Lucide,
  couleurs, type, formes arrondies, motion).

## Habillage `.wk-decor` — règles de rendu (charte)

Le motif `public/img/habillage.svg` est le **vrai SVG du graphiste**
(`design-system/assets/Éléments d_habillages-01.svg`) : trois anneaux
concentriques centrés dans un viewBox `0 0 400 400`. Il est peint via un **mask
CSS** (`.wk-decor` dans `resources/css/app.css`) pour que la couleur soit
pilotable (`--wk-decor-color`, ou classes `--lime` / `--forest` / `--white`).

Règles charte (issues des gabarits réseaux sociaux) :

- **Pleine couleur, jamais de transparence** sur l'habillage décoratif fort
  (slider, tuiles) → défaut `opacity: 1`. La variable `--wk-decor-opacity` reste
  disponible **uniquement** pour un usage filigrane explicite et rare (ex. bandeau
  compte pro).
- **Centre du SVG pile dans l'angle** de la div : `translate(±50%, ±50%)` selon le
  coin (`--tl/--tr/--bl/--br`) → on ne voit qu'un quart d'anneaux, comme la charte.
- Couleur lime `#aac932` par défaut (`--wk-decor--lime`), forest `#00453e`, blanc.

Câblage actuel :

- **Slider** (`storefront-cms::livewire.home-hero`) : anneaux lime pleine couleur,
  toujours côté droit (tr/br alterné) pour ne pas chevaucher le texte aligné à
  gauche. Le dégradé posé sur l'image d'un slide reprend la **même teinte** que
  `bg_color` du slide.
- **Tuiles « Nos univers »** (`home-tiles`) : dispo gabarit charte — photo en fond,
  pastille forest en **quart-de-cercle haut-droite** (cercle `bg-primary-800`
  centré sur l'angle) portant le titre/CTA alignés à droite, + anneaux lime pleine
  couleur en **bas-gauche**.

## Point de vigilance branding (règle projet §3.0)

Le back-office est **réutilisable multi-boutiques**. Le DS Weklo fournit le
**thème visuel** (couleurs/typo/formes) — légitime à intégrer comme thème actif.
Mais **le nom de marque, le logo, la tagline, les coordonnées** restent
**dynamiques** via `Pko\StorefrontCms\Models\Setting` / `brand_name()` : ne jamais
coder « Weklo » en dur dans une vue, un `<title>`, un e-mail. Le logo Weklo du
prototype n'est donc **pas** copié dans le storefront (il vient du Setting admin).

## À compléter (non bloquant)

- **Fonts** : déposer `FornoWaffle-Regular.woff2` + `FornoWaffle-Bold.woff2` dans
  `public/fonts/` (+ `design-system/assets/fonts/`). En attendant, fallback
  Hanken Grotesk. Copier depuis le projet Claude Design (`assets/fonts/`).
- **Mirror brut optionnel** : les `.jsx`/`.d.ts` d'implémentation React, les
  specimens `guidelines/*.card.html` et le bundle compilé `_ds_bundle.js`
  restent dans le projet Claude Design et sont récupérables à la demande — non
  nécessaires à l'implémentation Blade.
