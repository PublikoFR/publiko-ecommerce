<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Lunar\Admin\Filament\Resources\ProductResource;
use Pko\AiImporter\Enums\FlowState;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Filament\Resources\ImportJobResource\Pages\ViewImportJob;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\AiImporter\Support\MappingOrder;
use Pko\AiImporter\Support\ProductFieldCatalog;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Lignes préparées » — ce que l'import écrira, à vérifier avant de lancer.
 *
 *  - Onglets par situation (à importer / erreurs / importées / écartées), avec
 *    leur compteur : on va droit à ce qui demande une décision.
 *  - Colonnes utiles d'abord : photo, produit (référence + nom), marque, prix
 *    FINAL (éco-participation incluse), stock, statut et cause d'erreur. Toutes
 *    les colonnes brutes du fichier restent disponibles via le sélecteur de
 *    colonnes, masquées par défaut.
 *  - Un clic sur une ligne ouvre sa fiche (panneau latéral) : tous les champs,
 *    groupés, et l'historique de la ligne.
 *
 * Les lignes ne sont plus modifiables pendant qu'un traitement les lit ou les
 * écrit (préparation ou import en cours).
 */
class StagingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'stagingRecords';

    protected static ?string $title = 'Lignes préparées';

    protected static ?string $icon = 'heroicon-o-table-cells';

    /** Statuts d'une ligne encore à écrire dans le catalogue. */
    private const TO_IMPORT = [StagingStatus::Pending, StagingStatus::Validated, StagingStatus::Warning];

    /** Statuts d'une ligne écrite dans le catalogue. */
    private const IMPORTED = [StagingStatus::Created, StagingStatus::Updated, StagingStatus::Imported];

    /** Champs longs : zone de texte plutôt que champ sur une ligne. */
    private const LONG_TEXT_KEYS = [
        'description', 'description_short', 'meta_description', 'meta_keywords', 'features',
        'collections', 'category', 'videos', 'images', 'image', 'documents', 'attachments', 'tags',
    ];

    private const OTHER_GROUP = 'Autres colonnes';

    /** Ordre des groupes de champs dans la fiche d'une ligne. */
    private const GROUP_ORDER = ['Identité', 'Prix & taxe', 'Stock', 'Frais de port', 'Dimensions', 'Contenu', 'Relations', self::OTHER_GROUP];

    /** @var array<int, string>|null Clés de `data` (calculées une fois par requête). */
    private ?array $cachedDataKeys = null;

    /** @var array<int, array<string, mixed>> Données normalisées par id de ligne (aperçu prix, marque…). */
    private array $normalized = [];

    // Édition autorisée même sur la page View (ViewImportJob) — Filament rend le
    // relation manager en lecture seule par défaut sur une page ViewRecord.
    public function isReadOnly(): bool
    {
        return false;
    }

    // StagingRecord n'a pas de policy Shield (modèle interne) : l'accès est gardé
    // par la policy de l'import parent. On n'autorise la modification que si aucun
    // traitement n'est en train de lire ou d'écrire ces lignes.
    protected function canEdit(Model $record): bool
    {
        return $this->editable();
    }

    protected function canDelete(Model $record): bool
    {
        return $this->editable();
    }

    protected function canDeleteAny(): bool
    {
        return $this->editable();
    }

    private function job(): ImportJob
    {
        /** @var ImportJob */
        return $this->getOwnerRecord();
    }

    private function editable(): bool
    {
        return $this->job()->flowState()->allowsStagingEdits();
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->job()->stagingStatusCounts();
        $values = static fn (array $statuses): array => array_map(static fn (StagingStatus $s): string => $s->value, $statuses);

        return [
            'all' => Tab::make('Toutes')->badge($counts['total']),
            'to_import' => Tab::make('À importer')
                ->badge($counts['pending'] + $counts['warning'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $values(self::TO_IMPORT))),
            'errors' => Tab::make('Erreurs')
                ->badge($counts['error'])
                ->badgeColor($counts['error'] > 0 ? 'danger' : 'gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StagingStatus::Error->value)),
            'imported' => Tab::make('Importées')
                ->badge($counts['imported'])
                ->badgeColor($counts['imported'] > 0 ? 'success' : 'gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $values(self::IMPORTED))),
            'skipped' => Tab::make('Écartées')
                ->badge($counts['skipped'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StagingStatus::Skipped->value)),
        ];
    }

    public function form(Form $form): Form
    {
        $groups = [];
        foreach ($this->getDataKeys() as $key) {
            $groups[$this->groupOf($key)][] = in_array($key, self::LONG_TEXT_KEYS, true)
                ? Forms\Components\Textarea::make("data.{$key}")->label(ProductFieldCatalog::label($key))->rows(3)->autosize()
                : Forms\Components\TextInput::make("data.{$key}")->label(ProductFieldCatalog::label($key));
        }

        // Ordre fixe : ce qui identifie et chiffre le produit d'abord, les colonnes
        // sans effet à l'import en dernier, repliées.
        $order = array_flip(self::GROUP_ORDER);
        uksort($groups, static fn (string $a, string $b): int => ($order[$a] ?? 50) <=> ($order[$b] ?? 50));

        $sections = [];
        foreach ($groups as $label => $fields) {
            $isOther = $label === self::OTHER_GROUP;
            $sections[] = Forms\Components\Section::make($label)
                ->description($isOther ? 'Présentes dans le fichier préparé, mais sans champ correspondant au catalogue : elles ne sont pas importées.' : null)
                ->schema($fields)
                ->columns(in_array($label, ['Contenu', 'Relations'], true) ? 1 : 2)
                ->compact()
                ->collapsible()
                ->collapsed($isOther);
        }

        return $form->schema([
            Forms\Components\Placeholder::make('row_state')
                ->hiddenLabel()
                ->content(fn (?StagingRecord $record): Htmlable => $this->renderRowState($record)),
            ...$sections,
            Forms\Components\Section::make('Historique de la ligne')
                ->collapsed()
                ->compact()
                ->schema([
                    Forms\Components\Placeholder::make('log_history')
                        ->hiddenLabel()
                        ->content(fn (?StagingRecord $record): Htmlable => $this->renderRowLogHistory($record)),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $keys = $this->getDataKeys();
        $hasImage = array_intersect(['image', 'images'], $keys) !== [];

        // Colonnes brutes du fichier, disponibles dans le sélecteur de colonnes.
        $rawColumns = [];
        foreach ($keys as $key) {
            $rawColumns[] = Tables\Columns\TextColumn::make("cell_{$key}")
                ->label(ProductFieldCatalog::label($key))
                ->state(fn (StagingRecord $r): string => $this->stringifyCell($this->raw($r)[$key] ?? null))
                ->limit(40)
                ->tooltip(function (StagingRecord $r) use ($key): ?string {
                    $full = $this->stringifyCell($this->raw($r)[$key] ?? null);

                    return mb_strlen($full) > 40 ? mb_strimwidth($full, 0, 600, '…') : null;
                })
                ->toggleable(isToggledHiddenByDefault: true);
        }

        return $table
            ->recordTitle(fn (StagingRecord $record): string => 'Ligne '.$record->row_number)
            ->defaultSort('row_number')
            ->columns([
                Tables\Columns\TextColumn::make('row_number')->label('Ligne')->sortable()->color('gray'),

                ...($hasImage ? [
                    Tables\Columns\ImageColumn::make('data_image')
                        ->label('Photo')
                        ->state(fn (StagingRecord $r): ?string => $this->extractImageUrl($r))
                        ->height(40)
                        ->width(40),
                ] : []),

                Tables\Columns\TextColumn::make('product')
                    ->label('Produit')
                    ->state(fn (StagingRecord $r): string => $this->stringifyCell($this->normalizedData($r)['name'] ?? '') ?: '(sans nom)')
                    ->description(fn (StagingRecord $r): string => $this->identityOf($r))
                    ->limit(60)
                    ->weight('medium')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereRaw('LOWER(CAST(data AS CHAR)) LIKE ?', ['%'.mb_strtolower($search).'%'])),

                Tables\Columns\TextColumn::make('brand')
                    ->label('Marque')
                    ->state(fn (StagingRecord $r): string => $this->stringifyCell($this->normalizedData($r)['brand_name'] ?? ''))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Prix HT')
                    ->alignEnd()
                    ->state(fn (StagingRecord $r): ?string => $this->money($this->normalizedData($r)['price_cents'] ?? null))
                    ->description(function (StagingRecord $r): ?string {
                        $eco = $this->normalizedData($r)['ecotax_cents'] ?? null;

                        return is_int($eco) && $eco > 0 ? 'dont éco '.$this->money($eco) : null;
                    })
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->alignEnd()
                    ->state(fn (StagingRecord $r): ?string => isset($this->normalizedData($r)['stock']) ? (string) $this->normalizedData($r)['stock'] : null)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (StagingStatus $state): string => $state->label())
                    ->color(fn (StagingStatus $state): string => $state->color())
                    ->description(fn (StagingRecord $r): ?string => $r->error_message ? mb_strimwidth($r->error_message, 0, 140, '…') : null)
                    ->wrap(),

                Tables\Columns\TextColumn::make('lunar_product_id')
                    ->label('Fiche produit')
                    ->formatStateUsing(fn (): string => 'Ouvrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (StagingRecord $r): ?string => $this->productUrl($r->lunar_product_id), shouldOpenInNewTab: true)
                    ->placeholder('—'),

                ...$rawColumns,
            ])
            ->recordAction(fn (): ?string => $this->editable() ? 'edit' : null)
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Exporter (CSV)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Corriger')
                    ->slideOver()
                    ->modalWidth(MaxWidth::ThreeExtraLarge)
                    ->modalHeading(fn (StagingRecord $record): string => 'Ligne '.$record->row_number.' — '.($this->identityOf($record) ?: 'sans référence'))
                    ->modalSubmitActionLabel('Enregistrer')
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Les valeurs structurées (hash de caractéristiques, listes) sont
                        // éditées en JSON dans une zone de texte.
                        $data['data'] = array_map(
                            fn (mixed $value): mixed => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value,
                            (array) ($data['data'] ?? []),
                        );

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data, StagingRecord $record): array {
                        $existing = $this->raw($record);
                        $submitted = (array) ($data['data'] ?? []);

                        foreach ($submitted as $key => $value) {
                            // Ce qui était structuré le redevient si le JSON saisi est valide.
                            if (is_array($existing[$key] ?? null) && is_string($value)) {
                                $decoded = json_decode($value, true);
                                $submitted[$key] = is_array($decoded) ? $decoded : $value;
                            }
                        }

                        // On préserve les clés absentes du formulaire.
                        $data['data'] = array_merge($existing, $submitted);

                        // Une ligne en erreur corrigée repart « à importer ».
                        if ($record->status === StagingStatus::Error) {
                            $data['status'] = StagingStatus::Pending;
                            $data['error_message'] = null;
                        }

                        return $data;
                    }),

                Tables\Actions\Action::make('skip')
                    ->label('Écarter')
                    ->icon('heroicon-m-no-symbol')
                    ->color('gray')
                    ->visible(fn (StagingRecord $record): bool => $this->editable()
                        && in_array($record->status, [...self::TO_IMPORT, StagingStatus::Error], true))
                    ->action(fn (StagingRecord $record) => $record->update(['status' => StagingStatus::Skipped, 'error_message' => 'Écartée à la main'])),

                Tables\Actions\Action::make('restore')
                    ->label('Rétablir')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (StagingRecord $record): bool => $this->editable()
                        && in_array($record->status, [StagingStatus::Skipped, StagingStatus::Error], true))
                    ->action(fn (StagingRecord $record) => $record->update(['status' => StagingStatus::Pending, 'error_message' => null])),

                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('restore')
                        ->label('Remettre à importer')
                        ->icon('heroicon-m-arrow-uturn-left')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => StagingStatus::Pending, 'error_message' => null])),
                    Tables\Actions\BulkAction::make('validate')
                        ->label('Marquer comme vérifiées')
                        ->icon('heroicon-m-check')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records
                            ->filter(fn (StagingRecord $r): bool => in_array($r->status, self::TO_IMPORT, true))
                            ->each->update(['status' => StagingStatus::Validated, 'validated_at' => now()])),
                    Tables\Actions\BulkAction::make('skip')
                        ->label('Écarter')
                        ->icon('heroicon-m-no-symbol')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records
                            ->reject(fn (StagingRecord $r): bool => in_array($r->status, self::IMPORTED, true))
                            ->each->update(['status' => StagingStatus::Skipped, 'error_message' => 'Écartée à la main'])),
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn (): bool => $this->editable()),
            ])
            ->emptyStateIcon('heroicon-o-table-cells')
            ->emptyStateHeading(fn (): string => match ($this->job()->flowState()) {
                FlowState::Waiting => 'La préparation n\'a pas encore démarré',
                FlowState::Preparing => 'Préparation en cours…',
                default => 'Aucune ligne',
            })
            ->emptyStateDescription(fn (): ?string => match ($this->job()->flowState()) {
                FlowState::Waiting, FlowState::Preparing => 'Les lignes apparaissent ici au fur et à mesure.',
                default => null,
            });
    }

    /**
     * La page de l'import signale que son état a pu changer (tic de
     * rafraîchissement pendant un traitement, action terminée) : le tableau et
     * les compteurs d'onglets se rechargent. Pas de minuteur propre ici.
     */
    #[On(ViewImportJob::FLOW_UPDATED)]
    public function refreshFromFlow(): void
    {
        $this->cachedDataKeys = null;
        $this->normalized = [];
    }

    // ── Données d'une ligne ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function raw(StagingRecord $record): array
    {
        return $record->data instanceof \ArrayObject ? $record->data->getArrayCopy() : (array) $record->data;
    }

    /**
     * Données telles que l'import les lira : alias PrestaShop ramenés aux clés
     * canoniques, montants convertis en cents, éco-participation ajoutée au prix.
     *
     * @return array<string, mixed>
     */
    private function normalizedData(StagingRecord $record): array
    {
        return $this->normalized[$record->id] ??= LunarProductWriter::sanitizeNumericFields(
            LunarProductWriter::normalizeLegacyKeys($this->raw($record)),
        );
    }

    /** Ce qui identifie la ligne : SKU forcé, référence, sinon marque + référence fabricant. */
    private function identityOf(StagingRecord $record): string
    {
        $data = $this->normalizedData($record);

        foreach (['sku', 'reference'] as $key) {
            $value = trim($this->stringifyCell($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return trim($this->stringifyCell($data['brand_name'] ?? '').' '.$this->stringifyCell($data['mpn'] ?? ''));
    }

    private function money(mixed $cents): ?string
    {
        return is_int($cents) ? number_format($cents / 100, 2, ',', ' ').' €' : null;
    }

    private function productUrl(?int $productId): ?string
    {
        if ($productId === null) {
            return null;
        }

        try {
            return ProductResource::getUrl('edit', ['record' => $productId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rend une valeur de cellule en texte lisible (array/objet → JSON, null →
     * chaîne vide, scalaire → string).
     */
    private function stringifyCell(mixed $value): string
    {
        return match (true) {
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_null($value) => '',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    /**
     * Clés de `data` à exposer comme colonnes / champs.
     *
     * Source : les clés réelles de la première ligne (fonctionne avec ET sans
     * configuration — fichier déjà préparé). Avec une configuration, l'ordre de
     * son mapping est respecté.
     *
     * @return array<int, string>
     */
    private function getDataKeys(): array
    {
        if ($this->cachedDataKeys !== null) {
            return $this->cachedDataKeys;
        }

        $job = $this->job();
        $first = $job->stagingRecords()->orderBy('row_number')->first(['data']);
        if (! $first) {
            return $this->cachedDataKeys = [];
        }

        $dataKeys = array_map('strval', array_keys($this->raw($first)));

        $config = $job->config;
        if ($config) {
            $configData = $config->config_data instanceof \ArrayObject ? $config->config_data->getArrayCopy() : (array) $config->config_data;
            $mappingKeys = array_map('strval', array_keys(MappingOrder::declared($configData)));
            $dataKeys = array_values(array_unique([...array_intersect($mappingKeys, $dataKeys), ...$dataKeys]));
        }

        return $this->cachedDataKeys = $dataKeys;
    }

    /** Groupe d'affichage d'une clé (celui du catalogue de champs, alias compris). */
    private function groupOf(string $key): string
    {
        $canonical = LunarProductWriter::LEGACY_ALIASES[$key] ?? LunarProductWriter::LEGACY_EURO_ALIASES[$key] ?? $key;

        foreach (ProductFieldCatalog::groupedOptions() as $group => $fields) {
            if (array_key_exists($canonical, $fields)) {
                return str_starts_with($group, 'Famille fabricant') ? 'Identité' : $group;
            }
        }

        return self::OTHER_GROUP;
    }

    /**
     * Première URL d'image d'une ligne (`image` ou `images` : URL simple, CSV,
     * tableau ou JSON encodé).
     */
    private function extractImageUrl(StagingRecord $r): ?string
    {
        $data = $this->raw($r);

        foreach (['image', 'images'] as $key) {
            $url = $this->firstImageUrl($data[$key] ?? null);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function firstImageUrl(mixed $value): ?string
    {
        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) && $first !== '' ? $first : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && $decoded !== []) {
                $first = reset($decoded);

                return is_string($first) && $first !== '' ? $first : null;
            }
        }

        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                return $part;
            }
        }

        return null;
    }

    // ── Fiche d'une ligne ────────────────────────────────────────────────────

    private function renderRowState(?StagingRecord $record): Htmlable
    {
        if ($record === null) {
            return new HtmlString('');
        }

        $status = $record->status;
        $palette = ['danger' => '--danger-600', 'success' => '--success-600', 'warning' => '--warning-600', 'info' => '--info-600', 'gray' => '--gray-500'];
        $color = $palette[$status->color()] ?? '--gray-500';

        $html = sprintf(
            '<div style="display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:baseline;font-size:.875rem;">'
            .'<span style="font-weight:600;color:rgb(var(%s));">%s</span>',
            $color,
            e($status->label()),
        );

        if ($record->error_message) {
            $html .= '<span>'.e($record->error_message).'</span>';
        }
        if ($status === StagingStatus::Error) {
            $html .= '<span style="color:rgb(var(--gray-500));">Corrigez la valeur en cause puis enregistrez : la ligne repassera « à importer ».</span>';
        }
        if ($url = $this->productUrl($record->lunar_product_id)) {
            $html .= '<a href="'.e($url).'" target="_blank" rel="noopener" style="color:rgb(var(--primary-600));font-weight:500;">Ouvrir la fiche produit</a>';
        }

        return new HtmlString($html.'</div>');
    }

    private function renderRowLogHistory(?StagingRecord $record): Htmlable
    {
        if ($record === null) {
            return new HtmlString('');
        }

        $logs = ImportLog::query()
            ->where('import_job_id', $record->import_job_id)
            ->where('row_number', $record->row_number)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($logs->isEmpty()) {
            return new HtmlString('<span style="color:rgb(var(--gray-500));">Aucun événement pour cette ligne.</span>');
        }

        $colors = [
            LogLevel::Success->value => '#059669',
            LogLevel::Warning->value => '#d97706',
            LogLevel::Error->value => '#dc2626',
            LogLevel::Info->value => '#2563eb',
            LogLevel::Debug->value => '#6b7280',
        ];

        $rows = $logs->map(function (ImportLog $log) use ($colors): string {
            $color = $colors[$log->level->value] ?? '#374151';
            $time = optional($log->created_at)->format('d/m H:i:s') ?? '';

            return sprintf(
                '<div style="font-family:ui-monospace,monospace;font-size:.75rem;line-height:1.5;"><span style="color:#9ca3af;">%s</span> <span style="color:%s;font-weight:600;">[%s]</span> %s</div>',
                e($time),
                $color,
                e(strtoupper($log->level->value)),
                e($log->message),
            );
        })->implode('');

        return new HtmlString('<div style="max-height:16rem;overflow-y:auto;">'.$rows.'</div>');
    }

    private function exportCsv(): StreamedResponse
    {
        $job = $this->job();
        $filename = 'import_'.$job->id.'_lignes.csv';

        $keys = [];
        StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->orderBy('id')
            ->chunk(500, function ($records) use (&$keys): void {
                foreach ($records as $record) {
                    $keys += array_fill_keys(array_map('strval', array_keys($this->raw($record))), true);
                }
            });
        $keys = array_keys($keys);

        return response()->streamDownload(function () use ($job, $keys): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_merge(['row_number', 'status', 'error_message'], $keys), ';');

            StagingRecord::query()
                ->where('import_job_id', $job->id)
                ->orderBy('row_number')
                ->chunk(500, function ($records) use ($out, $keys): void {
                    foreach ($records as $record) {
                        $data = $this->raw($record);
                        $line = [$record->row_number, $record->status->value, $record->error_message];
                        foreach ($keys as $key) {
                            $value = $data[$key] ?? '';
                            $line[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                        fputcsv($out, $line, ';');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
