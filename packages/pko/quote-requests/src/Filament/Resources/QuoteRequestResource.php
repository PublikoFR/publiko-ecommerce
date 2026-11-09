<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Filament\Resources;

use Filament\Infolists\Components\Grid;
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
        return $infolist->schema([
            Grid::make(3)->schema([
                Section::make('Demande')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('mode')->label('Type')->badge()->color('gray')
                            ->formatStateUsing(fn (QuoteRequestMode $state): string => $state->label()),
                        TextEntry::make('message')->label('Description')->placeholder('—')
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                        ViewEntry::make('lines')->label('Produits et quantités')
                            ->view('pko-quote-requests::filament.lines'),
                        ViewEntry::make('files')->label('Pièces jointes')
                            ->view('pko-quote-requests::filament.files'),
                    ]),
                Section::make('Contact')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->label('Statut')->badge()
                            ->formatStateUsing(fn (QuoteRequestStatus $state): string => $state->label())
                            ->color(fn (QuoteRequestStatus $state): string => $state->color()),
                        TextEntry::make('name')->label('Nom'),
                        TextEntry::make('company')->label('Société')->placeholder('—'),
                        TextEntry::make('email')->label('E-mail')->copyable()
                            ->url(fn (QuoteRequest $record): string => 'mailto:'.$record->email),
                        TextEntry::make('phone')->label('Téléphone')->placeholder('—')
                            ->url(fn (QuoteRequest $record): ?string => $record->phone ? 'tel:'.preg_replace('/\s+/', '', $record->phone) : null),
                        TextEntry::make('postcode')->label('Code postal')->placeholder('—'),
                        TextEntry::make('customer_id')->label('Compte client')
                            ->placeholder('Non connecté')
                            ->formatStateUsing(fn (QuoteRequest $record): string => (string) ($record->customer?->company_name ?: '#'.$record->customer_id))
                            ->url(fn (QuoteRequest $record): ?string => self::adminUrl('filament.lunar.resources.customers.edit', $record->customer_id)),
                        TextEntry::make('created_at')->label('Reçue le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Contexte d\'origine')
                    ->columnSpan(3)
                    ->columns(4)
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
                    ->columnSpan(3)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('admin_notes')->hiddenLabel()->placeholder('Aucune note.')
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
            ]),
        ]);
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
