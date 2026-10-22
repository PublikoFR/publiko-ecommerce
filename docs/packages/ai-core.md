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


## Désignation portable d'un modèle : fournisseur + alias

`LlmManager::forBackend($provider, $model)` / `resolveBackend()` instancient un provider sans id de configuration : la clé API vient de la `LlmConfig` active du fournisseur (`is_default` d'abord), `$model` accepte un alias de famille (`opus`, `sonnet`, `haiku`, `fable`) ou un identifiant exact (vide = modèle de la config). Aucune config active → `LlmUnavailableException` (message affichable).

`ModelAliasResolver` traduit l'alias : l'API Anthropic n'accepte que des identifiants (`claude-sonnet-5-5`), pas `sonnet`. Il lit `GET /v1/models`, retient le plus récent de la famille (`created_at`), cache `ai-core.llm.aliases.cache_ttl` (24 h) ; table de repli `ai-core.llm.aliases.claude.fallback` si l'API ne répond pas — **à mettre à jour à chaque génération**. Revers assumé : l'alias suit automatiquement une nouvelle génération, qui peut refuser un paramètre ; les features qui l'utilisent doivent faire un appel de test avant un traitement de masse (cf. `AiPreflight` dans ai-importer).
