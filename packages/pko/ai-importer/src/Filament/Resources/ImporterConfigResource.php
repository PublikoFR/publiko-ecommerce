<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Response;
use Lunar\Admin\Support\Resources\BaseResource;
use Pko\AiImporter\Actions\ActionRegistry;
use Pko\AiImporter\Filament\Resources\ImporterConfigResource\Pages;
use Pko\AiImporter\Models\ImporterConfig;

/**
 * Config editor.
 *
 * Two editing modes side by side through tabs:
 *
 *  - **Éditeur visuel** — a Repeater of columns, each with a sub-Repeater of
 *    actions. Each action has a `type` Select (populated from `ActionRegistry`)
 *    plus a KeyValue pane for its parameters. This covers 100% of action types
 *    without hardcoding 17 form schemas (good trade-off for v1 of the editor).
 *  - **JSON brut** — a raw textarea as an escape hatch for advanced edits.
 *
 * Under the hood both modes serialise to the same `config_data` JSON column.
 * The visual editor uses a `_visual` scratch key during form state that we
 * strip out on save (`mutateFormDataBeforeSave`).
 *
 * An Export action on each row downloads the `config_data` as a JSON file,
 * symmetrical to `ai-importer:import-ps-config`.
 */
class ImporterConfigResource extends BaseResource
{
    protected static ?string $model = ImporterConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

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
     * Visual tab schema — feuilles + mapping via Repeaters.
     *
     * @return array<int, mixed>
     */
    public static function visualSchema(): array
    {
        $actionTypes = collect(ActionRegistry::all())
            ->keys()
            ->mapWithKeys(fn (string $t): array => [$t => $t])
            ->toArray();

        return [
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('config_data.primary_sheet')
                    ->label('Feuille principale')
                    ->helperText('Nom de la feuille à itérer (ex: B01_COMMERCE)'),
                Forms\Components\TextInput::make('config_data.join_key')
                    ->label('Clé de jointure')
                    ->helperText('Nom de colonne partagée entre feuilles (ex: REFCIALE)'),
            ]),

            Forms\Components\Repeater::make('config_data.sheets_repeater')
                ->label('Feuilles')
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\Toggle::make('skip_first_row')->label('Ligne 1 = en-têtes')->default(true),
                    Forms\Components\TextInput::make('join_key')->helperText('Si différent du global'),
                    Forms\Components\TextInput::make('type')->helperText('ex: logistics, images'),
                ])
                ->columns(4)
                ->addActionLabel('+ Feuille')
                ->reorderableWithButtons()
                ->collapsed()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),

            Forms\Components\Repeater::make('config_data.mapping_repeater')
                ->label('Colonnes mappées')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Clé de sortie')
                            ->required()
                            ->helperText('reference, name, price_cents, stock, features…'),
                        Forms\Components\TextInput::make('col')
                            ->label('Colonne source')
                            ->helperText('Nom d\'en-tête ou lettre (M, AA…)'),
                        Forms\Components\TextInput::make('sheet')->label('Feuille'),
                    ]),
                    Forms\Components\TextInput::make('default')->label('Valeur par défaut'),
                    Forms\Components\Repeater::make('actions')
                        ->label('Pipeline d\'actions')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->options($actionTypes)
                                ->required()
                                ->live()
                                ->searchable()
                                ->columnSpan(1),
                            Forms\Components\KeyValue::make('params')
                                ->label('Paramètres')
                                ->keyLabel('Paramètre')
                                ->valueLabel('Valeur')
                                ->reorderable()
                                ->columnSpan(2),
                        ])
                        ->columns(3)
                        ->addActionLabel('+ Action')
                        ->reorderableWithButtons()
                        ->itemLabel(fn (array $state): ?string => $state['type'] ?? null),
                ])
                ->addActionLabel('+ Colonne')
                ->collapsed()
                ->cloneable()
                ->itemLabel(fn (array $state): ?string => $state['key'] ?? null)
                ->columnSpanFull(),
        ];
    }

    /**
     * Hook called by Pages\Edit & Pages\Create to flatten repeaters back into
     * the canonical JSON shape. Handles both directions (hydrate + dehydrate).
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
            $actions = [];
            foreach ((array) ($cfgArr['actions'] ?? []) as $a) {
                $aArr = (array) $a;
                $type = $aArr['type'] ?? '';
                unset($aArr['type']);
                $actions[] = [
                    'type' => $type,
                    'params' => self::stringifyParams($aArr),
                ];
            }
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
            $config['sheets'] = $sheets;
            unset($config['sheets_repeater']);
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
                if (isset($item['actions']) && is_array($item['actions'])) {
                    $item['actions'] = array_values(array_map(static function (array $a): array {
                        $params = is_array($a['params'] ?? null) ? self::typedParams($a['params']) : [];

                        return ['type' => $a['type'] ?? ''] + $params;
                    }, $item['actions']));
                }
                $mapping[$key] = $item;
            }
            $config['mapping'] = $mapping;
            unset($config['mapping_repeater']);
        }

        $data['config_data'] = $config;

        return $data;
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
