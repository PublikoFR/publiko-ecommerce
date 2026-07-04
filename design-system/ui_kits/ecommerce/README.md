# Weklo — Ecommerce UI Kit (Boutique Pro)

A high-fidelity, interactive recreation of Weklo's **B2B ecommerce store** for
professionals of the *fermeture* trade (menuiseries, portes, portails, volets,
motorisations, portes de garage).

> **Note:** no existing codebase, Figma, or live site was provided, so these
> screens are an original design built faithfully in the Weklo brand system —
> not a copy of a real product. When the real store (or its design files) are
> shared, these should be reconciled against it.

## Run it
Open `index.html`. It's a click-through prototype:
- Browse **categories** from the hero or the nav bar.
- Open the **listing page** → toggle **layout options** (filters left vs. on top,
  grid vs. list), sort, and filter by material.
- Open a **product** → choose a coloris, set quantity, **add to cart**.
- The **cart drawer** slides in with HT / TVA / TTC totals and a "convertir en devis" action.

## Files
- `index.html` — app shell + routing state (home / category / product) + cart.
- `shared.jsx` — `Icon` (Lucide→SVG), catalog data (`CATEGORIES`, `PRODUCTS`),
  `ProductViz` (branded image placeholder), `Stars`, `ProductCard`, formatters.
- `Header.jsx` — utility bar, search, account/cart, category nav.
- `Footer.jsx` — trust strip + link columns.
- `CartDrawer.jsx` — slide-in cart + `Stepper`.
- `HomePage.jsx` — hero, universes grid, featured, pro-account banner.
- `CategoryPage.jsx` — breadcrumb, filters (side/top **layout option**), sort, grid/list.
- `ProductPage.jsx` — gallery, price (HT/TTC), variants, qty, tabs, related.

## Built on the design system
Composes the bundled primitives (`window.WekloDesignSystem_23c47b`): `Button`,
`Badge`, `Tag`, `Tabs`, `Checkbox`. Icons via Lucide. All color/space/type from
`styles.css` tokens.

## Layout options (per your request)
The listing page exposes two **filter layouts** (left sidebar / top bar) and two
**views** (grid / list) as live controls. Tell me which should be the default,
or if you'd like the same treatment applied to other screens.

## Product imagery
Product photos weren't available, so each product shows a branded placeholder
tile (`ProductViz`) with the category's Lucide icon. Swap in real photography by
replacing `ProductViz` with an `<img>`.
