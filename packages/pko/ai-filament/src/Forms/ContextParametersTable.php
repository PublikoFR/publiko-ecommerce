<?php

declare(strict_types=1);

namespace Pko\AiFilament\Forms;

use Filament\Forms\Components\Field;

/**
 * Filament form field : renders a 3-column table (checkbox, label, live value)
 * listing every context property a GenerateAiAction will inject into the LLM
 * prompt. The state is an associative array ['property' => bool] bound to
 * each checkbox.
 */
final class ContextParametersTable extends Field
{
    protected string $view = 'ai-filament::forms.context-parameters-table';

    /** @var array<string, string> */
    protected array $rows = [];

    /**
     * @param  array<string, string>  $rows  [livewire property name => human label]
     */
    public function rows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
