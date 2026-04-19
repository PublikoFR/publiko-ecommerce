<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

/**
 * LLM-powered column transform.
 *
 * Phase 1 = wiring + input assembly. Actual API call delegated to LlmManager
 * once ClaudeProvider/OpenAiProvider are implemented in phase 2.
 */
final class LlmTransformAction extends Action
{
    /**
     * @param  array<int, string>  $input_columns
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

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $config = $this->llm_config_id !== null
            ? LlmConfig::query()->find($this->llm_config_id)
            : LlmConfig::default();

        if (! $config || ! $config->active) {
            return $value; // no-op when no usable LLM configured
        }

        $inputs = [];
        foreach ($this->input_columns as $col) {
            $inputs[$col] = $ctx->row[$col] ?? '';
        }
        if ($this->additional_context !== null) {
            $inputs['__context__'] = $this->additional_context;
        }

        $provider = app(LlmManager::class)->forConfig($config);
        $result = $provider->transform($this->prompt, $inputs);

        if ($this->output_format === 'json' && $this->output_json_key !== null) {
            $decoded = json_decode($result, true);

            return is_array($decoded) ? ($decoded[$this->output_json_key] ?? $result) : $result;
        }

        return $result;
    }
}
