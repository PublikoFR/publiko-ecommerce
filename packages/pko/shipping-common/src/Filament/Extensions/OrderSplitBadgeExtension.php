<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Filament\Extensions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\Order;

/**
 * Adds a read-only informational badge on the order view (ManageOrder)
 * when the order is part of a split: either the payable order that has
 * quote siblings, or a quote order that was split from a payable one.
 */
final class OrderSplitBadgeExtension extends ResourceExtension
{
    /**
     * @param  array<int, Action|ActionGroup>  $actions
     * @return array<int, Action|ActionGroup>
     */
    public function headerActions(array $actions): array
    {
        $order = $this->resolveOrder();

        if (! $order) {
            return $actions;
        }

        $meta = (array) ($order->meta ?? []);

        // Payable order → shows its quote sibling references
        $splitChildren = (array) ($meta['split_children'] ?? []);
        if (! empty($splitChildren)) {
            $siblings = Order::whereIn('id', $splitChildren)->pluck('reference')->implode(', ');
            $actions[] = Action::make('split_children_badge')
                ->label("Devis lié : {$siblings}")
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->disabled()
                ->button()
                ->tooltip('Cette commande a été scindée. Le(s) devis ci-contre attendent un chiffrage transport.');
        }

        // Quote order → shows the payable order it was split from
        $splitFrom = $meta['split_from'] ?? null;
        if ($splitFrom !== null) {
            $parent = Order::find((int) $splitFrom);
            if ($parent) {
                $actions[] = Action::make('split_from_badge')
                    ->label("Commande payante : {$parent->reference}")
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->disabled()
                    ->button()
                    ->tooltip('Ce devis a été créé automatiquement lors de la scission du panier mixte.');
            }
        }

        return $actions;
    }

    private function resolveOrder(): ?Order
    {
        return $this->caller?->record ?? null;
    }
}
