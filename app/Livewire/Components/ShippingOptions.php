<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Lunar\Facades\Taxes;
use Lunar\Models\Currency;
use Pko\ShippingCommon\Contracts\PickupPointProvider;
use Pko\ShippingCommon\Dto\PickupPoint;
use Pko\ShippingCommon\Modifiers\UnifiedShippingModifier;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\ShippingCommon\Support\PortModeResolver;
use Pko\ShippingCommon\Support\WeightCalculator;

class ShippingOptions extends Component
{
    /**
     * Identifier du service nécessitant la sélection d'un point relais.
     */
    public const PICKUP_OPTION_IDENTIFIER = 'chronopost.chrono_relais';

    /**
     * The chosen shipping option.
     */
    public ?string $chosenOption = null;

    /**
     * Code postal de recherche des points relais (prérempli depuis l'adresse).
     */
    public string $pickupSearchPostcode = '';

    /**
     * Points relais retournés par le provider, sérialisés pour Livewire.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $pickupPoints = [];

    /**
     * Id du point relais sélectionné dans la liste.
     */
    public ?string $pickupPointId = null;

    /**
     * Point relais retenu (depuis la liste ou la saisie manuelle).
     *
     * @var array<string, mixed>|null
     */
    public ?array $selectedPickupPoint = null;

    /**
     * Saisie manuelle simplifiée (V1) quand aucun point n'est retourné par l'API.
     *
     * @var array{name:string,address1:string,postcode:string,city:string}
     */
    public array $manualPickupPoint = [
        'name' => '',
        'address1' => '',
        'postcode' => '',
        'city' => '',
    ];

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
            $default = $options->first(fn ($opt) => $opt->getIdentifier() === UnifiedShippingModifier::CHRONO13_IDENTIFIER)
                ?? $options->first();
            $this->chosenOption = $default?->getIdentifier();
        }

        $this->pickupSearchPostcode = (string) ($this->shippingAddress?->postcode ?? '');

        // Restaure un point relais déjà choisi (meta panier).
        $existing = CartSession::current()?->meta['pickup_point'] ?? null;
        if (is_array($existing) && $existing !== []) {
            $this->selectedPickupPoint = $existing;
            $this->pickupPointId = isset($existing['id']) ? (string) $existing['id'] : null;
        }
    }

    /**
     * True si le service choisi exige un point relais.
     */
    public function getRequiresPickupPointProperty(): bool
    {
        return $this->chosenOption === self::PICKUP_OPTION_IDENTIFIER;
    }

    /**
     * Recherche les points relais proches du code postal saisi.
     */
    public function searchPickupPoints(): void
    {
        $postcode = trim($this->pickupSearchPostcode);
        if ($postcode === '') {
            $this->addError('pickupSearchPostcode', 'Veuillez renseigner un code postal.');

            return;
        }

        $country = (string) ($this->shippingAddress?->country?->iso2 ?? 'FR');

        $points = app(PickupPointProvider::class)
            ->search($postcode, $country, self::PICKUP_OPTION_IDENTIFIER);

        $this->pickupPoints = array_map(
            fn (PickupPoint $point) => $point->toArray(),
            $points,
        );
    }

    /**
     * Mémorise le point relais sélectionné dans la liste.
     */
    public function updatedPickupPointId(?string $value): void
    {
        $this->selectedPickupPoint = collect($this->pickupPoints)
            ->first(fn (array $p) => (string) ($p['id'] ?? '') === (string) $value);
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

        $cart = CartSession::current();

        // Point relais obligatoire pour le service Chrono Relais.
        if ($this->requiresPickupPoint) {
            $point = $this->resolvePickupPoint();

            if ($point === null) {
                $this->addError('pickupPointId', 'Veuillez sélectionner ou saisir un point relais.');

                return;
            }

            $this->persistPickupPoint($cart, $point);
        } else {
            // Service sans point relais → purge un éventuel point relais obsolète.
            $this->persistPickupPoint($cart, null);
        }

        $option = $this->shippingOptions->first(fn ($option) => $option->getIdentifier() == $this->chosenOption);

        CartSession::setShippingOption($option);

        $this->dispatch('selectedShippingOption');
    }

    /**
     * Résout le point relais retenu : liste sélectionnée, sinon saisie manuelle complète.
     *
     * @return array<string, mixed>|null
     */
    private function resolvePickupPoint(): ?array
    {
        if (is_array($this->selectedPickupPoint) && ($this->selectedPickupPoint['id'] ?? null)) {
            return $this->selectedPickupPoint;
        }

        $manual = array_map('trim', $this->manualPickupPoint);
        if ($manual['name'] !== '' && $manual['address1'] !== '' && $manual['postcode'] !== '' && $manual['city'] !== '') {
            return PickupPoint::fromArray([
                'id' => 'manual:'.$manual['postcode'].':'.$manual['name'],
                'name' => $manual['name'],
                'address1' => $manual['address1'],
                'postcode' => $manual['postcode'],
                'city' => $manual['city'],
                'country_code' => (string) ($this->shippingAddress?->country?->iso2 ?? 'FR'),
                'carrier' => 'chronopost',
            ])->toArray();
        }

        return null;
    }

    /**
     * Persiste (ou purge) le point relais dans le meta du panier.
     *
     * @param  array<string, mixed>|null  $point
     */
    private function persistPickupPoint($cart, ?array $point): void
    {
        if ($cart === null) {
            return;
        }

        $meta = $cart->meta?->toArray() ?? [];

        if ($point === null) {
            unset($meta['pickup_point']);
        } else {
            $meta['pickup_point'] = $point;
        }

        $cart->meta = $meta;
        $cart->save();
    }

    /**
     * Prix HT et TTC d'une option d'expédition (zone-aware via le moteur de taxe).
     *
     * @return array{ht: Price, ttc: Price, has_tax: bool}
     */
    public function optionPrices(ShippingOption $option): array
    {
        $cart = CartSession::current();
        $currency = $cart?->currency ?? Currency::getDefault();
        $htCents = (int) $option->getPrice()->value;

        $taxCents = 0;
        if ($cart?->shippingAddress !== null) {
            try {
                $taxCents = (int) Taxes::setShippingAddress($cart->shippingAddress)
                    ->setCurrency($currency)
                    ->setPurchasable($option)
                    ->getBreakdown($htCents)
                    ->amounts
                    ->sum('price.value');
            } catch (\Throwable) {
                // Zone de taxe non résolue (ex. config incomplète) → pas de TVA ventilée.
                $taxCents = 0;
            }
        }

        return [
            'ht' => new Price($htCents, $currency, 1),
            'ttc' => new Price($htCents + $taxCents, $currency, 1),
            'has_tax' => $taxCents > 0,
        ];
    }

    /**
     * Mode d'affichage des prix d'expédition au panier ('both' | 'ht' | 'ttc').
     */
    public function getPriceDisplayProperty(): string
    {
        return ShippingSettings::taxDisplay();
    }

    /**
     * Return whether we have a shipping address.
     */
    public function getShippingAddressProperty()
    {
        return CartSession::current()->shippingAddress;
    }

    /**
     * True si le seuil franco est atteint (respecte la base configurée).
     */
    public function getIsFrancoReachedProperty(): bool
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return false;
        }

        $threshold = ShippingSettings::thresholdCents();

        if (ShippingSettings::francoBasis() === 'cart_total') {
            return WeightCalculator::cartSubtotalHt($cart) >= $threshold;
        }

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
     * Cents HT restants d'articles ÉLIGIBLES pour atteindre le seuil franco.
     * Miroir exact de ShippingCalculator::computeBanners() — même base (eligible vs cart_total).
     * Retourne 0 si le seuil est atteint ou si le panier est vide.
     */
    public function getFrancoRemainingCentsProperty(): int
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return 0;
        }

        $threshold = ShippingSettings::thresholdCents();

        $current = ShippingSettings::francoBasis() === 'cart_total'
            ? WeightCalculator::cartSubtotalHt($cart)
            : WeightCalculator::francoEligibleSubtotalHt($cart);

        return max(0, $threshold - $current);
    }

    /**
     * Meta de l'option sélectionnée (grid_price_cents, flat_price_cents, surcharge_cents…).
     *
     * @return array<string, mixed>|null
     */
    public function getSelectedOptionMetaProperty(): ?array
    {
        if ($this->chosenOption === null) {
            return null;
        }

        $option = $this->shippingOptions->first(fn ($opt) => $opt->getIdentifier() === $this->chosenOption);

        return $option?->meta ?? null;
    }

    /**
     * Total HT (cents) de l'option sélectionnée, LU depuis le ShippingQuote.
     * Aucune addition côté vue : le total vient de ShippingOption::getPrice(),
     * calculé par UnifiedShippingModifier via CalculatedShippingOption::totalPriceCents().
     */
    public function getSelectedOptionTotalCentsProperty(): int
    {
        if ($this->chosenOption === null) {
            return 0;
        }

        $option = $this->shippingOptions->first(fn ($opt) => $opt->getIdentifier() === $this->chosenOption);

        return $option === null ? 0 : (int) $option->getPrice()->value;
    }

    /**
     * Options sentinelles ("sur devis") : meta['quote'] === true.
     */
    public function getSentinelOptionsProperty(): Collection
    {
        return $this->shippingOptions->filter(fn ($opt) => ($opt->meta['quote'] ?? false) === true);
    }

    /**
     * True si l'option choisie cumule plusieurs composants de frais (récap ventilé à afficher).
     */
    public function getHasVentilatedRecapProperty(): bool
    {
        $meta = $this->selectedOptionMeta;
        if ($meta === null) {
            return false;
        }

        return ($meta['flat_price_cents'] ?? 0) > 0
            || ($meta['surcharge_cents'] ?? 0) > 0
            || $this->sentinelOptions->isNotEmpty();
    }

    /**
     * Lignes du panier avec port_mode résolu 'flat' (forfaits transport).
     */
    public function getFlatLinesProperty(): Collection
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return collect();
        }

        return $cart->lines->filter(function ($line) {
            $product = $line->purchasable?->product;

            return $product !== null && PortModeResolver::resolve($product) === 'flat';
        });
    }

    /**
     * Lignes du récap ventilé pour les forfaits transport : libellé + montant HT (cents).
     * Le calcul (prix forfait × quantité) reste côté PHP, miroir de
     * ShippingCalculator étape 4 — la vue ne fait aucune arithmétique.
     *
     * @return array<int, array{label: string, cents: int}>
     */
    public function getFlatLineRowsProperty(): array
    {
        return $this->flatLines
            ->map(fn ($line) => [
                'label' => (string) $line->purchasable->getDescription(),
                'cents' => (int) ($line->purchasable?->product?->pko_transport_price_cents ?? 0) * (int) $line->quantity,
            ])
            ->values()
            ->all();
    }

    /**
     * Formate un montant en cents dans la devise du panier courant.
     */
    public function formatHtCents(int $cents): string
    {
        $cart = CartSession::current();
        $currency = $cart?->currency ?? Currency::getDefault();

        return (new Price($cents, $currency, 1))->formatted();
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
