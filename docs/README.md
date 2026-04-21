# Documentation technique — ecom-laravel

Référence technique du back-office Laravel 11 + Lunar 1.x + Filament 3 (MDE Distribution, rebranding Publiko). Ce dossier remplace l'ancien `technical-choices.md` monolithique (1789 lignes) par une arborescence thématique.

## Point d'entrée — architecture

| Doc | Contenu |
|---|---|
| [architecture.md](architecture.md) | Stack principale, environnement Docker, mécanismes d'extension Lunar, gotchas Lunar, arborescence clé, ce qu'il faut éviter |
| [packages-architecture.md](packages-architecture.md) | Path repositories composer, foundation media-core, i18n minimal, gotcha Resource swap, checklist nouveau package |
| [admin.md](admin.md) | Navigation Filament, page d'édition produit unifiée, liste produits, sticky footer, global search |
| [workflow.md](workflow.md) | Tests, Git, outils IA (MCP servers), RBAC |
| [payments.md](payments.md) | Stripe, rejet Cashier |
| [shipping.md](shipping.md) | Drivers Chronopost / Colissimo / table-rate |

## Packages PKO

Chaque package `packages/pko/*` a sa propre doc sous [packages/](packages/) :

### Catalogue

- [catalog-features.md](packages/catalog-features.md) — caractéristiques produit structurées + façade Features
- [product-types.md](packages/product-types.md) — arbitrage Product Types Lunar vs Caractéristiques custom
- [tree-manager.md](packages/tree-manager.md) — page admin unifiée catégories + caractéristiques
- [product-videos.md](packages/product-videos.md) — vidéos multi-provider (YouTube, Vimeo, Dailymotion, MP4)
- [product-documents.md](packages/product-documents.md) — documents téléchargeables par catégorie

### CMS & storefront

- [media-core.md](packages/media-core.md) — foundation média (table `pko_mediables`, trait, MediaPicker)
- [page-builder.md](packages/page-builder.md) — mini builder JSON sections/colonnes/blocs
- [storefront-cms.md](packages/storefront-cms.md) — CMS unifié multi-post-type + brand pages + facets + sanitization
- [storefront.md](packages/storefront.md) — frontoffice Livewire phase 1
- [storefront-b2b.md](packages/storefront-b2b.md) — frontoffice B2B pro phase 2

### IA

- [ai-core.md](packages/ai-core.md) — providers LLM universels (Claude, OpenAI)
- [ai-filament.md](packages/ai-filament.md) — actions Filament IA réutilisables
- [ai-importer.md](packages/ai-importer.md) — pipeline import Excel multi-feuilles

### Autres

- [loyalty.md](packages/loyalty.md) — fidélité B2B
- [api-platform.md](packages/api-platform.md) — API Platform 4.3 (lecture seule, auth staff)

## Guides utilisateur et plans existants

- [ai-importer-migration-plan.md](ai-importer-migration-plan.md)
- [ai-importer-user-guide.md](ai-importer-user-guide.md)
- [product-edit-unified-page.md](product-edit-unified-page.md)

## Convention

Cette documentation est **la référence maître**. Tout changement d'architecture / nouvelle dépendance / nouvelle règle → mise à jour du fichier concerné dans **le même commit** (cf. `CLAUDE.md §1`).
