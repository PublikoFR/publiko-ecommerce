# `pko/lunar-admin-nav` — Réorganisation centralisée du menu admin Filament

## Rôle

Package dédié à la hiérarchie complète du menu latéral Filament admin.

Redéfinit :
- Tous les groupes et leur ordre, via `NavigationBuilder`
- Des **raccourcis Pilotage** (sans label de groupe, rendus en tête de sidebar) qui dupliquent volontairement les Resources les plus consultées
- Deux **hub pages** à onglets pour dé-peupler le menu : `LoyaltyHub` (`/admin/fidelite`) et `HomepageHub` (`/admin/page-accueil`)

## Structure du menu

```
[Pilotage — sans label]
  Tableau de bord      → Dashboard
  Commandes [badge]    → OrderResource
  Expédition           → CarrierShipmentResource
  Clients              → CustomerResource

[Catalogue]
  Produits, Marques, Catégories (CollectionResource), Caractéristiques

[Paramètres catalogue] (collapsed par défaut)
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
  Devises, Zones fiscales, Classes fiscales, Taux de TVA,
  Stripe, Chronopost, Colissimo
```

**Note Filament** : Filament 3 ne supporte pas les sous-groupes imbriqués persistants côté sidebar (seul `childItems` existe et ne s'affiche qu'au survol actif). La hiérarchie visuelle de la section Configuration est donc matérialisée par **4 groupes collapsed consécutifs** (Général / Imports / Boutique / Paiement & Expédition) plutôt qu'un unique groupe Configuration avec sous-sections.

## Mécanisme

`AdminNavPlugin::register()` pose `$panel->navigation(fn (NavigationBuilder $b) => Builder::build($b))`. Cette injection **remplace** l'auto-collection des NavigationItems par Filament : seules les entrées explicitement ajoutées par `Builder::build()` apparaissent dans le menu.

Conséquences :
- Les Resources restent enregistrées (leurs URLs continuent de répondre)
- Les entrées non listées par le Builder sont invisibles au menu mais accessibles en direct (ex: `admin/loyalty-tiers`, `admin/home-slides`)
- Le Builder ignore silencieusement toute Resource dont la classe est absente (package optionnel désinstallé) via `class_exists()`

## Raccourcis Pilotage

Chaque raccourci est un `NavigationItem::make()->url(XxxResource::getUrl())` avec `isActiveWhen` calqué sur la route native. Comportement voulu : le raccourci **et** l'entrée dans son groupe d'origine s'allument simultanément pour la même URL.

Les quatre Resources ciblées (Dashboard, Orders, CarrierShipments, Customers) **n'apparaissent QUE dans Pilotage** — elles sont exclues des groupes principaux pour éviter une triple entrée.

## Hub pages

### `LoyaltyHub` (`admin/fidelite`)

- 4 onglets : Paliers, Cadeaux débloqués, Historique des points, Configuration
- Tab actif persisté via query string `?tab=paliers` (Livewire `#[Url]`)
- Les 3 premiers onglets = `TableWidget` (Filament) qui réutilisent `Resource::table()` des Resources natives → zéro duplication de schéma
- Le 4e onglet embarque le formulaire de `LoyaltySettings` (ratio points, email admin) inline

### `HomepageHub` (`admin/page-accueil`)

- 3 onglets : Slides, Tuiles, Offres
- Même pattern `TableWidget` réutilisant `HomeSlideResource::table` / `HomeTileResource::table` / `HomeOfferResource::table`

**Navigation entre onglets** : Livewire partial render (pas de rechargement de page complet, query string mise à jour, back-button navigateur fonctionnel).

**Actions de création** : bouton "Nouveau" dans chaque header de tableau qui redirige vers l'URL native `XxxResource::getUrl('create')` (pas de modal inline — les formulaires de création/édition restent sur leurs pages dédiées).

## Désactivation

Pour retirer la réorganisation et revenir au menu Filament natif : supprimer `->plugin(AdminNavPlugin::make())` dans `AppServiceProvider::panel()` et retirer `pko/lunar-admin-nav` des `require`.

## Extension

Pour ajouter une nouvelle Resource au menu : éditer `Builder::build()` et insérer `...self::navItems(NouvelleResource::class, sort: N)` dans le sous-array du groupe cible. Si le Resource vit dans un package optionnel, ajouter la dépendance `@dev` dans `composer.json` du package.

Pour un nouveau hub à onglets : créer une Page sous `src/Filament/Pages/`, des `TableWidget` sous `src/Filament/Widgets/`, et enregistrer la Page dans `AdminNavPlugin::register()->pages([...])`.
