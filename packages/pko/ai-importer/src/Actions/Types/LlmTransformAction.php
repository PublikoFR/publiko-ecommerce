<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiCore\Contracts\LlmProviderInterface;
use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * LLM-powered column transform.
 *
 * Les `input_columns` sont des CLÉS DE SORTIE du mapping : la valeur lue est
 * celle déjà calculée pour cette ligne (`ExecutionContext::getOutput`), avec
 * repli sur la colonne brute du fichier si la clé n'est pas (encore) mappée.
 * `ParseFileToStagingJob` calcule les colonnes IA après les autres
 * (`Support\MappingOrder`) pour que ces valeurs existent.
 *
 * Assemble les colonnes d'entrée + le contexte global, puis délègue l'appel à
 * `LlmManager::forConfig()` (providers ClaudeProvider / OpenAiProvider tous deux
 * opérationnels). No-op sûr quand aucun `LlmConfig` actif n'est résolu (table
 * vide en dev/test) : `execute()` renvoie la valeur source sans toucher au
 * réseau.
 *
 * La configuration LLM et le provider sont résolus UNE fois par instance
 * d'action (et la config mise en cache par identifiant pour la durée du
 * process) : sans cela, chaque ligne parsée coûtait une requête SQL et une
 * instanciation de provider par colonne IA.
 *
 * ⚠️ Coûts API : sur une config active, chaque ligne parsée déclenche un VRAI
 * appel facturable. Ne jamais lancer un parse réel d'une config contenant des
 * actions `llm_transform` sans budget maîtrisé (cf. docs/packages/ai-importer.md).
 */
final class LlmTransformAction extends Action
{
    /** @var array<string, LlmConfig|null> Cache process : clé `id:<n>` ou `default`. */
    private static array $configCache = [];

    private ?LlmProviderInterface $provider = null;

    private bool $resolved = false;

    /**
     * @param  array<int, string|array<string, mixed>>  $input_columns
     */
    public function __construct(
        public readonly ?int $llm_config_id = null,
        public readonly string $prompt = '',
        public readonly array $input_columns = [],
        public readonly ?string $additional_context = null,
        public readonly string $output_format = 'string', // string|json
        public readonly ?string $output_json_key = null,
    ) {}

    public static function type(): string
    {
        return 'llm_transform';
    }

    /** À appeler quand une LlmConfig change en cours de process (tests, admin). */
    public static function flushConfigCache(): void
    {
        self::$configCache = [];
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $provider = $this->provider();
        if ($provider === null) {
            return $value; // no-op when no usable LLM configured
        }

        $inputs = [];
        foreach ($this->input_columns as $col) {
            // Tolère le format PrestaShop `{col, label}` en plus des chaînes plates.
            if (is_array($col)) {
                $col = $col['col'] ?? reset($col);
            }
            $col = is_scalar($col) ? (string) $col : '';
            if ($col === '') {
                continue;
            }
            $inputs[$col] = self::scalar($ctx->getOutput($col, $ctx->row[$col] ?? ''));
        }
        if ($this->additional_context !== null && trim($this->additional_context) !== '') {
            $inputs['__context__'] = $this->additional_context;
        }

        // Contexte global (section `ai` de la config) injecté dans tous les appels
        // LLM en bloc système, optionnellement mis en cache (Anthropic prompt caching).
        $cfg = $ctx->job->config?->config_data;
        $aiConfig = $cfg instanceof \ArrayObject ? (array) ($cfg->getArrayCopy()['ai'] ?? []) : [];
        $globalContext = trim((string) ($aiConfig['global_context'] ?? ''));
        $useCache = (bool) ($aiConfig['context_cache'] ?? false);

        $options = [];
        if ($globalContext !== '') {
            $options['system'] = $globalContext;
            $options['cache_system'] = $useCache;
        }

        $result = $provider->transform($this->prompt, $inputs, $options);

        if ($this->output_format === 'json') {
            $decoded = json_decode(self::stripCodeFences($result), true);
            if (! is_array($decoded)) {
                return $result;
            }
            if ($this->output_json_key === null || $this->output_json_key === '') {
                return $decoded;
            }

            return $decoded[$this->output_json_key] ?? $result;
        }

        return $result;
    }

    private function provider(): ?LlmProviderInterface
    {
        if ($this->resolved) {
            return $this->provider;
        }
        $this->resolved = true;

        $config = $this->config();
        if (! $config || ! $config->active) {
            return null;
        }

        return $this->provider = app(LlmManager::class)->forConfig($config);
    }

    private function config(): ?LlmConfig
    {
        $key = $this->llm_config_id !== null && $this->llm_config_id > 0
            ? 'id:'.$this->llm_config_id
            : 'default';

        if (! array_key_exists($key, self::$configCache)) {
            self::$configCache[$key] = $key === 'default'
                ? LlmConfig::default()
                : LlmConfig::query()->find($this->llm_config_id);
        }

        return self::$configCache[$key];
    }

    /**
     * Valeur transmissible au modèle : les sorties structurées (hash
     * `features`, liste d'URLs) sont sérialisées en JSON, le reste en chaîne.
     */
    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Les modèles entourent volontiers un JSON de ```json … ``` malgré la
     * consigne : on retire la clôture avant de décoder.
     */
    private static function stripCodeFences(string $text): string
    {
        $t = trim($text);
        if (preg_match('/^```[a-zA-Z0-9_-]*\s*\n?(.*?)\n?```$/s', $t, $m)) {
            return trim($m[1]);
        }

        return $t;
    }
}
