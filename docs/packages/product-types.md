# Product Types Lunar vs Caractéristiques custom — arbitrage

### 7.quater.1 Deux systèmes complémentaires, pas concurrents

Le catalogue repose sur **deux mécanismes orthogonaux** pour décrire un produit. L'un vient de Lunar core, l'autre de notre package. Ne pas les confondre.

| | **Product Type + Attributes (Lunar)** | **Feature Families + Values (custom)** |
|---|---|---|
| **Rôle** | Schéma déclaratif : quels champs un produit de ce type porte | Taxonomie filtrable : valeurs partagées entre produits pour facettes et mega-menu |
| **Stockage** | JSON `attribute_data` sur `lunar_products` (1 valeur par produit) | 4 tables relationnelles `pko_feature_*` avec pivot SQL indexé |
| **Exemples** | Poids (kg), dimensions (mm), puissance (W), description technique | Marque=Somfy, Applications=[résidentiel, copropriété], Matière=aluminium |
| **Indexable SQL** | Non — JSON non-indexable pour `WHERE` multi-critères | Oui — PK composée + index reverse, JOIN O(log n) |
| **Valeur unique par produit ?** | Oui (mesure, texte libre, nombre propre au produit) | Non — liste finie de valeurs réutilisées sur N produits |
| **Filtrage catalogue front** | Impossible sans Scout (Meili/Algolia) | Natif SQL via `Features::productsWith()` et `Features::countsFor()` |
| **Admin back-office** | Fiche produit Lunar Admin (onglet Attributs) | Publiko Tree Manager + onglet Caractéristiques injecté via `ProductFeaturesExtension` |

### 7.quater.2 Règle de décision

Pour chaque info produit à ajouter, poser ces questions dans l'ordre :

1. **La valeur est-elle unique par produit ?** (mesure précise, texte libre, dimension) → **Attribut Lunar** sur le Product Type
2. **Veut-on filtrer le catalogue dessus ?** (facette front, mega-menu, recherche par critère) → **Caractéristique custom** (`FeatureFamily` + `FeatureValue`)
3. **La valeur est-elle réutilisée à l'identique sur plein de produits ?** (marque, norme, couleur catalogue) → **Caractéristique custom**
4. **C'est du texte libre ou une mesure continue ?** → **Attribut Lunar**

Les deux coexistent sans conflit : un produit de type « Portail » peut porter simultanément les attributs `largeur_mm=3200`, `hauteur_mm=1800`, `poids_kg=85` (Lunar) **et** les caractéristiques `marque=Somfy`, `matière=aluminium`, `applications=[résidentiel, copropriété]` (custom).

### 7.quater.3 Product Types Lunar — fonctionnement

- Table `lunar_product_types` (5 types seedés : Portail, Volet roulant, Motorisation, Clôture, Accessoire)
- Un Product Type déclare quels `Attribute` sont rattachés au produit (product-level) et à ses variantes (variant-level)
- Les Attributes sont des `Lunar\Models\Attribute` groupés par `AttributeGroup`, stockés comme `TranslatedText`, `Number`, `Text`, `Dropdown` dans `attribute_data`
- Admin : page « Types de produit » native Lunar Admin (menu Catalogue)
- **Pas encore câblé en prod** : 0 attributs assignés aux 5 types, à configurer quand les fiches produit seront enrichies (phase 2/3)

### 7.quater.4 Variantes Lunar — architecture

- `ProductOption` = axe de variation (Taille, Couleur, Finition)
- `ProductOptionValue` = valeur sur cet axe (S/M/L, Bleu/Rouge)
- `ProductVariant` = combinaison concrète (Bleu/M) → c'est le `Purchasable` qui porte SKU, prix (`lunar_prices`), stock
- Les variantes sont des combinaisons **finies et précalculées** — pas un outil de saisie libre

### 7.quater.5 Configurateur sur mesure (vision phase 3)

Pour les menuiseries sur mesure (le client saisit ses dimensions), les variantes Lunar ne conviennent pas (combinaisons infinies). Architecture envisagée :

- **`CartLine::meta`** — champ JSON cast array, prévu par Lunar pour stocker des données custom par ligne de panier (`{largeur_mm: 1247, hauteur_mm: 832, double_vitrage: true}`)
- **Cart Pipeline** (`CartLineModifier`) — modifier custom qui lit `$line->meta`, applique une formule `largeur × hauteur × tarif_m²`, override `unit_price`
- **Product Type Attributes** pour stocker les paramètres du configurateur (`tarif_m2`, `largeur_min_mm`, `largeur_max_mm`, `pas_mm`)
- **`CartValidator`** custom pour rejeter les `Cart::add()` si dimensions hors bornes
- Package cible : `packages/pko/product-configurator`
- **Pièges** : le Cart Fingerprinting Lunar doit inclure `meta` dans le hash (sinon manipulation post-checkout), et `OrderLine` doit recopier le `meta` au passage `Cart → Order` (vérifié : le pipeline standard le fait)

---

