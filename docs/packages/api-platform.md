# pko/lunar-api (API Platform 4.3)

`api-platform/laravel:^4.3` installé (v4.3.3 compatible Laravel 11.51). Config `config/api-platform.php` publié, `resources[]` étendu aux paths `packages/pko/*/src/Models`.

### Ressources exposées (`#[ApiResource(operations: [GetCollection, Get])]` — lecture seule)

- Storefront CMS : `Post`, `PostType`, `BrandPage`, `HomeSlide`, `HomeTile`, `HomeOffer`
- Catalog : `FeatureFamily`, `FeatureValue`
- Produit : `ProductDocument`, `DocumentCategory`, `ProductVideo`
- Store locator : `Store`

31 routes générées sous `/api/*` (JSON-LD, OpenAPI, Hydra). Docs auto à `GET /api/docs`.

### MCP

Laravel Boost MCP (déjà présent) couvre l'introspection DB live. Le bridge MCP dédié API Platform (config `mcp.enabled = true` dans api-platform.php) est disponible mais non exposé via une route dédiée en v4.3 — découverte via les endpoints JSON-LD standards.

**Pas d'écriture** : v1 strictement lecture, aucun risque de mutation.

### Sécurité — middleware auth + global scopes defense-in-depth

`config/api-platform.php` → `routes.middleware = ['auth:staff']`. Les routes `/api/*` exigent une session staff Filament. Pour exposer une resource publiquement, retirer le middleware et mettre `security: "..."` sur le `#[ApiResource]` concerné.

**Global scopes anti-fuite** sur `Post`, `HomeSlide`, `HomeTile`, `HomeOffer` (tous modèles CMS temporels) :

```php
protected static function booted(): void
{
    static::addGlobalScope('pko_api_published_only', function (Builder $query): void {
        if (app()->runningInConsole()) return;
        if (request()->is('api/*')) {
            $query->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
        }
    });
}
```

Raison : le provider par défaut d'API Platform n'applique PAS les scopes locaux (`scopePublished`, `scopeActive`). Sans ce garde, `GET /api/posts` leakerait drafts + scheduled posts. Le scope ne s'active QUE sur `/api/*` — zéro impact sur Filament admin.

