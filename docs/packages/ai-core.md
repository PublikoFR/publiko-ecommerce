# pko/lunar-ai-core — providers LLM universels

Package `packages/pko/ai-core/` (namespace `Pko\AiCore\`), ServiceProvider : `AiCoreServiceProvider`.

### Rôle

Héberge tout le domaine LLM du back-office : contrats, enums, modèle `LlmConfig`, providers HTTP (Claude, OpenAI) et `LlmManager`. N'a aucune dépendance vers Filament ou Lunar — utilisable par n'importe quel module.

### Points d'entrée

- `LlmManager::make(LlmProviderName, string $apiKey, string $model, array $options = []): LlmProviderInterface` — instanciation directe, sans passer par un `LlmConfig` DB. **À utiliser pour toute nouvelle feature IA.**
- `LlmManager::forConfig(LlmConfig $config): LlmProviderInterface` — sucre autour de `make()` pour `ai-importer`.

### Table DB

`pko_llm_configs` (anciennement `pko_ai_importer_llm_configs`, renommée via migration `2026_04_19_000001`).

### Config

Clé : `ai-core.llm.*` (timeout, retries, backoff, critical HTTP codes). Publiable via `php artisan vendor:publish --tag=ai-core-config`.

### Consommateurs

- `pko/ai-importer` : utilise `forConfig()` via `LlmTransformAction`.
- `pko/ai-filament` : utilise `LlmManager::forConfig()` pour les boutons "Générer avec l'IA".
- `LlmConfigResource` Filament reste dans `pko/ai-importer` (dépendance Filament tenue hors du core).

---

