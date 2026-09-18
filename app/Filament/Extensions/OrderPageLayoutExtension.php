<?php

declare(strict_types=1);

namespace App\Filament\Extensions;

use App\Support\Orders\OrderCompanyName;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Component;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Admin\Support\Infolists\Components\Timeline;
use Lunar\Admin\Support\OrderStatus;
use Lunar\DataTypes\Price;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Pko\Pennylane\Services\OrderDocuments;
use Pko\ShippingCommon\Filament\Pages\DailyManifestPage;
use Pko\ShippingCommon\Filament\Resources\CarrierShipmentResource;
use Pko\ShippingCommon\Models\CarrierShipment;
use Pko\ShippingCommon\Support\CarrierDisplayLabel;
use Pko\ShippingCommon\Tracking\LaPosteTrackingClient;

/**
 * Mise en page de la fiche commande admin.
 *
 * Colonne principale, dans l'ordre :
 *  1. « Produits dans la commande » : lignes, puis notes client (40 %) et totaux (60 %) ;
 *  2. « Livraison » : mode choisi, point relais, envois transporteur (étiquette,
 *     suivi, bordereau) et adresse de livraison ;
 *  3. « Transactions », suivies de l'adresse de facturation.
 * Tous ces blocs sont pliables, l'état plié est mémorisé par le navigateur.
 *
 * Colonne latérale : nom du client et résumé fusionnés en un bloc « vue d'ensemble »,
 * les adresses en sortent (sections Lunar réutilisées telles quelles, action
 * « Modifier » comprise) et l'historique y remplace le bloc « Étiquettes ».
 */
final class OrderPageLayoutExtension extends ResourceExtension
{
    /**
     * Le groupe d'alertes Lunar (« shouts » : capture requise, remboursement) ouvre la
     * colonne principale. Vide, il occupe quand même un espacement de grille et décale
     * la colonne vers le bas par rapport à la colonne latérale : on le masque.
     */
    public function extendsInfolist(Infolist $infolist): Infolist
    {
        foreach ($infolist->getComponents(withHidden: true) as $column) {
            foreach ($column->getChildComponents() as $child) {
                if ($child instanceof Group && $child->getKey() === 'shouts') {
                    $child->hidden(fn (Group $component): bool => $component->getChildComponentContainer()->getComponents() === []);
                }
            }
        }

        return $infolist;
    }

    /**
     * Lunar construit la colonne principale dans un ordre fixe :
     * [0] expédition, [1] lignes, [2] totaux, [3] transactions, [4] historique.
     * Expédition et totaux sont remplacés par nos propres blocs ; tout composant
     * ajouté au-delà par une autre extension est conservé en fin de colonne.
     *
     * @param  array<int, Component>  $schema
     * @return array<int, Component>
     */
    public function extendInfolistSchema(array $schema): array
    {
        $schema = array_values($schema);

        if (count($schema) < 5) {
            return $schema;
        }

        // L'historique [4] passe dans la colonne latérale (extendInfolistAsideSchema).
        return [
            self::productsSection($schema[1]),
            self::shippingSection(),
            $schema[3],
            ...array_slice($schema, 5),
        ];
    }

    /**
     * @param  array<int, Component>  $schema
     * @return array<int, Component>
     */
    public function extendInfolistAsideSchema(array $schema): array
    {
        $headingOf = fn (Component $component): ?string => $component instanceof Section
            ? (string) $component->getHeading()
            : null;

        $movedHeadings = [
            __('lunarpanel::order.infolist.shipping_address.label'),
            __('lunarpanel::order.infolist.billing_address.label'),
        ];
        $tagsHeading = __('lunarpanel::order.infolist.tags.label');

        $aside = [];
        foreach ($schema as $component) {
            // Nom du client (entrée isolée) + résumé (section sans titre) fusionnés
            // en un seul bloc « vue d'ensemble ».
            if ($component instanceof TextEntry && $component->getName() === 'customer') {
                continue;
            }

            $heading = $headingOf($component);

            if ($component instanceof Section && blank($heading)) {
                $aside[] = self::overviewSection();

                continue;
            }

            if (in_array($heading, $movedHeadings, true)) {
                continue;
            }

            // Le bloc « Étiquettes » ne sert pas (et son autocomplétion propose les tags
            // produits) : l'historique prend sa place, pour une vue d'ensemble à côté du détail.
            if ($heading === $tagsHeading) {
                $aside[] = ManageOrder::getTimelineInfolist();

                continue;
            }

            $aside[] = $component;
        }

        return $aside;
    }

    public function extendTransactionsInfolist(Component $section): Component
    {
        if (! $section instanceof Section) {
            return $section;
        }

        return $section
            ->schema([
                ...$section->getChildComponents(),
                // Rappel des documents comptables sous les transactions qui les ont générés.
                ViewEntry::make('pko_order_documents')
                    ->hiddenLabel()
                    ->view('filament.orders.order-documents')
                    ->state(fn (Order $record): array => OrderDocuments::forOrder($record))
                    ->visible(fn (Order $record): bool => filled(OrderDocuments::forOrder($record)['invoice'])),
                ManageOrder::getBillingAddressInfoList(),
            ])
            ->id('order-transactions')
            ->collapsible()
            ->collapsed(false)
            ->persistCollapsed();
    }

    public function extendTimelineInfolist(Component $timeline): Component
    {
        return Section::make(__('lunarpanel::order.infolist.timeline.label'))
            ->id('order-timeline')
            ->collapsible()
            ->persistCollapsed()
            ->schema([
                // Le titre est porté par la section : on vide celui du composant Lunar.
                Timeline::make('timeline')->label(''),
            ]);
    }

    private static function overviewSection(): Section
    {
        return Section::make()
            ->compact()
            ->schema([
                ViewEntry::make('pko_order_overview')
                    ->hiddenLabel()
                    ->view('filament.orders.order-overview')
                    ->state(fn (Order $record): array => self::overview($record)),
            ]);
    }

    /**
     * Données du bloc « vue d'ensemble » de la colonne latérale.
     *
     * @return array<string, mixed>
     */
    public static function overview(Order $order): array
    {
        $customer = $order->customer;
        $placed = $order->placed_at !== null;
        $date = $order->placed_at ?? $order->created_at;

        $ordersCount = $customer
            ? Order::query()->where('customer_id', $customer->id)->whereNotNull('placed_at')->count()
            : 0;

        $details = array_values(array_filter([
            ['label' => 'Chantier', 'value' => $order->pko_site_name, 'copyable' => false],
            ['label' => 'Réf. client', 'value' => $order->customer_reference, 'copyable' => true],
            // Un seul canal : l'information n'apprend rien.
            Channel::query()->count() > 1
                ? ['label' => 'Canal', 'value' => $order->channel?->name, 'copyable' => false]
                : null,
        ], fn (?array $row): bool => $row !== null && filled($row['value'])));

        return [
            'reference' => (string) ($order->reference ?: '#'.$order->id),
            'company' => OrderCompanyName::for($order),
            'status' => [
                'label' => OrderStatus::getLabel($order->status),
                'color' => OrderStatus::getColor($order->status),
            ],
            'date' => $date
                ? ($placed ? 'Passée le ' : 'Créée le ').$date->format('d/m/Y \à H:i')
                : null,
            'customer' => $customer ? [
                'name' => (string) $customer->fullName,
                'url' => CustomerResource::getUrl('edit', ['record' => $customer->id]),
                'type' => $order->new_customer ? 'Nouveau client' : 'Client récurrent',
                'orders' => $ordersCount.' '.($ordersCount > 1 ? 'commandes' : 'commande'),
            ] : null,
            'guest' => $customer ? null : [
                'name' => (string) ($order->billingAddress?->fullName ?? $order->shippingAddress?->fullName ?? ''),
            ],
            'details' => $details,
            'invoice' => OrderDocuments::forOrder($order)['invoice'],
        ];
    }

    private static function productsSection(Component $lines): Section
    {
        return Section::make('Produits dans la commande')
            ->id('order-products')
            ->collapsible()
            ->persistCollapsed()
            ->schema([
                $lines,
                Grid::make(['default' => 1, 'lg' => 5])
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextEntry::make('pko_customer_notes')
                                    ->label('Notes client')
                                    ->placeholder('Aucune note laissée par le client')
                                    ->formatStateUsing(fn (?string $state): HtmlString => new HtmlString(nl2br(e((string) $state))))
                                    ->html(),
                                // Champ natif Lunar : n'est plus alimenté par le checkout,
                                // affiché seulement s'il a été renseigné.
                                TextEntry::make('notes')
                                    ->label(__('lunarpanel::order.infolist.notes.label'))
                                    ->hidden(fn ($state): bool => blank($state)),
                            ]),
                        ViewEntry::make('order_totals')
                            ->hiddenLabel()
                            ->view('filament.orders.order-totals')
                            ->state(fn (Order $record): array => self::totalsRows($record))
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                    ]),
            ]);
    }

    /**
     * Lignes du récapitulatif, dans l'ordre d'affichage.
     *
     * @return list<array{label: string, value: string, emphasis?: bool, tone?: string}>
     */
    public static function totalsRows(Order $order): array
    {
        $rows = [
            ['label' => __('lunarpanel::order.infolist.sub_total.label'), 'value' => $order->sub_total->formatted],
        ];

        if ($order->discount_total->value > 0) {
            $rows[] = ['label' => __('lunarpanel::order.infolist.discount_total.label'), 'value' => '− '.$order->discount_total->formatted];
        }

        foreach ($order->shipping_breakdown->items ?? [] as $item) {
            $rows[] = ['label' => (string) $item->name, 'value' => $item->price->formatted];
        }

        foreach ($order->tax_breakdown->amounts ?? [] as $tax) {
            $rows[] = ['label' => (string) $tax->description, 'value' => $tax->price->formatted];
        }

        $rows[] = ['label' => __('lunarpanel::order.infolist.total.label'), 'value' => $order->total->formatted, 'emphasis' => true];

        $transactions = $order->transactions()->where('success', true)->get();
        $paid = (int) $transactions->where('type', 'capture')->sum('amount.value');
        $refunded = (int) $transactions->where('type', 'refund')->sum('amount.value');

        $rows[] = ['label' => __('lunarpanel::order.infolist.paid.label'), 'value' => (new Price($paid, $order->currency))->formatted];

        if ($refunded > 0) {
            $rows[] = [
                'label' => __('lunarpanel::order.infolist.refund.label'),
                'value' => (new Price($refunded, $order->currency))->formatted,
                'tone' => 'warning',
            ];
        }

        return $rows;
    }

    private static function shippingSection(): Section
    {
        return Section::make('Livraison')
            ->id('order-shipping')
            ->collapsible()
            ->persistCollapsed()
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Group::make()
                            ->schema(fn (Order $record): array => self::shippingDetails($record)),
                        ManageOrder::getShippingAddressInfolist(),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function shippingDetails(Order $order): array
    {
        $components = [
            RepeatableEntry::make('shippingLines')
                ->label('Mode de livraison')
                ->placeholder('Aucun mode de livraison sur cette commande')
                ->contained(false)
                ->columns(3)
                ->schema([
                    TextEntry::make('description')
                        ->hiddenLabel()
                        ->icon('heroicon-s-truck')
                        ->iconPosition(IconPosition::Before)
                        ->html()
                        ->columnSpan(2),
                    TextEntry::make('sub_total')
                        ->hiddenLabel()
                        ->alignEnd()
                        ->formatStateUsing(fn ($state): string => $state->formatted),
                ]),
            TextEntry::make('shippingAddress.delivery_instructions')
                ->label(__('lunarpanel::order.infolist.delivery_instructions.label'))
                ->hidden(fn ($state): bool => blank($state)),
        ];

        if ($point = self::pickupPoint($order)) {
            $components[] = TextEntry::make('pko_pickup_point')
                ->label('Point relais')
                ->icon('heroicon-o-map-pin')
                ->listWithLineBreaks()
                ->state(array_values(array_filter([
                    trim(($point['name'] ?? 'Sans nom').(isset($point['id']) ? ' ('.$point['id'].')' : '')),
                    $point['address1'] ?? null,
                    trim(($point['postcode'] ?? '').' '.($point['city'] ?? '')),
                ], fn ($line): bool => filled($line))));
        }

        $shipments = CarrierShipment::query()
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get();

        if ($shipments->isEmpty()) {
            // Sans mode de livraison (devis, retrait), l'absence d'étiquette est normale.
            if ($order->shippingLines->isNotEmpty()) {
                $components[] = TextEntry::make('pko_no_shipment')
                    ->label('Envoi transporteur')
                    ->state('Aucune étiquette générée')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->helperText("La création d'étiquette est mise en file à l'encaissement : vérifier que le worker de queue tourne.");
            }

            return $components;
        }

        foreach ($shipments as $shipment) {
            $components[] = self::shipmentFieldset($shipment);
        }

        // Le bordereau de remise est journalier : il regroupe tous les colis remis au
        // chauffeur ce jour-là. Date retenue = création de la première étiquette.
        $manifestDate = $shipments->firstWhere('status', CarrierShipment::STATUS_CREATED)?->created_at;

        if ($manifestDate !== null) {
            $components[] = Actions::make([
                Action::make('daily_manifest')
                    ->label('Bordereau de remise du '.$manifestDate->format('d/m/Y'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->link()
                    ->url(DailyManifestPage::getUrl().'?date='.$manifestDate->format('Y-m-d')),
            ]);
        }

        return $components;
    }

    private static function shipmentFieldset(CarrierShipment $shipment): Fieldset
    {
        $id = $shipment->id;
        $tracking = filled($shipment->tracking_number) ? (string) $shipment->tracking_number : null;

        $actions = [
            Action::make("view_shipment_{$id}")
                ->label('Fiche de l\'envoi')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->link()
                ->url(CarrierShipmentResource::getUrl('view', ['record' => $id])),
        ];

        if ($tracking !== null) {
            array_unshift($actions, Action::make("track_shipment_{$id}")
                ->label('Suivre le colis')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->link()
                ->url(LaPosteTrackingClient::PUBLIC_TRACKING_URL.urlencode($tracking), shouldOpenInNewTab: true));
        }

        if (self::hasLabel($shipment)) {
            array_unshift($actions, Action::make("download_label_{$id}")
                ->label('Télécharger l\'étiquette')
                ->icon('heroicon-o-arrow-down-tray')
                ->link()
                ->action(fn () => response()->streamDownload(
                    fn () => print (Storage::disk('local')->get((string) $shipment->label_path)),
                    basename((string) $shipment->label_path),
                    ['Content-Type' => 'application/pdf'],
                )));
        }

        $service = CarrierDisplayLabel::service($shipment->carrier, $shipment->service_code);
        $heading = 'Envoi '.CarrierDisplayLabel::carrier($shipment->carrier)
            .($service !== '—' ? ' — '.$service : '');

        return Fieldset::make($heading)
            ->columns(2)
            ->schema([
                TextEntry::make("pko_shipment_{$id}_tracking")
                    ->label('N° de suivi')
                    ->state($tracking)
                    ->placeholder('—')
                    ->copyable(),
                TextEntry::make("pko_shipment_{$id}_status")
                    ->label('Statut')
                    ->badge()
                    ->state(self::shipmentStatusLabel($shipment))
                    ->color(self::shipmentStatusColor($shipment)),
                TextEntry::make("pko_shipment_{$id}_error")
                    ->label('Erreur')
                    ->state($shipment->error_message)
                    ->color('danger')
                    ->columnSpanFull()
                    ->visible($shipment->status === CarrierShipment::STATUS_FAILED && filled($shipment->error_message)),
                Actions::make($actions)->columnSpanFull(),
            ]);
    }

    /**
     * Une fois l'étiquette créée, c'est l'avancement de la livraison qui intéresse.
     */
    private static function shipmentStatusLabel(CarrierShipment $shipment): string
    {
        return match ($shipment->delivery_status) {
            CarrierShipment::DELIVERY_IN_TRANSIT => 'En transit',
            CarrierShipment::DELIVERY_OUT_FOR_DELIVERY => 'En cours de livraison',
            CarrierShipment::DELIVERY_DELIVERED => 'Livré'.($shipment->delivered_at ? ' le '.$shipment->delivered_at->format('d/m/Y') : ''),
            CarrierShipment::DELIVERY_RETURNED => 'Retourné',
            CarrierShipment::DELIVERY_FAILED => 'Échec de livraison',
            default => $shipment->status === CarrierShipment::STATUS_CREATED
                ? 'Étiquette créée'
                : CarrierDisplayLabel::status($shipment->status),
        };
    }

    private static function shipmentStatusColor(CarrierShipment $shipment): string
    {
        return match ($shipment->delivery_status) {
            CarrierShipment::DELIVERY_DELIVERED => 'success',
            CarrierShipment::DELIVERY_FAILED, CarrierShipment::DELIVERY_RETURNED => 'danger',
            CarrierShipment::DELIVERY_OUT_FOR_DELIVERY => 'warning',
            CarrierShipment::DELIVERY_IN_TRANSIT => 'info',
            default => match ($shipment->status) {
                CarrierShipment::STATUS_CREATED => 'success',
                CarrierShipment::STATUS_FAILED => 'danger',
                default => 'gray',
            },
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pickupPoint(Order $order): ?array
    {
        $meta = $order->meta instanceof \ArrayObject
            ? $order->meta->getArrayCopy()
            : (array) ($order->meta ?? []);

        $point = $meta['pickup_point'] ?? null;

        return is_array($point) && $point !== [] ? $point : null;
    }

    private static function hasLabel(CarrierShipment $shipment): bool
    {
        return is_string($shipment->label_path)
            && $shipment->label_path !== ''
            && Storage::disk('local')->exists($shipment->label_path);
    }
}
