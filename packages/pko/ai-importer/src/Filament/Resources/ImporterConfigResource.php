<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AiCore\Models\LlmConfig;
use Pko\AiImporter\Actions\Types\ConditionAction;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Support\ActionPalette;
use Pko\AiImporter\Support\ProductFieldCatalog;

/**
 * Config editor — faithful reproduction of the PrestaShop Publiko AI Importer
 * config editor, themed with Filament/Lunar.
 *
 * Two editing modes through tabs:
 *
 *  - **Éditeur visuel** — three structured sections:
 *      1. « Feuilles Excel » : main sheet + join key, then a tabular Repeater of
 *         related sheets (name, one/many relation, join column).
 *      2. « Configuration IA » : context-cache toggle + global AI context.
 *      3. « Mapping des colonnes » : a tabular Repeater of target product fields,
 *         each row carrying a compact summary of its pipeline and a « Configurer »
 *         modal opening the action-pipeline builder (categorised palette +
 *         SI/ALORS/SINON branching for the `condition` action).
 *  - **JSON brut** — a raw textarea escape hatch for advanced edits.
 *
 * Both modes serialise to the same `config_data` JSON column. The visual editor
 * uses `sheets_repeater` / `mapping_repeater` scratch keys during form state
 * that {@see self::dehydrateVisual()} folds back into the canonical
 * `sheets` / `mapping` shape on save (and {@see self::hydrateVisual()} expands
 * on fill). Most actions keep the proven `{type, params}` KeyValue
 * representation; `condition` gets a SI/ALORS/SINON branching editor,
 * `llm_transform` a named-fields IA-prompt editor, and `map` a dedicated
 * key/value lookup table — each with its own hydrate/dehydrate branch so no
 * existing action type regresses through the round-trip.
 */
class ImporterConfigResource extends BaseResource
{
    protected static ?string $model = ImporterConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    /** @var array<string, string> */
    private const RULE_OPERATORS = [
        '=' => '= (égal)',
        '!=' => '≠ (différent)',
        '>' => '> (supérieur)',
        '>=' => '≥',
        '<' => '< (inférieur)',
        '<=' => '≤',
        'contains' => 'contient',
        'not_contains' => 'ne contient pas',
        'empty' => 'est vide',
        'not_empty' => 'non vide',
        'in' => 'dans la liste',
        'not_in' => 'hors liste',
    ];

    public static function getLabel(): string
    {
        return 'Configuration import';
    }

    public static function getPluralLabel(): string
    {
        return 'Configurations import';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('ai-importer.navigation.group', 'Imports');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identité')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(128),
                    Forms\Components\TextInput::make('supplier_name')
                        ->label('Fournisseur')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Tabs::make('Éditeur')
                ->columnSpanFull()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Éditeur visuel')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema(self::visualSchema()),

                    Forms\Components\Tabs\Tab::make('JSON brut')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            Forms\Components\Textarea::make('config_data')
                                ->label('Configuration JSON')
                                ->rows(28)
                                ->required()
                                ->columnSpanFull()
                                ->helperText('Édition directe du JSON. Se synchronise avec l\'éditeur visuel en sauvegardant.')
                                ->formatStateUsing(fn ($state): string => $state ? json_encode(self::arrayify($state), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}')
                                ->dehydrateStateUsing(fn ($state): array => is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                                ->rules(['json']),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('supplier_name')->label('Fournisseur')->searchable(),
                Tables\Columns\TextColumn::make('jobs_count')->counts('jobs')->label('Jobs'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d/m/Y H:i')->label('Màj'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('export')
                    ->label('Exporter JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (ImporterConfig $record) {
                        $filename = 'ai-importer-config-'.$record->name.'-'.now()->format('Ymd-His').'.json';
                        $content = json_encode(self::arrayify($record->config_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                        return Response::streamDownload(fn () => print $content, $filename, [
                            'Content-Type' => 'application/json',
                        ]);
                    }),
                Tables\Actions\ReplicateAction::make()->excludeAttributes(['name'])->label('Dupliquer'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImporterConfigs::route('/'),
            'create' => Pages\CreateImporterConfig::route('/create'),
            'edit' => Pages\EditImporterConfig::route('/{record}/edit'),
        ];
    }

    /**
     * Visual tab schema — feuilles + IA + mapping table.
     *
     * @return array<int, mixed>
     */
    public static function visualSchema(): array
    {
        return [
            self::sheetsSection(),
            self::aiSection(),
            self::mappingSection(),
        ];
    }

    private static function sheetsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Feuilles Excel')
            ->icon('heroicon-o-table-cells')
            ->description('Feuille principale itérée à l\'import, plus les feuilles liées par une clé de jointure.')
            ->schema([
                Forms\Components\Select::make('config_data.type')
                    ->label('Type de source')
                    ->options([
                        'FAB-DIS' => 'FAB-DIS',
                        'CSV' => 'CSV',
                        'XLSX' => 'XLSX',
                    ])
                    ->native(false)
                    ->placeholder('—')
                    ->helperText('Format du fichier source attendu pour cette configuration.'),

                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('config_data.primary_sheet')
                        ->label('Feuille principale')
                        ->helperText('Nom de la feuille à itérer (ex: B01_COMMERCE)'),
                    Forms\Components\TextInput::make('config_data.join_key')
                        ->label('Clé de jointure globale')
                        ->helperText('Colonne partagée entre feuilles (ex: REFCIALE)'),
                ]),

                Forms\Components\Repeater::make('config_data.sheets_repeater')
                    ->label('Feuilles avec relations')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom de la feuille')
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Select::make('relation')
                            ->label('Relation')
                            ->options(['one' => 'Un (1-1)', 'many' => 'Plusieurs (1-N)'])
                            ->placeholder('—'),
                        Forms\Components\TextInput::make('join_key')
                            ->label('Colonne de jointure')
                            ->placeholder('Globale si vide'),
                        Forms\Components\TextInput::make('type')
                            ->label('Type')
                            ->placeholder('logistics, images…'),
                        Forms\Components\Toggle::make('skip_first_row')
                            ->label('1ʳᵉ ligne = en-têtes')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(6)
                    ->addActionLabel('+ Ajouter une feuille')
                    ->reorderableWithButtons()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->defaultItems(0),
            ]);
    }

    private static function aiSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Configuration IA (optionnel)')
            ->icon('heroicon-o-sparkles')
            ->collapsed()
            ->schema([
                Forms\Components\Toggle::make('config_data.ai.context_cache')
                    ->label('Activer le cache de contexte')
                    ->helperText('Réutilise le contexte global entre les appels IA d\'un même import.'),
                Forms\Components\Textarea::make('config_data.ai.global_context')
                    ->label('Contexte global IA')
                    ->rows(4)
                    ->helperText('Injecté en préambule de chaque prompt IA (ex: ton de marque, conventions catalogue).')
                    ->columnSpanFull(),
            ]);
    }

    private static function mappingSection(): Forms\Components\Section
    {
        $fieldOptions = ProductFieldCatalog::groupedOptions();

        return Forms\Components\Section::make('Mapping des colonnes')
            ->icon('heroicon-o-arrows-right-left')
            ->description('Un champ produit cible par ligne. « Configurer » ouvre le pipeline d\'actions.')
            ->schema([
                Forms\Components\Repeater::make('config_data.mapping_repeater')
                    ->hiddenLabel()
                    ->schema([
                        Forms\Components\Select::make('key')
                            ->label('Champ cible')
                            ->options($fieldOptions)
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('col')
                            ->label('Colonne source')
                            ->placeholder('En-tête ou lettre (M, AA…)'),
                        Forms\Components\TextInput::make('sheet')
                            ->label('Feuille')
                            ->placeholder('Principale si vide'),
                        Forms\Components\TextInput::make('default')
                            ->label('Valeur par défaut')
                            ->placeholder('—'),
                        Forms\Components\Placeholder::make('pipeline_summary')
                            ->label('Pipeline')
                            ->content(fn (Get $get): HtmlString => self::summarizePipeline((array) $get('actions'))),
                    ])
                    ->columns(6)
                    ->extraItemActions([
                        self::configurePipelineAction(),
                    ])
                    ->addActionLabel('+ Ajouter un champ')
                    ->reorderableWithButtons()
                    ->cloneable()
                    ->itemLabel(fn (array $state): string => ProductFieldCatalog::label((string) ($state['key'] ?? '')))
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * « Configurer » modal action attached to each mapping row — edits the
     * `actions` pipeline of that row in a wide modal.
     */
    private static function configurePipelineAction(): Forms\Components\Actions\Action
    {
        return Forms\Components\Actions\Action::make('configurePipeline')
            ->label('Configurer')
            ->icon('heroicon-m-cog-6-tooth')
            ->color('primary')
            ->modalWidth('5xl')
            ->modalHeading('Pipeline d\'actions')
            ->fillForm(function (array $arguments, Forms\Components\Repeater $component): array {
                $item = $component->getItemState($arguments['item']);

                return ['actions' => $item['actions'] ?? []];
            })
            ->form([
                Forms\Components\Placeholder::make('pipeline_help')
                    ->hiddenLabel()
                    ->content(new HtmlString('Les actions s\'exécutent de haut en bas sur la valeur de la colonne. Utilisez <strong>Condition</strong> pour brancher SI / ALORS / SINON.')),
                self::actionsRepeater('actions', allowCondition: true),
            ])
            ->action(function (array $data, array $arguments, Forms\Components\Repeater $component): void {
                $state = $component->getState();
                $state[$arguments['item']]['actions'] = $data['actions'] ?? [];
                $component->state($state);
            });
    }

    /**
     * A pipeline of actions. The top level allows `condition`; nested branch
     * pipelines do not (mirrors {@see ConditionAction}
     * which never recurses into a nested condition).
     */
    private static function actionsRepeater(string $name, bool $allowCondition): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->hiddenLabel()
            ->schema(self::actionItemSchema($allowCondition))
            ->addActionLabel($allowCondition ? '+ Ajouter une action' : '+ Action')
            ->reorderableWithButtons()
            ->collapsible()
            ->itemLabel(fn (array $state): string => ActionPalette::label((string) ($state['type'] ?? '')))
            ->defaultItems(0);
    }

    /**
     * Schema of a single action item: a categorised type Select, the generic
     * params KeyValue (for every non-`condition` type), and — only when
     * `condition` is allowed and selected — the SI/ALORS/SINON branching editor.
     *
     * @return array<int, mixed>
     */
    private static function actionItemSchema(bool $allowCondition): array
    {
        $schema = [
            Forms\Components\Select::make('type')
                ->label('Type d\'action')
                ->options(ActionPalette::groupedOptions($allowCondition))
                ->searchable()
                ->required()
                ->live()
                ->columnSpanFull(),

            // Generic key/value editor for every action type that has no
            // dedicated form below (`condition`, `llm_transform` and `map`
            // each carry their own structured editor).
            Forms\Components\KeyValue::make('params')
                ->label('Paramètres')
                ->keyLabel('Paramètre')
                ->valueLabel('Valeur')
                ->reorderable()
                ->visible(fn (Get $get): bool => ! in_array($get('type'), ['condition', 'llm_transform', 'map'], true))
                ->columnSpanFull(),

            // Dedicated editor for the IA prompt action.
            self::llmTransformFieldset(),

            // Dedicated key/value editor for the lookup-table action.
            self::mapFieldset(),
        ];

        if ($allowCondition) {
            $schema[] = Forms\Components\Fieldset::make('Branches')
                ->visible(fn (Get $get): bool => $get('type') === 'condition')
                ->schema([
                    Forms\Components\Repeater::make('branches')
                        ->hiddenLabel()
                        ->addActionLabel('+ SINON SI / branche')
                        ->reorderableWithButtons()
                        ->defaultItems(1)
                        ->schema([
                            Forms\Components\Select::make('logic')
                                ->label('Logique entre règles')
                                ->options(['AND' => 'TOUTES (ET)', 'OR' => 'AU MOINS UNE (OU)'])
                                ->default('AND')
                                ->columnSpanFull(),
                            self::rulesRepeater(),
                            Forms\Components\Section::make('ALORS exécuter')
                                ->compact()
                                ->schema([self::actionsRepeater('actions', allowCondition: false)]),
                        ])
                        ->columnSpanFull(),

                    Forms\Components\Section::make('SINON (par défaut)')
                        ->compact()
                        ->schema([self::actionsRepeater('else_actions', allowCondition: false)])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull();
        }

        return $schema;
    }

    /**
     * Structured editor for the `llm_transform` action — replaces the raw
     * params KeyValue with named fields matching {@see LlmTransformAction}.
     */
    private static function llmTransformFieldset(): Forms\Components\Fieldset
    {
        return Forms\Components\Fieldset::make('Prompt IA')
            ->visible(fn (Get $get): bool => $get('type') === 'llm_transform')
            ->columns(4)
            ->schema([
                Forms\Components\Select::make('llm_config_id')
                    ->label('Configuration LLM')
                    ->options(fn (): array => LlmConfig::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Configuration par défaut')
                    ->searchable()
                    ->native(false)
                    ->columnSpan(2),
                Forms\Components\Select::make('output_format')
                    ->label('Format de sortie')
                    ->options(['string' => 'Texte', 'json' => 'JSON'])
                    ->default('string')
                    ->native(false)
                    ->live()
                    ->columnSpan(1),
                Forms\Components\TextInput::make('output_json_key')
                    ->label('Clé JSON de sortie')
                    ->placeholder('ex: description')
                    ->visible(fn (Get $get): bool => $get('output_format') === 'json')
                    ->columnSpan(1),
                Forms\Components\Textarea::make('prompt')
                    ->label('Prompt')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TagsInput::make('input_columns')
                    ->label('Colonnes d\'entrée')
                    ->placeholder('Ajouter une colonne')
                    ->helperText('Colonnes de la ligne injectées dans le prompt.')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('additional_context')
                    ->label('Contexte additionnel')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columnSpanFull();
    }

    /**
     * Structured editor for the `map` action — a dedicated key/value table
     * feeding the `values` param instead of hand-typed JSON.
     */
    private static function mapFieldset(): Forms\Components\Fieldset
    {
        return Forms\Components\Fieldset::make('Table de correspondance')
            ->visible(fn (Get $get): bool => $get('type') === 'map')
            ->columns(3)
            ->schema([
                Forms\Components\KeyValue::make('values')
                    ->label('Correspondances')
                    ->keyLabel('Valeur source')
                    ->valueLabel('Valeur cible')
                    ->reorderable()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('default')
                    ->label('Valeur par défaut')
                    ->placeholder('Vide = ignorer la valeur'),
                Forms\Components\Toggle::make('multi_value')
                    ->label('Valeurs multiples')
                    ->live()
                    ->inline(false),
                Forms\Components\TextInput::make('separator')
                    ->label('Séparateur')
                    ->default(',')
                    ->visible(fn (Get $get): bool => (bool) $get('multi_value')),
            ])
            ->columnSpanFull();
    }

    private static function rulesRepeater(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('rules')
            ->label('SI')
            ->addActionLabel('+ Règle')
            ->defaultItems(1)
            ->schema([
                Forms\Components\TextInput::make('field')
                    ->label('Champ')
                    ->placeholder('col, FEUILLE:col, col_value'),
                Forms\Components\Select::make('operator')
                    ->label('Opérateur')
                    ->options(self::RULE_OPERATORS)
                    ->default('='),
                Forms\Components\TextInput::make('value')
                    ->label('Valeur')
                    ->placeholder('valeur (CSV pour « dans la liste »)'),
            ])
            ->columns(3)
            ->columnSpanFull();
    }

    private static function summarizePipeline(array $actions): HtmlString
    {
        if ($actions === []) {
            return new HtmlString('<span class="text-gray-400 text-sm">— aucune action —</span>');
        }

        $labels = array_map(
            static fn (array $a): string => e(ActionPalette::label((string) ($a['type'] ?? ''))),
            array_values($actions),
        );

        $count = count($labels);
        $badges = implode(' → ', $labels);

        return new HtmlString(
            '<span class="text-sm"><strong>'.$count.'</strong> action'.($count > 1 ? 's' : '').' · '.$badges.'</span>'
        );
    }

    /**
     * JSON → form scratch keys (called on fill).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateVisual(array $data): array
    {
        $config = self::arrayify($data['config_data'] ?? []);

        // sheets{} → sheets_repeater[]
        $sheetsRepeater = [];
        foreach ((array) ($config['sheets'] ?? []) as $name => $cfg) {
            $sheetsRepeater[] = array_merge(['name' => $name], (array) $cfg);
        }
        $config['sheets_repeater'] = $sheetsRepeater;

        // mapping{} → mapping_repeater[]
        $mappingRepeater = [];
        foreach ((array) ($config['mapping'] ?? []) as $key => $cfg) {
            $cfgArr = (array) $cfg;
            $actions = self::hydrateActions((array) ($cfgArr['actions'] ?? []), allowCondition: true);
            $mappingRepeater[] = array_merge(
                ['key' => $key],
                array_diff_key($cfgArr, ['actions' => true]),
                ['actions' => $actions],
            );
        }
        $config['mapping_repeater'] = $mappingRepeater;

        $data['config_data'] = $config;

        return $data;
    }

    /**
     * Form scratch keys → canonical JSON (called on save).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dehydrateVisual(array $data): array
    {
        $config = self::arrayify($data['config_data'] ?? []);

        // sheets_repeater[] → sheets{}
        if (isset($config['sheets_repeater']) && is_array($config['sheets_repeater'])) {
            $sheets = [];
            foreach ($config['sheets_repeater'] as $item) {
                $name = $item['name'] ?? null;
                if ($name === null || $name === '') {
                    continue;
                }
                unset($item['name']);
                $sheets[$name] = array_filter($item, static fn ($v) => $v !== null && $v !== '');
            }
            unset($config['sheets_repeater']);
            if ($sheets !== []) {
                $config['sheets'] = $sheets;
            } else {
                unset($config['sheets']);
            }
        }

        // mapping_repeater[] → mapping{}
        if (isset($config['mapping_repeater']) && is_array($config['mapping_repeater'])) {
            $mapping = [];
            foreach ($config['mapping_repeater'] as $item) {
                $key = $item['key'] ?? null;
                if ($key === null || $key === '') {
                    continue;
                }
                unset($item['key']);
                $actions = self::dehydrateActions((array) ($item['actions'] ?? []), allowCondition: true);
                unset($item['actions']);
                if ($actions !== []) {
                    $item['actions'] = $actions;
                }
                $mapping[$key] = $item;
            }
            unset($config['mapping_repeater']);
            if ($mapping !== []) {
                $config['mapping'] = $mapping;
            } else {
                unset($config['mapping']);
            }
        }

        $config = self::normalizeAi($config);

        $data['config_data'] = $config;

        return $data;
    }

    /**
     * Drop the `ai` block when unused, otherwise keep a tidy normalised shape.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeAi(array $config): array
    {
        $ai = (array) ($config['ai'] ?? []);
        $cache = (bool) ($ai['context_cache'] ?? false);
        $global = trim((string) ($ai['global_context'] ?? ''));

        if (! $cache && $global === '') {
            unset($config['ai']);

            return $config;
        }

        $config['ai'] = ['context_cache' => $cache] + ($global !== '' ? ['global_context' => $global] : []);

        return $config;
    }

    /**
     * Canonical actions list → form items.
     *
     * @param  array<int, mixed>  $actions
     * @return array<int, array<string, mixed>>
     */
    private static function hydrateActions(array $actions, bool $allowCondition): array
    {
        $out = [];
        foreach ($actions as $a) {
            $a = (array) $a;
            $type = (string) ($a['type'] ?? '');
            if ($type === '') {
                continue;
            }

            if ($allowCondition && $type === 'condition') {
                $branches = [];
                foreach ((array) ($a['branches'] ?? []) as $b) {
                    $b = (array) $b;
                    $rules = [];
                    foreach ((array) ($b['rules'] ?? []) as $r) {
                        $r = (array) $r;
                        $rules[] = [
                            'field' => (string) ($r['field'] ?? ''),
                            'operator' => (string) ($r['operator'] ?? '='),
                            'value' => self::scalarToString($r['value'] ?? ''),
                        ];
                    }
                    $branches[] = [
                        'logic' => strtoupper((string) ($b['logic'] ?? 'AND')),
                        'rules' => $rules,
                        'actions' => self::hydrateActions((array) ($b['actions'] ?? []), allowCondition: false),
                    ];
                }

                $out[] = [
                    'type' => 'condition',
                    'params' => [],
                    'branches' => $branches,
                    'else_actions' => self::hydrateActions((array) ($a['else_actions'] ?? []), allowCondition: false),
                ];

                continue;
            }

            if ($type === 'llm_transform') {
                $out[] = self::baseItem('llm_transform') + [
                    'llm_config_id' => isset($a['llm_config_id']) ? (int) $a['llm_config_id'] : null,
                    'prompt' => (string) ($a['prompt'] ?? ''),
                    'input_columns' => array_values(array_map('strval', (array) ($a['input_columns'] ?? []))),
                    'output_format' => (string) ($a['output_format'] ?? 'string'),
                    'output_json_key' => isset($a['output_json_key']) ? (string) $a['output_json_key'] : null,
                    'additional_context' => isset($a['additional_context']) ? (string) $a['additional_context'] : null,
                ];

                continue;
            }

            if ($type === 'map') {
                $values = [];
                foreach ((array) ($a['values'] ?? []) as $k => $v) {
                    $values[(string) $k] = self::scalarToString($v);
                }
                $out[] = self::baseItem('map') + [
                    'values' => $values,
                    'default' => isset($a['default']) ? self::scalarToString($a['default']) : null,
                    'multi_value' => (bool) ($a['multi_value'] ?? false),
                    'separator' => (string) ($a['separator'] ?? ','),
                ];

                continue;
            }

            $out[] = [
                'type' => $type,
                'params' => self::stringifyParams(array_diff_key($a, ['type' => true])),
                'branches' => [],
                'else_actions' => [],
            ];
        }

        return $out;
    }

    /**
     * Empty form-item skeleton for an action with a dedicated editor: the
     * generic params/branches keys stay present (but hidden) so every repeater
     * item shares a uniform shape.
     *
     * @return array<string, mixed>
     */
    private static function baseItem(string $type): array
    {
        return ['type' => $type, 'params' => [], 'branches' => [], 'else_actions' => []];
    }

    /**
     * Form items → canonical actions list.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function dehydrateActions(array $items, bool $allowCondition): array
    {
        $out = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }

            if ($allowCondition && $type === 'condition') {
                $branches = [];
                foreach ((array) ($item['branches'] ?? []) as $b) {
                    $b = (array) $b;
                    $rules = [];
                    foreach ((array) ($b['rules'] ?? []) as $r) {
                        $r = (array) $r;
                        $field = (string) ($r['field'] ?? '');
                        if ($field === '') {
                            continue;
                        }
                        $rules[] = [
                            'field' => $field,
                            'operator' => (string) ($r['operator'] ?? '='),
                            'value' => self::scalarToString($r['value'] ?? ''),
                        ];
                    }
                    $branches[] = [
                        'logic' => strtoupper((string) ($b['logic'] ?? 'AND')),
                        'rules' => array_values($rules),
                        'actions' => self::dehydrateActions((array) ($b['actions'] ?? []), allowCondition: false),
                    ];
                }

                $out[] = [
                    'type' => 'condition',
                    'branches' => array_values($branches),
                    'else_actions' => self::dehydrateActions((array) ($item['else_actions'] ?? []), allowCondition: false),
                ];

                continue;
            }

            if ($type === 'llm_transform') {
                $action = ['type' => 'llm_transform'];

                $llmId = $item['llm_config_id'] ?? null;
                if ($llmId !== null && $llmId !== '') {
                    $action['llm_config_id'] = (int) $llmId;
                }
                $prompt = trim((string) ($item['prompt'] ?? ''));
                if ($prompt !== '') {
                    $action['prompt'] = $prompt;
                }
                $columns = array_values(array_filter(
                    array_map('strval', (array) ($item['input_columns'] ?? [])),
                    static fn (string $c): bool => $c !== '',
                ));
                if ($columns !== []) {
                    $action['input_columns'] = $columns;
                }
                $action['output_format'] = ($item['output_format'] ?? 'string') === 'json' ? 'json' : 'string';
                if ($action['output_format'] === 'json') {
                    $jsonKey = trim((string) ($item['output_json_key'] ?? ''));
                    if ($jsonKey !== '') {
                        $action['output_json_key'] = $jsonKey;
                    }
                }
                $context = trim((string) ($item['additional_context'] ?? ''));
                if ($context !== '') {
                    $action['additional_context'] = $context;
                }

                $out[] = $action;

                continue;
            }

            if ($type === 'map') {
                $values = [];
                foreach ((array) ($item['values'] ?? []) as $k => $v) {
                    if ((string) $k === '') {
                        continue;
                    }
                    $values[(string) $k] = self::scalarToString($v);
                }
                $default = $item['default'] ?? null;
                $separator = (string) ($item['separator'] ?? ',');
                $out[] = [
                    'type' => 'map',
                    'values' => $values,
                    'default' => ($default === null || $default === '') ? null : self::scalarToString($default),
                    'multi_value' => (bool) ($item['multi_value'] ?? false),
                    'separator' => $separator !== '' ? $separator : ',',
                ];

                continue;
            }

            $params = is_array($item['params'] ?? null) ? self::typedParams($item['params']) : [];
            $out[] = ['type' => $type] + $params;
        }

        return array_values($out);
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * KeyValue stores everything as strings. Restore bool / int / float / json.
     *
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private static function typedParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            $out[$k] = match (true) {
                is_string($v) && $v === 'true' => true,
                is_string($v) && $v === 'false' => false,
                is_string($v) && is_numeric($v) && ! str_contains($v, '.') => (int) $v,
                is_string($v) && is_numeric($v) => (float) $v,
                is_string($v) && str_starts_with(ltrim($v), '{') => json_decode($v, true) ?? $v,
                is_string($v) && str_starts_with(ltrim($v), '[') => json_decode($v, true) ?? $v,
                default => $v,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private static function stringifyParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            $out[$k] = match (true) {
                is_bool($v) => $v ? 'true' : 'false',
                is_scalar($v) || $v === null => (string) $v,
                default => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            };
        }

        return $out;
    }

    /**
     * @param  mixed  $state
     * @return array<string, mixed>
     */
    public static function arrayify($state): array
    {
        if ($state instanceof \ArrayObject) {
            return $state->getArrayCopy();
        }

        return is_array($state) ? $state : [];
    }
}
