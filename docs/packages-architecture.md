# Architecture packages — path repositories OSS-ready

Comment sont organisés les 20 packages PKO sous `packages/pko/*`, la foundation `pko/lunar-media-core`, et la checklist pour créer un nouveau package.

## Architecture packages OSS-ready — path repositories

Tous les modules custom `packages/pko/*` sont des packages composer-installables via **path repositories** (pas encore Packagist, mais architecture prête).

### Mécanique

- `composer.json` racine : `repositories[type=path, url="packages/pko/*", options.symlink=true]` + chaque package listé dans `require: "pko/lunar-<x>": "@dev"`.
- Chaque package a son propre `composer.json` avec `name`, `autoload.psr-4`, et **impérativement** `extra.laravel.providers` pour auto-discovery.
- `bootstrap/providers.php` ne contient plus que `AppServiceProvider` — tous les providers PKO sont auto-découverts.
- Pas d'entrée Pko\* dans `autoload.psr-4` du root composer.json — chaque package gère son propre autoload.

Résultat : `composer update` crée 20 symlinks `vendor/pko/lunar-<x>` → `packages/pko/<x>`. Laravel 11 package discovery enregistre les providers sans intervention.

### Foundation `pko/lunar-media-core`

Package foundation extrait contenant :
- Migration `pko_mediables` (pivot polymorphique)
- Trait `Pko\LunarMediaCore\Concerns\HasMediaAttachments` (méthodes `mediaAttachments`, `firstMedia`, `syncMediaAttachments`, etc.)
- Pivot model `Pko\LunarMediaCore\Models\Mediable`
- Composant Filament `Pko\LunarMediaCore\Filament\Forms\Components\MediaPicker` (class + view `media-core::forms.components.media-picker`)

Toute nouvelle fonctionnalité attachant des médias à un modèle **doit** déclarer `"pko/lunar-media-core": "@dev"` dans son composer.json et utiliser le trait + picker. Ne pas recréer de pivot polymorphique.

### i18n minimal

Chaque package Filament expose ses labels de navigation via `lang/fr/admin.php` + `loadTranslationsFrom()` dans son ServiceProvider + publie via `publishes([...lang], 'pko-<pkg>-lang')`. Les Filament Resources wrappent `navigationLabel/modelLabel/pluralModelLabel` dans `__('pko-<pkg>::admin.<key>')`.

Scope réduit : les ~300 `->label('...')` des form fields NE sont PAS touchés (déféré jusqu'à décision OSS formelle).

### Cross-dependencies

Déclarées dans `require` de chaque composer.json. Exemples :
- `pko/lunar-storefront-cms` → `pko/lunar-media-core` + `pko/lunar-page-builder`
- `pko/lunar-product-documents` → `pko/lunar-media-core`
- `pko/lunar-shipping-chronopost` → `pko/lunar-shipping-common`
- `pko/lunar-ai-filament` → `pko/lunar-ai-core`
- `pko/lunar-ai-importer` → `pko/lunar-ai-core` + `pko/lunar-catalog-features` + `pko/lunar-product-videos`

Ordre de boot des providers : Composer résout dependencies-first via `installed.json` — media-core boot avant storefront-cms, etc.

### Gotcha — Lunar Resource pages avec `$resource` hardcodé

Les pages par défaut de Lunar (ex: `Lunar\Admin\...\ListProductTypes`) déclarent `protected static string $resource = ProductTypeResource::class` **en dur**. Quand on subclass (`PkoProductTypeResource extends ProductTypeResource`), la list page utilise toujours `ProductTypeResource::class` pour générer les URLs edit/create → route inexistante après le slug swap (`/admin/pko-product-types` au lieu de `/admin/product-types`) → `RouteNotFoundException`.

**Correctif obligatoire** sur toute Resource swappée : créer des sous-classes `PkoList<X>`, `PkoCreate<X>`, `PkoEdit<X>` qui redéclarent `$resource = Pko<X>Resource::class`, et les enregistrer via `getDefaultPages()`. Pattern appliqué à :
- `PkoProductResource` (page Livewire custom `EditProductUnified`)
- `PkoProductTypeResource`
- `PkoProductOptionResource`
- `PkoAttributeGroupResource`
- `PkoCollectionGroupResource`

### Ce qui reste différé (décision OSS formelle requise)

- Publication sur Packagist (rename `publiko/*` optionnel, `git tag v1.0.0`, GitHub Action de release)
- Traductions EN (`lang/en/`)
- Tests OSS-grade avec coverage publique
- Les ~300 `->label('...')` form fields non wrappés
- Traduction des commentaires/README en anglais

### Création d'un nouveau package — checklist

Voir `CLAUDE.md §3.2` pour la checklist complète. En résumé :
1. `packages/pko/<feature-kebab>/` avec composer.json + README.md + ServiceProvider
2. Ajouter `"pko/lunar-<feature>": "@dev"` dans le root composer.json require (pas d'autoload)
3. Si médias attachés : require `pko/lunar-media-core` + trait HasMediaAttachments
4. Si Resource extends Lunar : override `getDefaultPages()` avec sous-classes
5. Labels admin wrappés `__()` + `lang/fr/admin.php`

