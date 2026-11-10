<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Filament\Resources;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource\Pages;
use Pko\QuoteRequests\Models\QuoteRequest;

class QuoteRequestResource extends Resource
{
    protected static ?string $model = QuoteRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $slug = 'quote-requests';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('pko-quote-requests::admin.quote_requests.nav');
    }

    public static function getModelLabel(): string
    {
        return __('pko-quote-requests::admin.quote_requests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pko-quote-requests::admin.quote_requests.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = QuoteRequest::query()->where('status', QuoteRequestStatus::New->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('files'))
            ->columns([
                TextColumn::make('id')
                    ->label('N°')
                    ->formatStateUsing(fn (QuoteRequest $record): string => $record->number())
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('mode')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (QuoteRequestMode $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (QuoteRequestStatus $state): string => $state->label())
                    ->color(fn (QuoteRequestStatus $state): string => $state->color()),
                TextColumn::make('name')
                    ->label('Contact')
                    ->description(fn (QuoteRequest $record): ?string => $record->company)
                    ->searchable(['name', 'company', 'email']),
                TextColumn::make('email')->label('E-mail')->copyable()->toggleable(),
                TextColumn::make('reference')
                    ->label('Produit / réf.')
                    ->state(fn (QuoteRequest $record): ?string => $record->reference ?? $record->productLabel())
                    ->placeholder('—')
                    ->searchable(['reference'])
                    ->toggleable(),
                TextColumn::make('files_count')->label('PJ')->alignCenter()->placeholder('0'),
            ])
            ->filters([
                SelectFilter::make('mode')->label('Type')->options(QuoteRequestMode::options()),
                SelectFilter::make('status')->label('Statut')->options(QuoteRequestStatus::options()),
            ])
            ->actions([ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // Contenu de la demande à gauche (ce qu'on lit pour chiffrer), suivi et
        // coordonnées compacts à droite (libellés en ligne). Les sections vides
        // (produits, contexte) sont masquées plutôt que remplies de tirets.
        return $infolist->schema([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Group::make()
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Message du client')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->schema([
                                TextEntry::make('message')->hiddenLabel()
                                    ->placeholder('Aucune description.')
                                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(nl2br(e(trim((string) $state)))))
                                    ->extraAttributes(['class' => 'text-base leading-relaxed']),
                            ]),
                        Section::make('Photos et pièces jointes')
                            ->icon('heroicon-o-photo')
                            ->description(fn (QuoteRequest $record): string => self::filesDescription($record))
                            ->schema([
                                ViewEntry::make('files')->hiddenLabel()
                                    ->view('pko-quote-requests::filament.files'),
                            ]),
                        Section::make('Produits et quantités')
                            ->icon('heroicon-o-queue-list')
                            ->visible(fn (QuoteRequest $record): bool => ! empty($record->lines))
                            ->schema([
                                ViewEntry::make('lines')->hiddenLabel()
                                    ->view('pko-quote-requests::filament.lines'),
                            ]),
                        Section::make('Contexte d\'origine')
                            ->icon('heroicon-o-link')
                            ->description('Page ou produit depuis lequel la demande a été envoyée.')
                            ->visible(fn (QuoteRequest $record): bool => self::hasContext($record))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('product_label')->label('Produit')->placeholder('—')
                                    ->state(fn (QuoteRequest $record): ?string => $record->productLabel())
                                    ->url(fn (QuoteRequest $record): ?string => self::adminUrl('filament.lunar.resources.products.edit', $record->product_id)),
                                TextEntry::make('reference')->label('Référence')->placeholder('—')->fontFamily('mono'),
                                TextEntry::make('collection')->label('Catégorie')->placeholder('—')
                                    ->state(fn (QuoteRequest $record): ?string => $record->context['collection_name'] ?? $record->collection),
                                TextEntry::make('from_url')->label('Page d\'origine')->placeholder('—')
                                    ->url(fn (QuoteRequest $record): ?string => $record->from_url, shouldOpenInNewTab: true),
                            ]),
                        Section::make('Notes internes')
                            ->icon('heroicon-o-pencil-square')
                            ->collapsible()
                            ->schema([
                                TextEntry::make('admin_notes')->hiddenLabel()->placeholder('Aucune note.')
                                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(nl2br(e(trim((string) $state))))),
                            ]),
                    ]),
                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Suivi')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                TextEntry::make('status')->label('Statut')->inlineLabel()->badge()
                                    ->formatStateUsing(fn (QuoteRequestStatus $state): string => $state->label())
                                    ->color(fn (QuoteRequestStatus $state): string => $state->color()),
                                TextEntry::make('mode')->label('Type')->inlineLabel()->badge()->color('gray')
                                    ->formatStateUsing(fn (QuoteRequestMode $state): string => $state->label()),
                                TextEntry::make('created_at')->label('Reçue le')->inlineLabel()->dateTime('d/m/Y à H:i'),
                            ]),
                        Section::make('Contact')
                            ->icon('heroicon-o-user')
                            ->schema([
                                TextEntry::make('name')->label('Nom')->inlineLabel()->weight('semibold'),
                                TextEntry::make('company')->label('Société')->inlineLabel()->placeholder('—'),
                                TextEntry::make('email')->label('E-mail')->inlineLabel()->copyable()
                                    ->url(fn (QuoteRequest $record): string => 'mailto:'.$record->email),
                                TextEntry::make('phone')->label('Téléphone')->inlineLabel()->placeholder('—')
                                    ->url(fn (QuoteRequest $record): ?string => $record->phone ? 'tel:'.preg_replace('/\s+/', '', $record->phone) : null),
                                TextEntry::make('postcode')->label('Code postal')->inlineLabel()->placeholder('—'),
                                TextEntry::make('customer_id')->label('Compte client')->inlineLabel()
                                    ->placeholder('Non connecté')
                                    ->formatStateUsing(fn (QuoteRequest $record): string => (string) ($record->customer?->company_name ?: '#'.$record->customer_id))
                                    ->url(fn (QuoteRequest $record): ?string => self::adminUrl('filament.lunar.resources.customers.edit', $record->customer_id)),
                            ]),
                    ]),
            ]),
        ]);
    }

    private static function filesDescription(QuoteRequest $record): string
    {
        $files = $record->files;
        if ($files->isEmpty()) {
            return 'Aucun fichier envoyé.';
        }

        $photos = $files->filter->isPreviewable()->count();
        $others = $files->count() - $photos;
        $parts = array_filter([
            $photos > 0 ? $photos.' photo'.($photos > 1 ? 's' : '').' (cliquer pour agrandir)' : null,
            $others > 0 ? $others.' document'.($others > 1 ? 's' : '') : null,
        ]);

        return implode(' · ', $parts);
    }

    private static function hasContext(QuoteRequest $record): bool
    {
        return $record->product_id !== null
            || filled($record->reference)
            || filled($record->collection)
            || filled($record->context['collection_name'] ?? null)
            || filled($record->from_url);
    }

    /** URL d'une fiche admin Lunar, null si la route ou l'id manque. */
    public static function adminUrl(string $route, ?int $id): ?string
    {
        return $id !== null && app('router')->has($route) ? route($route, $id) : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuoteRequests::route('/'),
            'view' => Pages\ViewQuoteRequest::route('/{record}'),
        ];
    }
}
