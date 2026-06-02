# pko/lunar-ai-importer

Pipeline d'import produits Lunar via LLM : Excel multi-feuilles, actions chaînées, staging.

## Installation

```bash
composer require pko/lunar-ai-importer
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-ai-importer-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-ai-core` @dev
- `pko/lunar-catalog-features` @dev
- `pko/lunar-product-videos` @dev
- `lunarphp/lunar` ^1.0
- `phpoffice/phpspreadsheet` ^2.0 || ^3.0

## Compatibilité PrestaShop (module Publiko AI Importer)

Les configs JSON du module PrestaShop d'origine tournent **directement**, sans
réécriture du mapping : `action` singulier lifté en `actions[]`, clés `comment`/`_*`
tolérées, alias `multiply/divide/add/subtract` (→ `math`) et
`uppercase/lowercase/capitalize` (→ `change_case`), `prefix` au niveau colonne,
`category_map`, `conditional`, `concat`/`template` multi-feuilles (`{col, sheet}`),
feuilles jointes (`join_key`/`join_col`, `type_col`), mapping en lettres de colonne.

Détail complet du périmètre + matrice de compat : `docs/packages/ai-importer.md`
(§7.quinquies.15). Non-régression verrouillée par `ImportPsConfigCommandTest` +
`PsConfigParseE2eTest` (chargent les vraies configs PS).

> ⚠️ **Coûts API** — une action `llm_transform` déclenche un **vrai appel facturable
> par ligne** dès qu'un `LlmConfig` est actif. Ne jamais lancer un parse réel d'une
> config contenant des `llm_transform` (ex. `somfy.json`) sans budget maîtrisé. La
> table `pko_llm_configs` vide (dev/test) ⇒ no-op sûr. L'import de config reste
> toujours sûr (insertion DB, zéro appel).

## Licence

Proprietary — Publiko.
