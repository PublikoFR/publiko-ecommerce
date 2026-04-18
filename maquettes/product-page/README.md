# Handoff : Page d'édition produit — Admin Lunar

## Vue d'ensemble

Page d'administration permettant de créer/éditer un produit dans le back-office e‑commerce Lunar. Elle combine le meilleur des systèmes existants (Prestashop, WooCommerce, Shopify) : tous les champs essentiels visibles d'un coup d'œil (photo, titre, prix, stock, SKU, statut, catégories, SEO, description courte/longue, variantes, tarification B2B, produits liés, historique).

Le layout retenu est **2 colonnes** : formulaire principal à gauche, sidebar contextuelle à droite (statut, organisation, produits liés, historique).

## À propos des fichiers de design

Les fichiers de ce bundle sont des **références de design créés en HTML/React (via Babel standalone)** — des prototypes qui montrent l'apparence et le comportement voulus, **pas du code production à copier tel quel**. La tâche est de **recréer ces designs dans la codebase existante du back-office Lunar** en utilisant ses patterns, ses composants et son framework (Livewire / Vue / React selon le stack Lunar), et ses tokens Tailwind déjà en place.

Si un système de composants existe (boutons, inputs, cards, switches, tabs), il **faut s'en servir** plutôt que de recréer. Ce handoff décrit précisément les classes, tokens et comportements à reproduire.

## Fidélité

**High-fidelity (hifi)**. Tous les couleurs, espacements, rayons, typographies et interactions sont spécifiés. L'implémentation doit être pixel-perfect par rapport au prototype, en utilisant les composants et classes Tailwind du projet Lunar.

---

## Layout principal

### Structure générale

```
┌─────────────────────────────────────────────────────────┐
│  Sidebar (232px)  │            Main                     │
│                   │  ┌──────────────────────────────┐   │
│  [Lunar logo]     │  │ Topbar (56px, sticky)        │   │
│                   │  ├──────────────────────────────┤   │
│  Tableau de bord  │  │ Breadcrumb                   │   │
│  Catalogue        │  │ H1 + actions                 │   │
│    Produits ★     │  │                              │   │
│    Marques        │  │ ┌──────────────┐ ┌─────────┐ │   │
│    Catégories     │  │ │              │ │         │ │   │
│    ...            │  │ │  Formulaire  │ │ Sidebar │ │   │
│                   │  │ │  principal   │ │ contex. │ │   │
│  Storefront       │  │ │              │ │(sticky) │ │   │
│    ...            │  │ └──────────────┘ └─────────┘ │   │
│                   │  │                              │   │
│  Commandes        │  │ ┌──────────────────────────┐ │   │
│    ...            │  │ │ Barre d'actions sticky   │ │   │
│                   │  │ └──────────────────────────┘ │   │
│  Marketing        │  └──────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

- App : `grid-template-columns: 232px 1fr; min-height: 100vh`
- Content zone : `grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start`
- Sidebar droite : `position: sticky; top: 70px`
- Padding content : `22px 28px 60px`

---

## Sections du formulaire (colonne principale)

Toutes les sections sont des **cards** : `background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.04)`.

Chaque card a un header (padding `14px 18px`, border-bottom) avec titre H3 (14px, 600, icône à gauche) et un hint optionnel à droite, puis un body (padding `18px`, `gap: 14px` entre enfants).

### 1. Informations générales
- Titre du produit (input, required, pleine largeur)
- Grille 2 colonnes : SKU (avec préfixe `#` via input-group) + Code-barres EAN/UPC
- Description courte (textarea 3 rows, help text dessous)

### 2. Médias
- Hint `4 images · 0 vidéo`
- Grille CSS auto-fill 100px min
- Première tuile : **primary** (`grid-column: span 2; grid-row: span 2`) avec badge "PRINCIPALE" en top-left (bleu `#2563eb`, 10px, blanc)
- Tuiles suivantes : carrées, badge "×" au hover (top-right, cercle blanc)
- Dernière tuile : ajout, border-dashed, icône `+` centrée
- Placeholder visuel : `repeating-linear-gradient(135deg, #eef2f7 0 8px, #e5e9f0 8px 16px)` avec label mono 10px
- Drag-drop implicite (à brancher sur lib comme SortableJS / dnd-kit)

### 3. Description longue (WYSIWYG)
- Toolbar : Paragraphe/H2/H3 (select) | Gras / Italique / Souligné | Liste / Lien / Image | IA (bouton bleu à droite)
- Zone `contentEditable` (min-height 140px, padding 12/14px, font-size 13.5px, line-height 1.6)
- Encadrement : border 1px avec focus bleu + shadow

### 4. Caractéristiques techniques
- Table 2 colonnes (clé | valeur), éditable inline
- Clé en fond gris clair `#fafbfc`, valeur en blanc
- Bouton × par ligne
- Bouton "+ Ajouter une caractéristique" (link bleu)

### 5. Tarification
- Grille 3 col : Prix (TTC, suffixe €, required) | Prix de comparaison (barré/PDSF) | Prix d'achat (coût)
- Grille 2 col : Classe de taxe (select : TVA 20% / 10% / 5,5% / 0%) | Marge estimée (readonly, calculée, vert)
- **Tarification B2B** : sous-card avec table des paliers
  - Colonnes : Groupe client | À partir de X unités | Prix unitaire (mono, align right) | Remise (badge vert) | menu "⋯"
  - Bouton "+ Ajouter un palier" en top-right du sous-card
  - Données exemple : Installateurs −15% / −20% (à 10u) / Revendeurs −25% (à 50u)

### 6. Inventaire & expédition
- Switch-row : "Suivre le stock de ce produit" (on par défaut)
- Grille 3 col : Stock actuel (suffixe "u.") | Seuil d'alerte | Stock de sécurité
- Switch-row : "Autoriser les commandes en rupture" (backorder)
- Select : Délai de réapprovisionnement
- Séparateur `hr`
- Grille 2 col : Poids (kg) | Classe d'expédition
- Dimensions : 3 input-group inline (L × l × H, cm)

### 7. Variantes
- Header hint `4 variantes actives`
- Matrice 6 colonnes : Variante (swatch + nom) | SKU | Prix | Stock | Statut (switch) | ⋯
- Inputs sans bordure, apparaissent au hover/focus
- Footer : bouton secondaire "+ Ajouter une variante" + lien "Générer à partir des options →"

### 8. Référencement (SEO)
- **Aperçu Google en direct** (fond `#fafbfc`, préfixe "APERÇU GOOGLE" en 11px gris)
  - URL : `mon-site.com › portails › {slug}` (couleur #202124, 12px, Arial)
  - Titre : `{seoTitle || title}` en `#1a0dab`, 18px
  - Description : `{seoDesc}` en `#4d5156`, 13px, line-height 1.5
- Titre SEO (input, compteur 60 chars, warn >55, over >60)
- Méta-description (textarea, compteur 160 chars)
- URL slug avec préfixe `mon-site.com/portails/` (addon gauche)
- Grille 2 col : Canonical (placeholder "Auto") | Indexation (select robots)

---

## Sidebar droite (contextuelle, sticky)

### Card 1 — Statut & visibilité
- Header : titre + badge vert "● Publié"
- Select Statut : Publié / Brouillon / Programmé / Archivé
- Switch-row "Visible sur la boutique"
- Switch-row "Mis en avant"
- Input datetime-local "Date de publication"

### Card 2 — Organisation
- Champ **Catégories** : chips bleues (`background: #eff6ff; color: #2563eb`), × au survol, input de recherche à la fin
- Select **Marque**
- Champ **Tags** : chips grises, même UX

### Card 3 — Produits liés
- Liste : thumbnail 40×40 + nom + SKU mono 11.5px + × à droite
- Bouton secondaire "+ Lier un produit"

### Card 4 — Dernières modifications
- Liste d'items : point bleu 8px + texte + meta `user · when` 12px gris
- Lien "Détails" à droite

---

## Chrome (sidebar + topbar + breadcrumb + page head + sticky footer)

### Sidebar gauche (232px, fond blanc, border-right `#e5e7eb`)
- Logo "Lunar" avec dot dégradé conique bleu
- Sections : Catalogue / Storefront / Commandes / Marketing (labels 11px, uppercase-like, color `#9ca3af`)
- Items : 13.5px, padding `7px 18px`, icône 15px à gauche
- Item actif : `background: #eff6ff; color: #2563eb; border-left: 2px solid #2563eb; font-weight: 500`
- Badge compteur (ex: Commandes `1`) : rond bleu, texte blanc 10px

### Topbar (56px, sticky, border-bottom)
- Search input (300px, icône loupe en position absolue, focus bleu)
- Avatar 32px rond gris avec initiale

### Breadcrumb
- 13px, couleur `#6b7280`, séparateurs `›` en `#9ca3af`, dernier élément `#111827`

### Page head
- H1 22px, 600, letter-spacing -0.01em, couleur `#0f172a`
- Sous-titre 13px gris : SKU (mono) · Modifié il y a X par Y
- Actions à droite : Aperçu (secondaire) | Dupliquer (secondaire) | ⋯ | séparateur | Enregistrer (primaire bleu)

### Sticky footer actions
- Bord haut + shadow subtile, fond blanc
- Gauche : indicateur autosave ("✓ Toutes les modifications sont enregistrées" vert)
- Droite : Annuler | Enregistrer comme brouillon | Enregistrer et publier (primaire)

---

## Design Tokens

### Couleurs
| Token                | Valeur      | Usage                                          |
|----------------------|-------------|------------------------------------------------|
| `--bg`               | `#f5f6f8`   | Fond de page                                   |
| `--panel`            | `#ffffff`   | Cards, inputs                                  |
| `--border`           | `#e5e7eb`   | Borders légers (cards, séparateurs)            |
| `--border-strong`    | `#d1d5db`   | Borders inputs                                 |
| `--text`             | `#111827`   | Texte principal                                |
| `--text-muted`       | `#6b7280`   | Texte secondaire, help                         |
| `--text-soft`        | `#9ca3af`   | Placeholders, icônes discrètes                 |
| `--blue`             | `#2563eb`   | Accent principal (boutons, liens, actif)       |
| `--blue-hover`       | `#1d4ed8`   | Hover btn-primary                              |
| `--blue-soft`        | `#eff6ff`   | Fond item actif, chip bleu                     |
| `--red`              | `#dc2626`   | Erreurs, suppressions                          |
| `--red-soft`         | `#fef2f2`   | Fond hover btn-danger                          |
| `--green`            | `#16a34a`   | Succès, marge positive                         |
| `--green-soft`       | `#f0fdf4`   | Fond badge vert                                |
| `--amber`            | `#d97706`   | Warnings compteurs SEO                         |
| `--amber-soft`       | `#fffbeb`   | Fond badge amber                               |

### Typographie
- Font family : `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif`
- Mono : `ui-monospace, SFMono-Regular, Menlo, monospace`
- Base : 14px / line-height 1.5
- H1 page : 22px / 600
- H3 card : 14px / 600
- Labels form : 12.5px / 500
- Help text : 12px / 400 / muted
- Inputs : 14px

### Espacements
- Card radius : 6px
- Card padding body : 18px
- Cards gap entre elles : 14-20px
- Stack gap : 14px
- Stack-sm gap : 8px
- Input padding : 7px 10px
- Button padding : 7px 14px
- Button radius : 6px

### Ombres
- Card : `0 1px 2px rgba(0,0,0,0.04)`
- Tweaks panel : `0 10px 30px rgba(15,23,42,.12)`
- Sticky footer : `0 -2px 8px rgba(0,0,0,.04)`

---

## Comportements & interactions

- **Autosave** : debounce ~1.2s après édition, indicateur bas de page passe de "Enregistrement…" à "✓ Toutes les modifications sont enregistrées"
- **Compteurs SEO** : passe en amber >55 (titre) / >150 (desc), rouge au-delà de 60/160
- **Aperçu Google** : re-render en live au moindre changement de titre/desc/slug
- **Chips** : × retire l'item. Input en fin de zone permet ajout. Autocomplete côté API.
- **Matrice variantes** : inputs invisibles par défaut, apparaissent au hover (border) et focus (border bleu + shadow)
- **Image tiles** : × apparaît au hover (opacity 0 → 1)
- **Drag-drop images** : réordonner à implémenter (SortableJS ou équivalent)
- **Upload area** : drop zone classique, pattern diagonal en placeholder

### États inputs
- Default : `border: 1px solid #d1d5db`
- Focus : `border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15)`
- Erreur : texte rouge 12px sous le champ

### Switch
- Taille 34×20, dot 16×16
- Off : `background: #d1d5db`
- On : `background: #2563eb`, dot translaté de 14px

---

## State à gérer

```ts
type ProductForm = {
  // général
  title: string; sku: string; ean: string;
  shortDesc: string; longDesc: string; // HTML

  // pricing
  price: string; comparePrice: string; cost: string;
  tax: 'TVA 20%' | 'TVA 10%' | 'TVA 5,5%' | 'TVA 0%';
  tierPricing: Array<{ group: string; minQty: number; price: number; discountPct: number }>;

  // stock
  trackStock: boolean; stock: number;
  lowStock: number; safetyStock: number;
  allowBackorder: boolean; leadTime: string;

  // expédition
  weight: string; shippingClass: string;
  dims: { L: number; l: number; H: number };

  // status
  status: 'published' | 'draft' | 'scheduled' | 'archived';
  visible: boolean; featured: boolean; publishAt: string;

  // organisation
  categories: string[]; brand: string; tags: string[];
  attributes: Array<{ key: string; value: string }>;

  // SEO
  seoTitle: string; seoDesc: string;
  slug: string; canonical: string;
  robots: 'index,follow' | 'noindex,follow' | 'noindex,nofollow';

  // médias & relations
  images: Array<{ id: string; url: string; primary: boolean; alt: string }>;
  relatedProductIds: string[];
  variants: Array<{ id: string; name: string; sku: string; price: string; stock: number; active: boolean }>;
};
```

---

## Fichiers inclus

- **`product-edit-tailwind.html`** ⭐ — **Fichier principal de référence**. HTML pur + Tailwind (CDN), aucun JSX, aucune dépendance React. Le développeur peut l'ouvrir directement et copier les classes Tailwind dans ses composants.
- `screenshot.png` — capture du rendu final
- `Product Edit Page.html` + `styles.css` + `chrome.jsx` + `sections.jsx` + `layouts.jsx` — version React d'origine, gardée comme référence secondaire (logique d'état, compteurs SEO dynamiques, etc.)

> Pour l'implémentation, se baser sur **`product-edit-tailwind.html`**. Les fichiers JSX sont fournis uniquement pour comprendre les comportements dynamiques (autosave, compteurs, aperçu Google live).

---

## Assets

Aucun asset externe. Toutes les icônes sont inline SVG (voir `Icon` dans `chrome.jsx`) — à remplacer par la lib d'icônes du projet (lucide-react, heroicons ou équivalent). Les placeholders images sont des patterns CSS.

---

## Notes pour l'intégration

1. **Mapper les classes CSS aux utilitaires Tailwind** — les tokens sont déjà proches du système Tailwind par défaut (`gray-200`, `blue-600`, etc.). La plupart des couleurs correspondent directement.
2. **Remplacer les `<Icon>` maison** par les icônes du projet.
3. **Brancher les APIs** : liste catégories (autocomplete), liste produits pour cross-sell, upload images, sauvegarde produit.
4. **Validation** : titre + SKU + prix + classe de taxe sont required. Les autres champs sont optionnels.
5. **Permissions** : prévoir show/hide des sections Tarification B2B et Historique selon le rôle (admin vs éditeur).
6. **I18N** : toutes les chaînes en français, à externaliser dans le système i18n du projet.
