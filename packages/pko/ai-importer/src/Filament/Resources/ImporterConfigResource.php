<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Support\PipelineSummary;
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
 *         each row carrying a compact server-rendered summary ({@see PipelineSummary})
 *         and a « Configurer » modal that opens the custom pipeline editor.
 *  - **JSON brut** — a raw textarea escape hatch for advanced edits.
 *
 * The « Configurer » modal embeds a self-contained JS editor
 * (resources/js/pipeline-editor.js, lifted verbatim from the PrestaShop « Publiko
 * AI Importer » module) reproducing its « Pipeline d'actions » UI trait-for-trait:
 * coloured SI / ALORS / SINON / PUIS flow with vertical connectors on the left, a
 * categorised action palette on the right. The editor serialises the column's
 * {sheet, col, default, actions} into the hidden `pipeline_json` field; the action
 * folds it back into that row's raw state.
 *
 * Both modes serialise to the same `config_data` JSON column. The visual editor
 * uses `sheets_repeater` / `mapping_repeater` scratch keys during form state that
 * {@see self::dehydrateVisual()} folds back into the canonical `sheets` / `mapping`
 * shape on save (and {@see self::hydrateVisual()} expands on fill). Per-mapping
 * `actions` are kept in their **canonical** `{type, …params}` / `{type, branches,
 * else_actions}` shape end-to-end (the JS editor reads and writes exactly what
 * {@see ActionPipeline} consumes), so no per-action
 * hydration layer is needed.
 */
class ImporterConfigResource extends BaseResource
{
    protected static ?string $model = ImporterConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    protected static ?string $cluster = PkoSystemDataCluster::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

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
                            // The raw editor lives on its own `config_json` scratch
                            // key — never on `config_data` — so it does not collide
                            // with the visual editor's `config_data.*` fields on the
                            // same Livewire state path (that collision rendered the
                            // textarea as « [object Object] » and wiped the mapping).
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('regenerateJson')
                                    ->label('Régénérer depuis l\'éditeur visuel')
                                    ->icon('heroicon-m-arrow-path')
                                    ->color('gray')
                                    ->action(function (Get $get, Forms\Set $set): void {
                                        $canonical = self::dehydrateVisual(['config_data' => $get('config_data')])['config_data'] ?? [];
                                        $set('config_json', json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                    }),
                            ]),
                            Forms\Components\Textarea::make('config_json')
                                ->label('Configuration JSON')
                                ->rows(28)
                                ->dehydrated(false)
                                ->live(onBlur: true)
                                ->columnSpanFull()
                                ->helperText('Édition directe du JSON. En quittant le champ, l\'éditeur visuel se met à jour. Le bouton ci-dessus régénère le JSON depuis l\'éditeur visuel.')
                                ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                                    $decoded = json_decode((string) $state, true);
                                    if (! is_array($decoded)) {
                                        return; // JSON invalide → on laisse l'éditeur visuel intact.
                                    }
                                    $expanded = self::hydrateVisual(['config_data' => $decoded])['config_data'] ?? [];
                                    $set('config_data', $expanded);
                                })
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
        return Forms\Components\Section::make('Mapping des colonnes')
            ->icon('heroicon-o-arrows-right-left')
            ->description('Une colonne cible par ligne. « Configurer » ouvre la source (feuille / colonne / défaut) et le pipeline d\'actions.')
            ->schema([
                // En-tête blueprint : recherche + masquer colonnes vides (filtre
                // client-side Alpine sur les items du repeater frère, voir la vue).
                Forms\Components\View::make('pko-ai-importer::filament.mapping-toolbar')
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('config_data.mapping_repeater')
                    ->hiddenLabel()
                    ->extraAttributes(['data-mapping-repeater' => 'true'])
                    ->schema([
                        Forms\Components\Select::make('key')
                            ->label('Colonne cible')
                            // Catalogue Lunar + préservation des clés sources
                            // (PrestaShop, etc.) hors catalogue : la valeur courante
                            // est toujours proposée, donc jamais perdue à la sauvegarde.
                            ->options(function (Get $get): array {
                                $options = ProductFieldCatalog::groupedOptions();
                                $current = (string) $get('key');
                                if ($current !== '' && ! array_key_exists($current, ProductFieldCatalog::flat())) {
                                    $options['Autres (source d\'origine)'] = [$current => ProductFieldCatalog::label($current)];
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Placeholder::make('pipeline_summary')
                            ->label('Configuration')
                            ->content(fn (Get $get): HtmlString => PipelineSummary::render([
                                'col' => $get('col'),
                                'sheet' => $get('sheet'),
                                'default' => $get('default'),
                                'actions' => (array) $get('actions'),
                            ]))
                            ->columnSpan(3),
                    ])
                    ->columns(5)
                    ->extraItemActions([
                        self::configurePipelineAction(),
                    ])
                    ->addActionLabel('+ Ajouter une colonne')
                    ->reorderableWithButtons()
                    ->cloneable()
                    ->itemLabel(fn (array $state): string => ProductFieldCatalog::label((string) ($state['key'] ?? '')))
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * « Configurer » modal action attached to each mapping row — opens the custom
     * pipeline editor, a faithful reproduction of the PrestaShop « Pipeline
     * d'actions » modal (coloured SI / ALORS / SINON / PUIS flow on the left,
     * categorised action palette on the right). The editor is a self-contained JS
     * engine (resources/js/pipeline-editor.js, lifted verbatim from the PS module)
     * mounted in the modal content; it serialises the column's
     * {sheet, col, default, actions} into the hidden `pipeline_json` field, which
     * {@see action} folds back into that row's raw state on « Appliquer ».
     */
    private static function configurePipelineAction(): Forms\Components\Actions\Action
    {
        $seedFrom = static function (array $arguments, Forms\Components\Repeater $component): array {
            $item = $component->getRawItemState($arguments['item']);

            return [
                'sheet' => $item['sheet'] ?? null,
                'col' => $item['col'] ?? null,
                'default' => $item['default'] ?? null,
                'actions' => array_values((array) ($item['actions'] ?? [])),
            ];
        };

        return Forms\Components\Actions\Action::make('configurePipeline')
            ->label('Configurer')
            ->icon('heroicon-m-cog-6-tooth')
            ->color('primary')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Appliquer')
            ->modalCancelActionLabel('Annuler')
            ->modalHeading(function (array $arguments, Forms\Components\Repeater $component): string {
                $item = $component->getRawItemState($arguments['item']);

                return 'Colonne : '.ProductFieldCatalog::label((string) ($item['key'] ?? ''));
            })
            // Le moteur JS pousse l'état complet dans ce champ caché (via les events
            // input/change qu'il propage), que Filament transmet en `$data` au submit.
            ->fillForm(function (array $arguments, Forms\Components\Repeater $component) use ($seedFrom): array {
                return ['pipeline_json' => json_encode($seedFrom($arguments, $component), JSON_UNESCAPED_UNICODE)];
            })
            ->form([
                Forms\Components\Hidden::make('pipeline_json')
                    ->extraAttributes(['data-pko-pipeline-json' => '1']),
            ])
            ->modalContent(function (array $arguments, Forms\Components\Repeater $component) use ($seedFrom): View {
                $item = $component->getRawItemState($arguments['item']);

                return view('pko-ai-importer::filament.pipeline-editor-modal', [
                    'seed' => $seedFrom($arguments, $component),
                    'label' => ProductFieldCatalog::label((string) ($item['key'] ?? '')),
                ]);
            })
            ->action(function (array $data, array $arguments, Forms\Components\Repeater $component): void {
                $payload = json_decode((string) ($data['pipeline_json'] ?? ''), true);
                if (! is_array($payload)) {
                    return;
                }

                $state = $component->getState();
                $item = $state[$arguments['item']] ?? [];
                $item['sheet'] = self::blankToNull($payload['sheet'] ?? null);
                $item['col'] = self::blankToNull($payload['col'] ?? null);
                $item['default'] = self::blankToNull($payload['default'] ?? null);
                $item['actions'] = array_values((array) ($payload['actions'] ?? []));
                $state[$arguments['item']] = $item;
                $component->state($state);
            });
    }

    private static function blankToNull(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
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

        // Miroir canonique pour l'onglet « JSON brut » (clé scratch dédiée, jamais
        // confondue avec `config_data` que pilotent les champs de l'éditeur visuel).
        $data['config_json'] = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // sheets{} → sheets_repeater[]
        $sheetsRepeater = [];
        foreach ((array) ($config['sheets'] ?? []) as $name => $cfg) {
            $sheetsRepeater[] = array_merge(['name' => $name], (array) $cfg);
        }
        $config['sheets_repeater'] = $sheetsRepeater;

        // mapping{} → mapping_repeater[] : les `actions` restent dans leur forme
        // canonique. Elles ne sont plus des composants Filament inline (édition
        // exclusivement via le modal « Configurer » / l'éditeur de pipeline JS),
        // donc aucune expansion form-shape n'est nécessaire — l'éditeur sérialise
        // et relit directement le canonique consommé par ActionPipeline.
        $mappingRepeater = [];
        foreach ((array) ($config['mapping'] ?? []) as $key => $cfg) {
            $cfgArr = (array) $cfg;
            $cfgArr['actions'] = array_values((array) ($cfgArr['actions'] ?? []));
            $mappingRepeater[] = array_merge(['key' => $key], $cfgArr);
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
                $actions = array_values((array) ($item['actions'] ?? []));
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
