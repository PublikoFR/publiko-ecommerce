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

## Comment les tokens sont branchés dans le projet

| Token DS | Où c'est câblé dans le projet |
|---|---|
| Couleurs (forest/lime/neutrals/sémantiques) | `tailwind.config.js` → `primary`=forest, `accent`/`lime`, `neutral` (green-tinted), `success/warning/danger/info` |
| Fonts (Forno Waffle / Hanken Grotesk / IBM Plex Mono) | `tailwind.config.js` (`fontFamily`) + `resources/css/app.css` (`@font-face` + `@import`) |
| Radius / shadows | `tailwind.config.js` (`borderRadius`, `boxShadow`) |
| Variables CSS (`--surface-*`, `--text-*`, `--shadow-*`, `--radius-*`…) | `resources/css/app.css` (`:root`) |
| Habillage (motif concentrique) | `public/img/habillage.svg` + helpers `.wk-decor*` dans `app.css` |

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
