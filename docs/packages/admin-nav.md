# `pko/lunar-admin-nav` — Réorganisation centralisée du menu admin Filament

## Rôle

Package dédié à la hiérarchie complète du menu latéral Filament admin + sub-navigation on-page pour certaines sections transverses (Expédition, Taxes).

Redéfinit :
- Tous les groupes et leur ordre, via `NavigationBuilder`
- Des **raccourcis Pilotage** (sans label de groupe, rendus en tête de sidebar) qui dupliquent volontairement les Resources les plus consultées
- Deux **hub pages** à onglets pour dé-peupler le menu : `LoyaltyHub` (`/admin/fidelite`) et `HomepageHub` (`/admin/page-accueil`)
- Une **sub-navigation on-page à droite** pour les pages Expédition (6 items : Méthodes / Zones / Exclusion / Envois transporteurs / Chronopost / Colissimo) et Taxes (Cluster Lunar, 3 items : Zones / Classes / Taux)

## Structure du menu

```
[Pilotage — sans label]
  Tableau de bord      → Dashboard
  Commandes [badge]    → OrderResource
  Expédition           → PkoShippingMethodResource (+ sub-nav on-page 6 items)
  Clients              → CustomerResource

[Catalogue]
  Produits, Médiathèque, Marques, Catégories, Caractéristiques

[Paramètres catalogue] (collapsed)
  Types de produits, Options de produits, Groupes d'attributs,
  Groupes de collections, Catégories de documents, Tags

[Ventes & Clients]
  Groupes de clients, Réductions, Abonnés newsletter, Fidélité (hub)

[Contenu]
  Page d'accueil (hub), Contenus (PostResource), Types de contenus

[Général] (collapsed)
  Personnel, Rôles, Configurations LLM

[Imports et Données] (collapsed)
  Imports, Configurations d'import, Activités

[Boutique] (collapsed)
  Paramètres storefront, Magasins, Canaux, Langues

[Paiement & Expédition] (collapsed)
  Devises, Taxes (Cluster, 1 entrée → sub-nav on-page), Stripe
```

**Note Filament** : Filament 3 ne supporte pas les sous-groupes imbriqués persistants côté sidebar (seul `childItems` existe et ne s'affiche qu'au survol actif). La hiérarchie visuelle de la section Configuration est matérialisée par **4 groupes collapsed consécutifs** (Général / Imports / Boutique / Paiement & Expédition) plutôt qu'un unique groupe Configuration avec sous-sections.

## Mécanisme principal — NavigationBuilder

`AdminNavPlugin::register()` pose `$panel->navigation(fn (NavigationBuilder $b) => Builder::build($b))`. Cette injection **remplace** l'auto-collection des NavigationItems par Filament : seules les entrées explicitement ajoutées par `Builder::build()` apparaissent dans le menu.

Conséquences :
- Les Resources restent enregistrées (leurs URLs continuent de répondre)
- Les entrées non listées par le Builder sont invisibles au menu mais accessibles en direct (ex: `admin/loyalty-tiers`, `admin/home-slides`)
- Le Builder ignore silencieusement toute Resource dont la classe est absente (package optionnel désinstallé) via `class_exists()`

## Raccourcis Pilotage

Chaque raccourci est un `NavigationItem::make()->url(XxxResource::getUrl())` avec `isActiveWhen` calqué sur la route native. Les 4 Resources ciblées (Dashboard, Orders, Shipping, Customers) **n'apparaissent QUE dans Pilotage** — elles sont exclues des groupes principaux pour éviter une double entrée.

## Hub pages à onglets

### `LoyaltyHub` (`admin/fidelite`)
- 4 onglets : Paliers, Cadeaux débloqués, Historique des points, Configuration
- Tab actif persisté via query string `?tab=paliers` (Livewire `#[Url]`)
- Les 3 premiers onglets = `TableWidget` (Filament) qui réutilisent `Resource::table()` des Resources natives → zéro duplication de schéma
- Le 4e onglet embarque le formulaire de `LoyaltySettings` (ratio points, email admin) inline

### `HomepageHub` (`admin/page-accueil`)
- 3 onglets : Slides, Tuiles, Offres — même pattern `TableWidget`

**Navigation entre onglets** : Livewire partial render, pas de rechargement full page, query string mise à jour, back-button fonctionnel.

**Actions de création** : bouton "Nouveau" redirige vers l'URL native `XxxResource::getUrl('create')` (pas de modal inline).

## Sub-navigation on-page (droite)

Deux mécanismes Filament natifs utilisés :

### Section Expédition — `$subNavigationPosition + getSubNavigation()`

6 Resources/Pages partagent une sub-nav à droite reliant **Méthodes / Zones / Exclusion / Envois transporteurs / Chronopost / Colissimo** — 3 Resources sont dans Lunar (`lunar-shipping`), 1 dans `pko/shipping-common`, 2 dans `pko/shipping-chronopost`/`colissimo`.

- **Pour les 3 Resources Lunar** : swap Panel-level via reflection dans `AdminNavPlugin::swapShippingResources()` qui remplace `ShippingMethodResource` → `PkoShippingMethodResource` (et 2 autres). Chaque Pko* subclass déclare `$subNavigationPosition = SubNavigationPosition::End` et override `getDefaultPages()` pour pointer vers des List Pages Pko qui override `getSubNavigation()` via `ShippingSubNavigation::items()`.
- **Pour les 3 Pages/Resources Pko** : modification directe du code (packages `pko/shipping-*` sont sous contrôle direct) — ajout de `$subNavigationPosition`, `shouldRegisterNavigation = false` et `getSubNavigation()`.

### Section Taxes — Cluster Filament

Lunar déclare déjà un `Lunar\Admin\Filament\Clusters\Taxes` (Cluster Filament 3 natif) regroupant TaxZone / TaxClass / TaxRate sous l'URL `/admin/taxes/...`. Limitations :
- Le Cluster Lunar a `$subNavigationPosition = Start` (sub-nav à gauche)
- Les 3 Resources du cluster n'ont pas non plus de `$subNavigationPosition`, donc fallback à `Start`

Swap appliqué :
- Cluster `Taxes` → `PkoTaxesCluster` (subclass, `$subNavigationPosition = End`) via reflection sur `Panel::$clusters` dans `AdminNavPlugin::swapTaxesCluster()`
- Les 3 Resources `TaxZoneResource`, `TaxClassResource`, `TaxRateResource` → `Pko*` subclasses avec `$subNavigationPosition = End` + `$cluster = PkoTaxesCluster::class` + override de `getDefaultPages()` pointant vers 9 Pko subclass pages (List/Create/Edit × 3), chaque Pko page redéclarant `$resource` vers le Pko Resource.
- Swap des 3 Resources dans `AppServiceProvider::swapLunarResources()` (via reflection sur `LunarPanelManager::$resources`, même pattern que products — Lunar core tax Resources y sont déclarées).

## Classes swappées — tableau récapitulatif

| Lunar original | Pko remplacement | Mécanisme de swap | Fichier déclarant le swap |
|---|---|---|---|
| `ProductResource` | `PkoProductResource` | `LunarPanelManager::$resources` reflection | `AppServiceProvider::swapLunarResources` |
| `ProductTypeResource` | `PkoProductTypeResource` | idem | idem |
| `ProductOptionResource` | `PkoProductOptionResource` | idem | idem |
| `AttributeGroupResource` | `PkoAttributeGroupResource` | idem | idem |
| `CollectionGroupResource` | `PkoCollectionGroupResource` | idem | idem |
| `TaxZoneResource` | `PkoTaxZoneResource` | idem | idem |
| `TaxClassResource` | `PkoTaxClassResource` | idem | idem |
| `TaxRateResource` | `PkoTaxRateResource` | idem | idem |
| `ShippingMethodResource` | `PkoShippingMethodResource` | `Panel::$resources` reflection | `AdminNavPlugin::swapShippingResources` |
| `ShippingZoneResource` | `PkoShippingZoneResource` | idem | idem |
| `ShippingExclusionListResource` | `PkoShippingExclusionListResource` | idem | idem |
| `Taxes` (Cluster) | `PkoTaxesCluster` | `Panel::$clusters` reflection | `AdminNavPlugin::swapTaxesCluster` |

Pourquoi 2 mécanismes différents : les Resources Lunar core sont enregistrées via `LunarPanelManager::$resources` (swappable AVANT `LunarPanel::register()`). Les Resources des plugins Lunar satellites (comme `lunar-shipping`) sont enregistrées directement via `$panel->resources()` au moment où le plugin s'enregistre — il faut donc swapper AU NIVEAU du Panel Filament, APRÈS que ShippingPlugin ait registré ses resources. AdminNavPlugin étant enregistré en dernier dans `AppServiceProvider::panel()`, son `register()` s'exécute après ShippingPlugin.

## Couplage avec Lunar — risques & mitigation

Le swap reflection est fragile par design. **Avant chaque mise à jour Lunar** (`composer update lunarphp/*`) :

1. **Pin la version** : `composer.json` actuel autorise `^1.0` (tout 1.x). Préférer `~1.X.Y` pour bloquer les minors automatiques.
2. **Lire le CHANGELOG** de Lunar avant bump — cherche rename de Resources, Pages, Clusters, namespaces.
3. **Smoke test post-upgrade** — visiter dans le navigateur :
   - `/admin/products` + sub-nav à droite
   - `/admin/shipping-methods` + sub-nav à droite (6 items)
   - `/admin/taxes/tax-zones` + sub-nav à droite (3 items)
   - `/admin/fidelite` et `/admin/page-accueil` (onglets)
   - `/admin` → menu complet avec raccourcis Pilotage
4. **Points de casse connus** :
   - Ajout d'une Page dans `getDefaultPages()` d'une Resource Lunar → ma subclass n'a pas la page → route 404 (corriger en ajoutant l'entrée dans l'override Pko)
   - Rename d'une classe Page → `extends` casse → fatal (corriger le `use` et la classe parente)
   - Rename de propriété `$resources` sur `LunarPanelManager` ou `Panel` → reflection no-op silencieux → pages Lunar d'origine actives (détecté par smoke test)
   - Refactor namespace → tous les `use` cassent

Tests automatisés HTTP sur ces URLs = carte à jouer en suivi.

## Désactivation

Pour retirer la réorganisation et revenir au menu Filament natif :
- Supprimer `->plugin(AdminNavPlugin::make())` dans `AppServiceProvider::panel()`
- Retirer les 3 entries Tax du swap dans `AppServiceProvider::swapLunarResources()`
- Retirer `pko/lunar-admin-nav` des `require` de `composer.json` racine
- `composer update`

## Extension

**Ajouter une Resource au menu** : éditer `Builder::build()` et insérer `...self::navItems(NouvelleResource::class, sort: N)` dans le sous-array du groupe cible.

**Nouveau hub à onglets** : créer une Page sous `src/Filament/Pages/`, des `TableWidget` sous `src/Filament/Widgets/`, enregistrer la Page dans `AdminNavPlugin::register()->pages([...])`.

**Nouvelle sub-navigation on-page** pour un groupe de Resources Lunar : dupliquer le pattern Taxes / Expédition — Pko subclass Resource avec `$subNavigationPosition = End` + Pko subclass pages redéclarant `$resource` + swap via reflection. Si les Resources visées sont dans `LunarPanelManager::$resources`, swap dans `AppServiceProvider`. Sinon (plugins Lunar satellites), swap dans `AdminNavPlugin::register()` au niveau du Panel.
