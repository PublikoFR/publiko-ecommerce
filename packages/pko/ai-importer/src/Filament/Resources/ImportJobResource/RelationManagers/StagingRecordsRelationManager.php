<?php

declare(strict_types=1);

namespace Pko\AiImporter\Filament\Resources\ImportJobResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Aperçu des données » — preview & édition des lignes parsées avant import.
 *
 * Le statut est éditable en ligne (`SelectColumn`). L'édition complète d'une
 * ligne passe par le modal `EditAction`, qui montre aussi l'historique des
 * logs de la ligne (cf. capture 11 du module PrestaShop). Le `data` est un
 * blob JSON dont le schéma dépend du mapping — pas de colonnes statiques.
 *
 * NB : Filament n'a pas d'édition de cellule au double-clic native. Le statut
 * est éditable inline ; tout le reste passe par le modal d'édition de ligne.
 */
class StagingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'stagingRecords';

    protected static ?string $title = 'Aperçu des données';

    protected static ?string $icon = 'heroicon-o-table-cells';

    // Édition autorisée même sur la page View (ViewImportJob) : correction du
    // staging à la volée. Filament rend par défaut un RelationManager read-only
    // sur une page ViewRecord (isReadOnly() → is_subclass_of ViewRecord) ; la
    // propriété statique $isReadOnly n'est PAS lue par cette version → on override
    // la méthode pour masquer le read-only et révéler edit/delete/bulk.
    public function isReadOnly(): bool
    {
        return false;
    }

    // StagingRecord n'a pas de policy Shield dédiée (modèle interne d'import).
    // L'accès est déjà gardé par celui du job parent (ImportJobResource) : tout
    // staff pouvant consulter le job peut corriger son staging. On autorise donc
    // explicitement edit/delete ici plutôt que de dépendre d'une policy absente
    // (sinon seul un super-admin via Gate::before y aurait accès).
    protected function canEdit(Model $record): bool
    {
        return true;
    }

    protected function canDelete(Model $record): bool
    {
        return true;
    }

    protected function canDeleteAny(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('row_number')->label('Ligne #')->disabled(),
            Forms\Components\Select::make('status')
                ->options(collect(StagingStatus::cases())
                    ->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])
                    ->toArray())
                ->required(),
            Forms\Components\Textarea::make('data')
                ->label('Données mappées (JSON)')
                ->rows(16)
                ->columnSpanFull()
                ->formatStateUsing(fn ($state): string => json_encode((array) $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                ->dehydrateStateUsing(fn ($state): array => is_string($state) ? (json_decode($state, true) ?? []) : (array) $state)
                ->rules(['json']),
            Forms\Components\Textarea::make('error_message')->rows(2)->disabled(),
            Forms\Components\Placeholder::make('log_history')
                ->label('Historique des logs de la ligne')
                ->columnSpanFull()
                ->content(fn (?StagingRecord $record): Htmlable => $this->renderRowLogHistory($record)),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('row_number')
            ->defaultSort('row_number')
            ->columns([
                Tables\Columns\TextColumn::make('row_number')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('data.reference')
                    ->label('SKU')
                    ->formatStateUsing(fn ($state, StagingRecord $record): string => (string) (((array) $record->data)['reference'] ?? ''))
                    ->searchable(query: fn ($query, string $search) => $query->where('data', 'like', '%"reference":"%'.$search.'%"%')),
                Tables\Columns\TextColumn::make('data.name')
                    ->label('Nom')
                    ->formatStateUsing(fn ($state, StagingRecord $record): string => (string) (((array) $record->data)['name'] ?? ''))
                    ->limit(60),
                Tables\Columns\TextColumn::make('data.price_cents')
                    ->label('Prix')
                    ->formatStateUsing(function ($state, StagingRecord $record): string {
                        $cents = ((array) $record->data)['price_cents'] ?? null;

                        return $cents === null ? '—' : number_format(((int) $cents) / 100, 2, ',', ' ').' €';
                    }),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Statut')
                    ->options(collect(StagingStatus::cases())
                        ->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])
                        ->toArray())
                    ->selectablePlaceholder(false)
                    ->rules(['required']),
                Tables\Columns\TextColumn::make('error_message')->label('Erreur')->limit(60)->tooltip(fn ($record): ?string => $record->error_message),
                Tables\Columns\TextColumn::make('lunar_product_id')->label('Produit Lunar')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(StagingStatus::cases())->mapWithKeys(fn (StagingStatus $s): array => [$s->value => $s->label()])->toArray()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Exporter CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::FourExtraLarge)
                    ->modalHeading(fn (StagingRecord $record): string => 'Ligne #'.$record->row_number),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('validate')
                    ->label('Marquer validées')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Validated, 'validated_at' => now()])),
                Tables\Actions\BulkAction::make('skip')
                    ->label('Ignorer')
                    ->icon('heroicon-o-forward')
                    ->action(fn ($records) => $records->each->update(['status' => StagingStatus::Skipped])),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    private function renderRowLogHistory(?StagingRecord $record): Htmlable
    {
        if ($record === null) {
            return new HtmlString('<span class="text-gray-400">—</span>');
        }

        $logs = ImportLog::query()
            ->where('import_job_id', $record->import_job_id)
            ->where('row_number', $record->row_number)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        if ($logs->isEmpty()) {
            return new HtmlString('<span class="text-gray-400">Aucun log pour cette ligne.</span>');
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
            $time = optional($log->created_at)->format('H:i:s') ?? '';

            return sprintf(
                '<div style="font-family:ui-monospace,monospace;font-size:.75rem;"><span style="color:#9ca3af;">%s</span> <span style="color:%s;font-weight:600;">[%s]</span> %s</div>',
                e($time),
                $color,
                e(strtoupper($log->level->value)),
                e($log->message),
            );
        })->implode('');

        return new HtmlString('<div style="max-height:14rem;overflow-y:auto;">'.$rows.'</div>');
    }

    private function exportCsv(): StreamedResponse
    {
        $job = $this->getOwnerRecord();
        $filename = 'staging_'.$job->uuid.'.csv';

        $records = StagingRecord::query()
            ->where('import_job_id', $job->id)
            ->orderBy('row_number')
            ->get();

        $keys = $records
            ->flatMap(fn (StagingRecord $r): array => array_keys((array) $r->data))
            ->unique()
            ->values()
            ->all();

        return response()->streamDownload(function () use ($records, $keys): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_merge(['row_number', 'status', 'error_message'], $keys));
            foreach ($records as $record) {
                $data = (array) $record->data;
                $line = [$record->row_number, $record->status->value, $record->error_message];
                foreach ($keys as $key) {
                    $value = $data[$key] ?? '';
                    $line[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
