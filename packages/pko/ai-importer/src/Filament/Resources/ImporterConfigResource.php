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
use Illuminate\Support\Str;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;
use Pko\AiImporter\Models\ImporterConfig;
use Pko\AiImporter\Services\ActionPipeline;
use Pko\AiImporter\Support\ConfigColumnExtractor;
use Pko\AiImporter\Support\ConfigDiagnostics;
use Pko\AiImporter\Support\MappingOrder;
use Pko\AiImporter\Support\PipelineSummary;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Libellés affichés tels quels (pas de Majuscule À Chaque Mot dans les titres). */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    public static function getLabel(): string
    {
        return 'Configuration d\'import';
    }

    public static function getPluralLabel(): string
    {
        return 'Configurations d\'import';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('ai-importer.navigation.group', 'Imports');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Constats sur la config ENREGISTRÉE (erreurs et avertissements) : ce qui
            // ferait échouer une préparation est visible avant de lancer un import.
            Forms\Components\Placeholder::make('diagnostics')
                ->hiddenLabel()
                ->columnSpanFull()
                ->visible(fn (?ImporterConfig $record): bool => self::blockingFindings($record) !== [])
                ->content(fn (?ImporterConfig $record): HtmlString => new HtmlString(view('pko-ai-importer::filament.config-diagnostics', [
                    'findings' => self::blockingFindings($record),
                ])->render())),

            Forms\Components\Section::make()
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom')
                        ->placeholder('ex. Nom du fournisseur — tarif FAB-DIS')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(128),
                    Forms\Components\TextInput::make('supplier_name')
                        ->label('Fournisseur')
                        ->maxLength(255),
                    Forms\Components\Select::make('config_data.type')
                        ->label('Type de fichier')
                        ->options([
                            'FAB-DIS' => 'FAB-DIS',
                            'CSV' => 'CSV',
                            'XLSX' => 'XLSX',
                        ])
                        ->native(false)
                        ->placeholder('—'),
                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Tabs::make('Éditeur')
                ->columnSpanFull()
                ->persistTabInQueryString('onglet')
                // Une config existante s'ouvre sur ses colonnes ; une nouvelle commence
                // par décrire les feuilles du fichier.
                ->activeTab(fn (?ImporterConfig $record): int => $record !== null ? 2 : 1)
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Feuilles du fichier')
                        ->icon('heroicon-o-table-cells')
                        ->schema(self::sheetsSchema()),

                    Forms\Components\Tabs\Tab::make('Colonnes')
                        ->icon('heroicon-o-arrows-right-left')
                        ->badge(fn (Get $get): ?int => count((array) $get('config_data.mapping_repeater')) ?: null)
                        ->schema(self::mappingSchema()),

                    Forms\Components\Tabs\Tab::make('IA')
                        ->icon('heroicon-o-sparkles')
                        ->schema(self::aiSchema()),

                    Forms\Components\Tabs\Tab::make('JSON')
                        ->icon('heroicon-o-code-bracket')
                        ->schema([
                            // The raw editor lives on its own `config_json` scratch
                            // key — never on `config_data` — so it does not collide
                            // with the visual editor's `config_data.*` fields on the
                            // same Livewire state path (that collision rendered the
                            // textarea as « [object Object] » and wiped the mapping).
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('regenerateJson')
                                    ->label('Régénérer depuis les autres onglets')
                                    ->icon('heroicon-m-arrow-path')
                                    ->color('gray')
                                    ->action(function (Get $get, Forms\Set $set): void {
                                        $canonical = self::dehydrateVisual(['config_data' => $get('config_data')])['config_data'] ?? [];
                                        $set('config_json', json_encode($canonical, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                    }),
                            ]),
                            Forms\Components\Textarea::make('config_json')
                                ->label('Configuration complète (JSON)')
                                ->rows(28)
                                ->dehydrated(false)
                                ->live(onBlur: true)
                                ->columnSpanFull()
                                ->helperText('Pour les réglages avancés ou un copier-coller. En quittant le champ, les autres onglets se mettent à jour ; le bouton ci-dessus fait l\'inverse.')
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

    /**
     * Erreurs et avertissements de la config enregistrée (les informations —
     * colonnes non importées, colonnes IA — ne sont pas des alertes).
     *
     * @return array<int, array{level: string, message: string}>
     */
    public static function blockingFindings(?ImporterConfig $record): array
    {
        if ($record === null) {
            return [];
        }

        return array_values(array_filter(
            ConfigDiagnostics::analyze(self::arrayify($record->config_data)),
            static fn (array $finding): bool => $finding['level'] !== ConfigDiagnostics::INFO,
        ));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Configuration')
                    ->description(fn (ImporterConfig $record): ?string => $record->supplier_name)
                    ->weight('medium')
                    ->searchable(['name', 'supplier_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->state(fn (ImporterConfig $record): ?string => data_get(self::arrayify($record->config_data), 'type'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('columns')
                    ->label('Colonnes')
                    ->state(fn (ImporterConfig $record): int => count((array) data_get(self::arrayify($record->config_data), 'mapping', [])))
                    ->description(function (ImporterConfig $record): ?string {
                        $ai = count(ConfigColumnExtractor::aiColumnKeys($record));

                        return $ai > 0 ? "dont {$ai} par IA" : null;
                    }),
                Tables\Columns\TextColumn::make('health')
                    ->label('État')
                    ->badge()
                    ->state(fn (ImporterConfig $record): string => match (true) {
                        ConfigDiagnostics::hasErrors(self::blockingFindings($record)) => 'À corriger',
                        self::blockingFindings($record) !== [] => 'À vérifier',
                        default => 'Prête',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'À corriger' => 'danger',
                        'À vérifier' => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (ImporterConfig $record): ?string => collect(self::blockingFindings($record))->pluck('message')->implode("\n") ?: null),
                Tables\Columns\TextColumn::make('jobs_count')->counts('jobs')->label('Imports'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('d/m/Y H:i')->label('Modifiée le')->sortable(),
            ])
            ->recordUrl(fn (ImporterConfig $record): string => static::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\Action::make('newImport')
                    ->label('Nouvel import')
                    ->icon('heroicon-m-play')
                    ->url(fn (ImporterConfig $record): string => ImportJobResource::getUrl('create', ['config' => $record->id])),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('export')
                        ->label('Exporter (JSON)')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (ImporterConfig $record) => self::exportJson($record)),
                    Tables\Actions\ReplicateAction::make()
                        ->label('Dupliquer')
                        ->beforeReplicaSaved(function (ImporterConfig $replica, ImporterConfig $record): void {
                            $replica->name = self::copyName($record->name);
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-cog-6-tooth')
            ->emptyStateHeading('Aucune configuration')
            ->emptyStateDescription('Une configuration décrit comment lire le fichier d\'un fournisseur : ses feuilles, et quelle colonne devient quel champ produit.')
            ->defaultSort('name');
    }

    public static function exportJson(ImporterConfig $record): StreamedResponse
    {
        $filename = 'config-import-'.Str::slug($record->name).'-'.now()->format('Ymd-His').'.json';
        $content = json_encode(self::arrayify($record->config_data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return Response::streamDownload(fn () => print $content, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /** Nom libre pour une copie : « X (copie) », puis « X (copie 2) »… */
    public static function copyName(string $name): string
    {
        $candidate = $name.' (copie)';
        $i = 2;
        while (ImporterConfig::query()->where('name', $candidate)->exists()) {
            $candidate = $name.' (copie '.$i++.')';
        }

        return $candidate;
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
     * Éditeur visuel complet (feuilles + IA + colonnes), à plat. Conservé pour
     * les usages qui veulent tout le schéma d'un bloc ; le formulaire, lui,
     * répartit ces trois parties en onglets.
     *
     * @return array<int, mixed>
     */
    public static function visualSchema(): array
    {
        return [
            ...self::sheetsSchema(),
            ...self::aiSchema(),
            ...self::mappingSchema(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function sheetsSchema(): array
    {
        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('config_data.primary_sheet')
                    ->label('Feuille principale')
                    ->placeholder('ex. B01_COMMERCE')
                    ->helperText('Celle qui contient une ligne par produit. Vide = première feuille du fichier.'),
                Forms\Components\TextInput::make('config_data.join_key')
                    ->label('Colonne commune aux feuilles')
                    ->placeholder('ex. REFCIALE')
                    ->helperText('Colonne de la feuille principale qui identifie le produit dans les autres feuilles (intitulé ou lettre).'),
            ]),

            Forms\Components\Repeater::make('config_data.sheets_repeater')
                ->label('Feuilles liées')
                ->helperText('Les autres feuilles à lire, rapprochées de la feuille principale par la colonne commune. Déclarez aussi la feuille principale si elle n\'a pas de ligne d\'en-têtes.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom de la feuille')
                        ->required()
                        ->columnSpan(2),
                    Forms\Components\Select::make('relation')
                        ->label('Lignes par produit')
                        ->options(['one' => 'Une seule', 'many' => 'Plusieurs'])
                        ->placeholder('—'),
                    Forms\Components\TextInput::make('join_col')
                        ->label('Colonne clé')
                        ->placeholder('Commune si vide'),
                    Forms\Components\TextInput::make('type_col')
                        ->label('Colonne « type »')
                        ->placeholder('ex. MTYP'),
                    Forms\Components\Toggle::make('skip_first_row')
                        ->label('1ʳᵉ ligne = en-têtes')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(6)
                ->addActionLabel('Ajouter une feuille')
                ->reorderableWithButtons()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->defaultItems(0),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function aiSchema(): array
    {
        return [
            Forms\Components\Textarea::make('config_data.ai.global_context')
                ->label('Contexte commun à tous les appels IA')
                ->rows(8)
                ->helperText('Ajouté en préambule de chaque prompt (ton de marque, conventions du catalogue). Les marqueurs {{categories}} et {{features}} sont remplacés, à chaque préparation, par les catégories du site et par les familles de caractéristiques avec leurs valeurs.')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('config_data.ai.context_cache')
                ->label('Mettre ce contexte en cache')
                ->helperText('Réduit le coût quand le contexte est long (liste de catégories) : il n\'est facturé plein tarif qu\'une fois par préparation.'),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function mappingSchema(): array
    {
        return [
            // Recherche + masquer colonnes vides (filtre client-side Alpine sur les
            // items du repeater frère, voir la vue).
            Forms\Components\View::make('pko-ai-importer::filament.mapping-toolbar')
                ->columnSpanFull(),

            Forms\Components\Repeater::make('config_data.mapping_repeater')
                ->hiddenLabel()
                ->extraAttributes(['data-mapping-repeater' => 'true'])
                ->schema([
                    Forms\Components\Select::make('key')
                        ->label('Champ produit')
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
                        ->label('Source et transformations')
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
                ->addActionLabel('Ajouter une colonne')
                ->reorderableWithButtons()
                ->cloneable()
                // Une ligne par colonne : le champ produit et, en clair, d'où vient sa
                // valeur. La ligne se déplie pour changer de champ ou voir le détail
                // du pipeline ; l'engrenage ouvre l'éditeur de source et d'actions.
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state): HtmlString => self::mappingItemLabel($state))
                ->truncateItemLabel(false)
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    /**
     * En-tête d'une colonne du mapping : champ produit, badge IA, puis la source
     * et les transformations en clair. « sans source » signale une colonne vide.
     *
     * @param  array<string, mixed>  $state
     */
    public static function mappingItemLabel(array $state): HtmlString
    {
        $key = (string) ($state['key'] ?? '');
        $summary = PipelineSummary::oneLine($state);
        $ai = ConfigColumnExtractor::usesAi($state)
            ? ' <span style="font-size:.6875rem;font-weight:600;padding:.0625rem .375rem;border-radius:.375rem;background:rgba(var(--warning-500),.14);color:rgb(var(--warning-700));">IA</span>'
            : '';
        $ignored = $key !== '' && ConfigDiagnostics::ignoredKeys([$key]) !== []
            ? ' <span style="font-size:.6875rem;font-weight:500;padding:.0625rem .375rem;border-radius:.375rem;background:rgba(var(--gray-500),.12);color:rgb(var(--gray-500));" title="Aucun champ du catalogue ne correspond : cette colonne est préparée mais pas importée.">non importée</span>'
            : '';

        return new HtmlString(
            '<span style="display:inline-flex;flex-wrap:wrap;align-items:baseline;gap:.25rem .625rem;">'
            .'<span style="font-weight:600;">'.e($key !== '' ? ProductFieldCatalog::label($key) : 'Nouvelle colonne').'</span>'
            .$ai.$ignored
            .'<span style="font-weight:400;color:rgb(var(--gray-500));font-size:.8125rem;">'.e($summary !== '' ? $summary : 'sans source — aucune configuration').'</span>'
            .'</span>'
        );
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
            ->label('Source et transformations')
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
            $cfg = (array) $cfg;
            // Clé absente = « 1ʳᵉ ligne = en-têtes » (défaut du parser). Sans cette
            // valeur explicite, le Toggle s'hydratait à false et CHAQUE sauvegarde
            // de l'éditeur visuel écrivait `skip_first_row: false` : la ligne
            // d'en-têtes était alors importée comme un produit et toute colonne
            // désignée par son intitulé (REFCIALE, MTYP…) devenait introuvable.
            $cfg['skip_first_row'] = (bool) ($cfg['skip_first_row'] ?? true);
            // Ancien champ « colonne de jointure » de l'éditeur (`join_key` local) :
            // affiché dans le champ actuel (`join_col`, prioritaire dans le parser).
            if (blank($cfg['join_col'] ?? null) && filled($cfg['join_key'] ?? null)) {
                $cfg['join_col'] = $cfg['join_key'];
            }
            $sheetsRepeater[] = array_merge(['name' => $name], $cfg);
        }
        $config['sheets_repeater'] = $sheetsRepeater;

        // mapping{} → mapping_repeater[] : les `actions` restent dans leur forme
        // canonique. Elles ne sont plus des composants Filament inline (édition
        // exclusivement via le modal « Configurer » / l'éditeur de pipeline JS),
        // donc aucune expansion form-shape n'est nécessaire — l'éditeur sérialise
        // et relit directement le canonique consommé par ActionPipeline.
        $mappingRepeater = [];
        foreach (MappingOrder::declared($config) as $key => $cfg) {
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
                // « 1ʳᵉ ligne = en-têtes » est le défaut du parser : on ne
                // sérialise que l'exception (false).
                if (($item['skip_first_row'] ?? true) !== false) {
                    unset($item['skip_first_row']);
                }
                // `join_col` simple miroir d'un ancien `join_key` local (cf.
                // hydrateVisual) : on ne l'ajoute pas au JSON s'il n'a pas changé.
                if (filled($item['join_col'] ?? null) && ($item['join_col'] === ($item['join_key'] ?? null))) {
                    unset($item['join_col']);
                }
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
                // L'ordre des colonnes est conservé à part : MySQL réordonne les
                // clés d'un objet JSON (cf. MappingOrder::declared()).
                $config['mapping_order'] = array_map('strval', array_keys($mapping));
            } else {
                unset($config['mapping'], $config['mapping_order']);
            }
        }

        $config = self::normalizeAi($config);

        // Champs simples laissés vides : absents du JSON, pas `null`.
        foreach (['type', 'primary_sheet', 'join_key'] as $key) {
            if (array_key_exists($key, $config) && blank($config[$key])) {
                unset($config[$key]);
            }
        }

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
