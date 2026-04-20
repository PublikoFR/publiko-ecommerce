<?php

declare(strict_types=1);

namespace Pko\AiFilament\Actions;

use Closure;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;
use Pko\AiCore\Llm\LlmManager;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiFilament\Forms\ContextParametersTable;

final class GenerateAiAction
{
    /**
     * Generic factory for "Generate with AI" hintActions on any Filament field.
     *
     * Returns 3 hintActions passed to `->hintActions([...])` :
     *   - direct : visible when target is empty, replaces the target on click.
     *   - preview : visible when target has content, opens a preview modal.
     *   - editPrompt : always visible (pencil), opens a modal where the user
     *     can edit the prompt before regenerating and applying.
     *
     * @param  string  $targetField  Form state key to write the AI result into.
     *                               Used with `$set($targetField, $result)`.
     * @param  string  $prompt  System/user prompt sent to the LLM.
     * @param  array<string>  $contextProperties  Livewire public-property names read on the
     *                                            current component and injected as inputs
     *                                            into the LLM call (keys = property names).
     * @param  string|null  $emptyCheckProperty  Livewire property name used to decide
     *                                           "empty → direct" vs "non-empty → preview".
     *                                           Defaults to `$targetField` (works when
     *                                           the Livewire component declares a public
     *                                           property matching the form field name).
     * @param  string|null  $llmConfigName  LlmConfig name ; null = default config.
     * @param  bool  $htmlMode  true = preview renders HTML + toggle
     *                          "mode code". false = plain textarea.
     * @param  string  $label  Button label.
     * @param  string  $icon  Heroicon slug.
     * @param  string  $modalHeading  Heading shown in the preview modal.
     * @param  string  $successTitle  Notification title on direct-replace success.
     * @param  Closure|null  $using  Optional customizer applied to both generated
     *                               actions (direct + preview) after the default
     *                               configuration. Receives the Action and must
     *                               return it. Use it to chain any Filament
     *                               Action API (color, size, outlined, tooltip,
     *                               extraAttributes…).
     * @return array<Action>
     */
    public static function forField(
        string $targetField,
        string $prompt,
        array $contextProperties = [],
        ?string $emptyCheckProperty = null,
        ?string $llmConfigName = null,
        bool $htmlMode = true,
        string $label = 'Générer avec l\'IA',
        string $icon = 'heroicon-o-sparkles',
        string $modalHeading = 'Aperçu — Contenu généré par l\'IA',
        string $successTitle = 'Contenu généré',
        ?Closure $using = null,
    ): array {
        $emptyCheckProperty ??= $targetField;

        $direct = self::buildDirectAction(
            targetField: $targetField,
            prompt: $prompt,
            contextProperties: $contextProperties,
            emptyCheckProperty: $emptyCheckProperty,
            llmConfigName: $llmConfigName,
            label: $label,
            icon: $icon,
            successTitle: $successTitle,
        );

        $preview = self::buildPreviewAction(
            targetField: $targetField,
            prompt: $prompt,
            contextProperties: $contextProperties,
            emptyCheckProperty: $emptyCheckProperty,
            llmConfigName: $llmConfigName,
            htmlMode: $htmlMode,
            label: $label,
            icon: $icon,
            modalHeading: $modalHeading,
        );

        $editPrompt = self::buildEditPromptAction(
            targetField: $targetField,
            prompt: $prompt,
            contextProperties: $contextProperties,
            llmConfigName: $llmConfigName,
            htmlMode: $htmlMode,
            modalHeading: $modalHeading,
        );

        if ($using !== null) {
            $direct = $using($direct) ?? $direct;
            $preview = $using($preview) ?? $preview;
            $editPrompt = $using($editPrompt) ?? $editPrompt;
        }

        return [$direct, $preview, $editPrompt];
    }

    /**
     * Preset : hintActions for the product long-description TiptapEditor.
     *
     * @return array<Action>
     */
    public static function descriptionActions(?string $llmConfigName = null): array
    {
        return self::forField(
            targetField: 'longDesc',
            prompt: 'Tu es un expert en rédaction e-commerce. '
                .'Génère une description HTML détaillée et convaincante pour le produit ci-dessous. '
                .'Utilise des balises <h2>, <p>, <ul>, <li>. '
                .'Langue : français. Axé bénéfices client, ton professionnel. '
                .'Retourne uniquement le HTML brut, sans balises <html>, <head>, <body>, '
                .'et sans bloc de code markdown (pas de ```html ni de ```).',
            contextProperties: [
                'productName',
                'sku',
                'shortDesc',
                'brandContext',
                'collectionsContext',
                'featuresContext',
            ],
            llmConfigName: $llmConfigName,
            htmlMode: true,
            modalHeading: 'Aperçu — Description générée par l\'IA',
            successTitle: 'Description générée',
        );
    }

    /**
     * @param  array<string>  $contextProperties
     */
    private static function buildDirectAction(
        string $targetField,
        string $prompt,
        array $contextProperties,
        string $emptyCheckProperty,
        ?string $llmConfigName,
        string $label,
        string $icon,
        string $successTitle,
    ): Action {
        return Action::make('generateAi_'.$targetField.'_direct')
            ->label($label)
            ->icon($icon)
            ->color('gray')
            ->button()
            ->outlined()
            ->extraAttributes(['class' => 'pko-ai-split-btn pko-ai-split-btn--start'])
            ->visible(fn ($livewire): bool => empty(strip_tags((string) ($livewire->{$emptyCheckProperty} ?? '')))
                && (auth()->user()?->can('generate_ai_content') ?? false)
            )
            ->action(function (Component $component, Set $set) use ($targetField, $prompt, $contextProperties, $llmConfigName, $successTitle): void {
                try {
                    $result = self::callLlm($component->getLivewire(), $prompt, $contextProperties, $llmConfigName);
                    $set($targetField, $result);
                    Notification::make()->success()->title($successTitle)->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Erreur LLM')->body($e->getMessage())->send();
                }
            });
    }

    /**
     * @param  array<string>  $contextProperties
     */
    private static function buildPreviewAction(
        string $targetField,
        string $prompt,
        array $contextProperties,
        string $emptyCheckProperty,
        ?string $llmConfigName,
        bool $htmlMode,
        string $label,
        string $icon,
        string $modalHeading,
    ): Action {
        return Action::make('generateAi_'.$targetField.'_preview')
            ->label($label)
            ->button()
            ->outlined()
            ->extraAttributes(['class' => 'pko-ai-split-btn pko-ai-split-btn--start'])
            ->icon($icon)
            ->color('gray')
            ->visible(fn ($livewire): bool => ! empty(strip_tags((string) ($livewire->{$emptyCheckProperty} ?? '')))
                && (auth()->user()?->can('generate_ai_content') ?? false)
            )
            ->fillForm(function (Component $component) use ($prompt, $contextProperties, $llmConfigName): array {
                try {
                    $result = self::callLlm($component->getLivewire(), $prompt, $contextProperties, $llmConfigName);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Erreur LLM')->body($e->getMessage())->send();
                    $result = '';
                }

                return [
                    'generatedContent' => $result,
                    'codeMode' => false,
                ];
            })
            ->form(self::previewFormSchema($htmlMode))
            ->modalHeading($modalHeading)
            ->modalSubmitActionLabel('Appliquer')
            ->modalWidth('5xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->action(function (array $data, Set $set) use ($targetField): void {
                $set($targetField, $data['generatedContent']);
            });
    }

    /**
     * Pencil action : always visible, opens a modal where the user can edit
     * the prompt, regenerate on demand, then preview + apply the result.
     *
     * @param  array<string>  $contextProperties
     */
    private static function buildEditPromptAction(
        string $targetField,
        string $prompt,
        array $contextProperties,
        ?string $llmConfigName,
        bool $htmlMode,
        string $modalHeading,
    ): Action {
        return Action::make('generateAi_'.$targetField.'_editPrompt')
            ->label('Modifier le prompt')
            ->hiddenLabel()
            ->tooltip('Modifier le prompt')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->button()
            ->outlined()
            ->extraAttributes(['class' => 'pko-ai-split-btn pko-ai-split-btn--end'])
            ->visible(fn (): bool => auth()->user()?->can('generate_ai_content') ?? false)
            ->fillForm(fn (): array => [
                'customPrompt' => $prompt,
                'llmConfigId' => self::resolveDefaultConfigId($llmConfigName),
                'enabledContext' => array_fill_keys($contextProperties, true),
                'generatedContent' => '',
                'codeMode' => false,
            ])
            ->form([
                Select::make('llmConfigId')
                    ->label('Modèle LLM')
                    ->options(fn (): array => LlmConfig::query()
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->native(false),
                Textarea::make('customPrompt')
                    ->label('Prompt envoyé au LLM')
                    ->helperText('Modifie ou enrichis le prompt pour ajouter du contexte supplémentaire que l\'IA ne peut pas déduire de la page.')
                    ->rows(8)
                    ->required(),
                ContextParametersTable::make('enabledContext')
                    ->label('Paramètres de la page transmis au prompt')
                    ->helperText('Décoche les lignes à exclure du contexte envoyé au LLM.')
                    ->rows(self::labelContextOptions($contextProperties))
                    ->visible(count($contextProperties) > 0),
                FormActions::make([
                    FormAction::make('regenerate')
                        ->label('Générer avec l\'IA')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->action(function (Component $component, Get $get, Set $set) use ($contextProperties): void {
                            try {
                                $enabledMap = (array) $get('enabledContext');
                                $selectedProps = array_values(array_filter(
                                    $contextProperties,
                                    fn (string $property): bool => (bool) ($enabledMap[$property] ?? false),
                                ));
                                $config = LlmConfig::query()
                                    ->where('id', (int) $get('llmConfigId'))
                                    ->where('active', true)
                                    ->firstOrFail();
                                $inputs = [];
                                foreach ($selectedProps as $property) {
                                    $inputs[$property] = (string) ($component->getLivewire()->{$property} ?? '');
                                }
                                $result = app(LlmManager::class)->forConfig($config)
                                    ->transform((string) $get('customPrompt'), $inputs);
                                $set('generatedContent', self::stripCodeFences($result));
                                Notification::make()->success()->title('Contenu généré — vérifiez l\'aperçu')->send();
                            } catch (\Throwable $e) {
                                Notification::make()->danger()->title('Erreur LLM')->body($e->getMessage())->send();
                            }
                        }),
                ]),
                ...self::previewFormSchema($htmlMode),
            ])
            ->modalHeading($modalHeading)
            ->modalSubmitActionLabel('Appliquer')
            ->modalWidth('5xl')
            ->modalFooterActionsAlignment(Alignment::End)
            ->action(function (array $data, Set $set) use ($targetField): void {
                $content = (string) ($data['generatedContent'] ?? '');
                if (trim($content) === '') {
                    Notification::make()->warning()->title('Aucun contenu à appliquer')->body('Cliquez d\'abord sur « Générer avec l\'IA ».')->send();

                    return;
                }
                $set($targetField, $content);
            });
    }

    /**
     * @return array<Component>
     */
    private static function previewFormSchema(bool $htmlMode): array
    {
        if (! $htmlMode) {
            return [
                Textarea::make('generatedContent')
                    ->label('Contenu généré (modifiable avant application)')
                    ->rows(12)
                    ->required(),
            ];
        }

        return [
            Toggle::make('codeMode')
                ->label('Mode code (HTML brut)')
                ->inline(false)
                ->live()
                ->default(false)
                ->dehydrated(false),
            Placeholder::make('htmlPreview')
                ->label('Aperçu')
                ->content(fn (Get $get): HtmlString => new HtmlString(
                    '<div style="max-height:60vh;overflow-y:auto" class="prose dark:prose-invert max-w-none rounded border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">'
                    .((string) ($get('generatedContent') ?? ''))
                    .'</div>'
                ))
                ->visible(fn (Get $get): bool => ! (bool) $get('codeMode')),
            Textarea::make('generatedContent')
                ->label('HTML brut (modifiable)')
                ->rows(16)
                ->required()
                ->visible(fn (Get $get): bool => (bool) $get('codeMode')),
        ];
    }

    /**
     * @param  array<string>  $contextProperties
     */
    private static function callLlm(
        mixed $livewire,
        string $prompt,
        array $contextProperties,
        ?string $configName,
    ): string {
        $config = $configName !== null
            ? LlmConfig::where('name', $configName)->where('active', true)->firstOrFail()
            : LlmConfig::default();

        if ($config === null) {
            throw new \RuntimeException('Aucune configuration LLM active trouvée.');
        }

        $inputs = [];
        foreach ($contextProperties as $property) {
            $inputs[$property] = (string) ($livewire->{$property} ?? '');
        }

        $result = app(LlmManager::class)->forConfig($config)->transform($prompt, $inputs);

        return self::stripCodeFences($result);
    }

    private static function resolveDefaultConfigId(?string $llmConfigName): ?int
    {
        if ($llmConfigName !== null) {
            return LlmConfig::query()
                ->where('name', $llmConfigName)
                ->where('active', true)
                ->value('id');
        }

        return LlmConfig::query()
            ->where('is_default', true)
            ->where('active', true)
            ->value('id');
    }

    /**
     * Humanize context property names for the checkbox list (camelCase → "Camel Case").
     *
     * @param  array<string>  $contextProperties
     * @return array<string, string>
     */
    private static function labelContextOptions(array $contextProperties): array
    {
        $out = [];
        foreach ($contextProperties as $property) {
            $out[$property] = ucfirst(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $property)));
        }

        return $out;
    }

    private static function stripCodeFences(string $result): string
    {
        $result = trim($result);

        if (str_starts_with($result, '```')) {
            $result = preg_replace('/^```[a-zA-Z]*\s*\n?/', '', $result) ?? $result;
            $result = preg_replace('/\n?```\s*$/', '', $result) ?? $result;
        }

        return trim($result);
    }
}
