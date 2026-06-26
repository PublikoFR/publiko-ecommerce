<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Pko\ShippingCommon\Modifiers\FrancoModifier;
use Pko\ShippingCommon\Support\WeightCalculator;

class ShippingOptions extends Component
{
    /**
     * The chosen shipping option.
     */
    public ?string $chosenOption = null;

    public function mount(): void
    {
        if ($shippingOption = $this->shippingAddress?->shipping_option) {
            $option = $this->shippingOptions->first(function ($opt) use ($shippingOption) {
                return $opt->getIdentifier() == $shippingOption;
            });
            $this->chosenOption = $option?->getIdentifier();
        }

        if ($this->chosenOption === null) {
            $options = $this->shippingOptions;
            $default = $options->first(fn ($opt) => $opt->getIdentifier() === FrancoModifier::CHRONO13_IDENTIFIER)
                ?? $options->first();
            $this->chosenOption = $default?->getIdentifier();
        }
    }

    /**
     * Return available shipping options.
     */
    public function getShippingOptionsProperty(): Collection
    {
        return ShippingManifest::getOptions(
            CartSession::current()
        );
    }

    public function rules(): array
    {
        return [
            'chosenOption' => 'required',
        ];
    }

    /**
     * Save the shipping option.
     */
    public function save(): void
    {
        $this->validate();

        $option = $this->shippingOptions->first(fn ($option) => $option->getIdentifier() == $this->chosenOption);

        CartSession::setShippingOption($option);

        $this->dispatch('selectedShippingOption');
    }

    /**
     * Return whether we have a shipping address.
     */
    public function getShippingAddressProperty()
    {
        return CartSession::getCart()->shippingAddress;
    }

    /**
     * True si le seuil franco 350 € HT est atteint sans lignes exclues.
     */
    public function getIsFrancoReachedProperty(): bool
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return false;
        }

        $threshold = (int) config('shipping.franco.threshold_ht_cents', 35000);

        return WeightCalculator::francoEligibleSubtotalHt($cart) >= $threshold
            && ! WeightCalculator::cartHasFrancoExcludedLine($cart);
    }

    /**
     * True si au moins une ligne est exclue du franco de port.
     */
    public function getHasExcludedLinesProperty(): bool
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return false;
        }

        return WeightCalculator::cartHasFrancoExcludedLine($cart);
    }

    /**
     * True si le panier mélange des lignes stock Weklo et des lignes fournisseur externe.
     */
    public function getHasMultipleSourcesProperty(): bool
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return false;
        }

        $hasWeklo = false;
        $hasSupplier = false;

        foreach ($cart->lines as $line) {
            if ($line->purchasable?->product?->pko_supplier_id !== null) {
                $hasSupplier = true;
            } else {
                $hasWeklo = true;
            }

            if ($hasWeklo && $hasSupplier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Labels et descriptions lisibles par service, indexés par identifier.
     */
    public function getServiceLabelsProperty(): array
    {
        return [
            'chronopost.chrono_relais' => [
                'title' => 'Livraison économique — Chrono Relais',
                'description' => 'Point relais Pickup, jusqu\'à 20 kg.',
            ],
            'chronopost.chrono13' => [
                'title' => 'Livraison standard — Chrono 13',
                'description' => 'Livraison le lendemain avant 13h.',
            ],
            'chronopost.chrono10' => [
                'title' => 'Livraison express — Chrono 10',
                'description' => 'Le lendemain avant 10h, selon éligibilité code postal.',
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.components.shipping-options');
    }
}
