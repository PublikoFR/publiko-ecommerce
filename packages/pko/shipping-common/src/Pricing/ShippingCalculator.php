<?php

declare(strict_types=1);

namespace Pko\ShippingCommon\Pricing;

use Illuminate\Support\Collection;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Pko\ShippingCommon\Carriers\CarrierRegistry;
use Pko\ShippingCommon\Dto\QuoteRequest;
use Pko\ShippingCommon\Models\ShippingSurcharge;
use Pko\ShippingCommon\Repositories\CarrierServiceRepository;
use Pko\ShippingCommon\Settings\ShippingSettings;
use Pko\ShippingCommon\Support\PortModeResolver;
use Pko\ShippingCommon\Support\ShippingTaxHelper;
use Pko\ShippingCommon\Support\WeightCalculator;
use Pko\ShippingCommon\Support\ZoneResolver;

/**
 * Service de calcul des frais de port.
 *
 * Remplace le pipeline de modifiers éclatés (AbstractCarrierModifier, FrancoModifier,
 * SurchargeModifier, FreeShippingModifier). Ordre de calcul garanti et explicite :
 *
 *   1. Partition des lignes par port-mode (standard / flat / free / quote)
 *   2. Poids taxable = lignes standard uniquement (flat/free/quote exclus)
 *   3. Prix de grille par service carrier (via CarrierClient::quote())
 *      Cas flat-only (poids=0, frais forfaitaires > 0) : services listés à 0 € de grille
 *      Cas tout-gratuit (toutes lignes free) : option unique "Livraison offerte"
 *   4. Forfait flat = somme(pko_transport_price_cents × quantité) des lignes flat
 *      Décision : forfait multiplié par la quantité (2 articles volumineux = 2× le forfait)
 *   5. Franco : annule le gridPrice des services couverts (flat et surcharges intacts)
 *   6. Suppléments auto/quote (table pko_shipping_surcharges)
 *   7. Blockers si lignes quote présentes
 *   8. Banners franco / progression / lignes exclues / multi-colis
 */
final class ShippingCalculator
{
    /** Identifier par défaut présélectionné au checkout. */
    public const DEFAULT_OPTION_IDENTIFIER = 'chronopost.chrono13';

    /** Identifier de l'option "Livraison offerte" (toutes lignes free). */
    public const FREE_SHIPPING_IDENTIFIER = 'free_shipping';

    /** Identifier de la sentinelle "sur devis" quand le poids dépasse la grille. */
    public const OVERWEIGHT_QUOTE_IDENTIFIER = 'quote.overweight';

    /** Service Chronopost nécessitant un point relais. */
    private const RELAIS_SERVICE_CODE = 'chrono_relais';

    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly CarrierServiceRepository $serviceRepo,
    ) {}

    public function calculate(Cart $cart): ShippingQuote
    {
        // ── 1. Adresse ────────────────────────────────────────────────────────
        $address = $cart->shippingAddress;
        if ($address === null) {
            return $this->emptyQuote();
        }

        $country = $address->country?->iso2 ?? 'FR';
        $postcode = (string) ($address->postcode ?? '');

        if (! $this->shouldQuote($country, $postcode)) {
            return $this->emptyQuote();
        }

        // ── 2. Partition des lignes ───────────────────────────────────────────
        $allLines = $cart->lines ?? collect();
        [$standardLines, $flatLines, $freeLines, $quoteLines] = $this->partitionLines($allLines);

        // ── 3. Blockers ───────────────────────────────────────────────────────
        $blockers = [];
        if ($quoteLines->isNotEmpty()) {
            $blockers[] = 'Certains articles nécessitent un devis transport. Le paiement en ligne est indisponible.';
        }

        // ── 4. Forfait flat (pko_transport_price_cents × quantité) ────────────
        $flatCents = 0;
        foreach ($flatLines as $line) {
            $price = (int) ($line->purchasable?->product?->pko_transport_price_cents ?? 0);
            $flatCents += $price * (int) $line->quantity;
        }

        // ── 5. Poids (lignes standard uniquement) ─────────────────────────────
        $weightKg = WeightCalculator::fromLines($standardLines);

        // ── 6. Options carrier ────────────────────────────────────────────────
        $currency = $cart->currency ?? Currency::getDefault();
        $taxClass = TaxClass::getDefault();
        $priceBase = ShippingSettings::taxPriceBase();
        $taxRate = 0.0;
        if ($priceBase === 'ttc' && $taxClass !== null) {
            $taxRate = ShippingTaxHelper::effectiveTaxRate($taxClass, $address, $currency);
        }

        $options = $this->resolveCarrierOptions(
            allLines: $allLines,
            standardLines: $standardLines,
            flatLines: $flatLines,
            freeLines: $freeLines,
            quoteLines: $quoteLines,
            weightKg: $weightKg,
            flatCents: $flatCents,
            country: $country,
            postcode: $postcode,
            priceBase: $priceBase,
            taxRate: $taxRate,
        );

        // ── 7. Franco ─────────────────────────────────────────────────────────
        $threshold = ShippingSettings::thresholdCents();
        $basis = ShippingSettings::francoBasis();
        $francoServices = ShippingSettings::francoServices();

        $francoApplies = $this->computeFrancoApplies($cart, $threshold, $basis);

        if ($francoApplies) {
            foreach ($options as $opt) {
                if (! $opt->isSentinel && ShippingSettings::francoCovers($francoServices, $opt->serviceCode)) {
                    $opt->gridPriceCents = 0;
                    $opt->franco = true;
                }
            }
        }

        // ── 8. Suppléments ────────────────────────────────────────────────────
        $options = $this->applySurcharges($options, $postcode, $country, $currency);

        // Poids hors grille : seule la sentinelle "sur devis" subsiste → paiement bloqué.
        if ($options !== [] && ! array_filter($options, fn (CalculatedShippingOption $o) => ! $o->isSentinel)) {
            $blockers[] = 'Le poids de votre commande dépasse nos grilles tarifaires. '
                .'Le transport sera devisé, le paiement en ligne est indisponible.';
        }

        // ── 9. Banners ────────────────────────────────────────────────────────
        $banners = $this->computeBanners($cart, $options, $francoApplies, $threshold, $basis);

        return new ShippingQuote(
            options: $options,
            defaultOptionIdentifier: self::DEFAULT_OPTION_IDENTIFIER,
            banners: $banners,
            blockers: $blockers,
        );
    }

    // ── Résolution des options ────────────────────────────────────────────────

    /**
     * @return CalculatedShippingOption[]
     */
    private function resolveCarrierOptions(
        Collection $allLines,
        Collection $standardLines,
        Collection $flatLines,
        Collection $freeLines,
        Collection $quoteLines,
        float $weightKg,
        int $flatCents,
        string $country,
        string $postcode,
        string $priceBase,
        float $taxRate,
    ): array {
        // Panier 100 % gratuit (toutes lignes free, aucune autre)
        if ($allLines->isNotEmpty()
            && $standardLines->isEmpty()
            && $flatLines->isEmpty()
            && $quoteLines->isEmpty()
        ) {
            return [$this->makeFreeShippingOption()];
        }

        // Cas flat-only : poids nul, forfaits présents — on liste les services sans grille
        if ($weightKg <= 0.0 && $flatLines->isNotEmpty()) {
            return $this->resolveFlatOnlyOptions($flatCents);
        }

        // Cas normal : poids > 0 — appel aux clients carrier
        if ($weightKg > 0.0) {
            $options = $this->resolveWeightedOptions($weightKg, $flatCents, $country, $postcode, $priceBase, $taxRate);

            // Aucun service ne couvre ce poids (au-delà du dernier bracket de grille) :
            // sans option, le checkout serait muet et laisserait passer un paiement à
            // 0 € de port. On bascule explicitement en transport sur devis.
            if ($options === []) {
                return [$this->makeOverweightQuoteOption()];
            }

            return $options;
        }

        // Aucun cas applicable (ex. panier vide)
        return [];
    }

    /**
     * Sentinelle "transport sur devis" — poids hors grille (aucun bracket ne couvre
     * le poids taxable). Prix 0 : le montant réel est communiqué après validation.
     */
    private function makeOverweightQuoteOption(): CalculatedShippingOption
    {
        return new CalculatedShippingOption(
            identifier: self::OVERWEIGHT_QUOTE_IDENTIFIER,
            carrierCode: '',
            serviceCode: '',
            name: 'Transport sur devis',
            description: 'Le poids total de votre commande dépasse nos grilles tarifaires. '
                .'Un transport spécifique vous sera proposé après validation de la commande.',
            gridPriceCents: 0,
            flatPriceCents: 0,
            autoSurchargeCents: 0,
            franco: false,
            isSentinel: true,
            requiresPickupPoint: false,
        );
    }

    private function makeFreeShippingOption(): CalculatedShippingOption
    {
        return new CalculatedShippingOption(
            identifier: self::FREE_SHIPPING_IDENTIFIER,
            carrierCode: '',
            serviceCode: '',
            name: 'Livraison offerte',
            description: 'Tous les articles de votre commande sont expédiés directement par le fournisseur.',
            gridPriceCents: 0,
            flatPriceCents: 0,
            autoSurchargeCents: 0,
            franco: false,
            isSentinel: false,
            requiresPickupPoint: false,
        );
    }

    /**
     * Flat-only : weight=0, au moins une ligne flat.
     * Enumère les services activés depuis la DB (pas d'appel client) avec gridPrice=0.
     *
     * @return CalculatedShippingOption[]
     */
    private function resolveFlatOnlyOptions(int $flatCents): array
    {
        $options = [];

        foreach ($this->registry->all() as $carrierCode => $def) {
            foreach ($this->serviceRepo->enabledFor($carrierCode) as $service) {
                $options[] = new CalculatedShippingOption(
                    identifier: $carrierCode.'.'.$service['code'],
                    carrierCode: $carrierCode,
                    serviceCode: $service['code'],
                    name: ($def->displayName ?? ucfirst($carrierCode)).' — '.$service['label'],
                    description: 'Expédition par '.($def->displayName ?? $carrierCode),
                    gridPriceCents: 0,
                    flatPriceCents: $flatCents,
                    autoSurchargeCents: 0,
                    franco: false,
                    isSentinel: false,
                    requiresPickupPoint: $service['code'] === self::RELAIS_SERVICE_CODE,
                );
            }
        }

        return $options;
    }

    /**
     * Cas normal : poids > 0, appel client carrier.
     *
     * @return CalculatedShippingOption[]
     */
    private function resolveWeightedOptions(
        float $weightKg,
        int $flatCents,
        string $country,
        string $postcode,
        string $priceBase,
        float $taxRate,
    ): array {
        $options = [];

        foreach ($this->registry->all() as $carrierCode => $def) {
            $client = app('pko.shipping.carrier.'.$carrierCode);

            $quotes = $client->quote(new QuoteRequest(
                weightKg: $weightKg,
                destinationPostcode: $postcode,
                destinationCountry: $country,
            ));

            foreach ($quotes as $quote) {
                $netCents = ($priceBase === 'ttc')
                    ? ShippingTaxHelper::grossToNet($quote->priceCents, $taxRate)
                    : $quote->priceCents;

                $options[] = new CalculatedShippingOption(
                    identifier: $carrierCode.'.'.$quote->serviceCode,
                    carrierCode: $carrierCode,
                    serviceCode: $quote->serviceCode,
                    name: ($def->displayName ?? ucfirst($carrierCode)).' — '.$quote->serviceLabel,
                    description: 'Livraison '.$quote->serviceLabel.' (France métropolitaine)',
                    gridPriceCents: $netCents,
                    flatPriceCents: $flatCents,
                    autoSurchargeCents: 0,
                    franco: false,
                    isSentinel: false,
                    requiresPickupPoint: $quote->serviceCode === self::RELAIS_SERVICE_CODE,
                );
            }
        }

        return $options;
    }

    // ── Franco ────────────────────────────────────────────────────────────────

    private function computeFrancoApplies(Cart $cart, int $threshold, string $basis): bool
    {
        if ($basis === 'cart_total') {
            return WeightCalculator::cartSubtotalHt($cart) >= $threshold;
        }

        return WeightCalculator::francoEligibleSubtotalHt($cart) >= $threshold
            && ! WeightCalculator::cartHasFrancoExcludedLine($cart);
    }

    // ── Suppléments ───────────────────────────────────────────────────────────

    /**
     * @param  CalculatedShippingOption[]  $options
     * @return CalculatedShippingOption[]
     */
    private function applySurcharges(array $options, string $postcode, string $country, mixed $currency): array
    {
        $surcharges = ShippingSurcharge::query()
            ->where('enabled', true)
            ->whereIn('mode', ['auto', 'quote'])
            ->get();

        if ($surcharges->isEmpty()) {
            return $options;
        }

        foreach ($surcharges as $surcharge) {
            if (! $this->matchesAddress($surcharge, $postcode, $country)) {
                continue;
            }

            if ($surcharge->mode === 'auto') {
                foreach ($options as $opt) {
                    if (! $opt->isSentinel) {
                        $opt->autoSurchargeCents += (int) $surcharge->amount_cents;
                    }
                }
            } elseif ($surcharge->mode === 'quote') {
                $options[] = new CalculatedShippingOption(
                    identifier: 'surcharge.'.$surcharge->code,
                    carrierCode: '',
                    serviceCode: '',
                    name: $surcharge->label,
                    description: 'Transport sur devis — prix communiqué après validation de commande',
                    gridPriceCents: 0,
                    flatPriceCents: 0,
                    autoSurchargeCents: 0,
                    franco: false,
                    isSentinel: true,
                    requiresPickupPoint: false,
                );
            }
        }

        return $options;
    }

    private function matchesAddress(ShippingSurcharge $surcharge, string $postcode, string $country): bool
    {
        $rule = $surcharge->rule ?? [];

        if (isset($rule['match']) && $rule['match'] === 'always') {
            return true;
        }

        if (isset($rule['type'])) {
            return match ($rule['type']) {
                'corse' => ZoneResolver::isCorse($postcode, $country),
                default => false,
            };
        }

        if (isset($rule['postcode_prefix'])) {
            $normalized = preg_replace('/\s+/', '', $postcode) ?? '';

            return str_starts_with($normalized, (string) $rule['postcode_prefix']);
        }

        return false;
    }

    // ── Banners ───────────────────────────────────────────────────────────────

    /**
     * @param  CalculatedShippingOption[]  $options
     * @return list<array<string, mixed>>
     */
    private function computeBanners(Cart $cart, array $options, bool $francoApplies, int $threshold, string $basis): array
    {
        $banners = [];

        $francoReached = false;
        foreach ($options as $opt) {
            if ($opt->franco) {
                $francoReached = true;
                break;
            }
        }

        if ($francoReached) {
            $banners[] = ['type' => 'franco_reached'];
        } else {
            // Progression vers le franco
            $current = $basis === 'cart_total'
                ? WeightCalculator::cartSubtotalHt($cart)
                : WeightCalculator::francoEligibleSubtotalHt($cart);

            $remaining = max(0, $threshold - $current);
            if ($remaining > 0) {
                $banners[] = ['type' => 'franco_progress', 'remaining_cents' => $remaining];
            }
        }

        if (WeightCalculator::cartHasFrancoExcludedLine($cart)) {
            $banners[] = ['type' => 'excluded_lines'];
        }

        if ($this->hasMultipleSources($cart)) {
            $banners[] = ['type' => 'multi_colis'];
        }

        return $banners;
    }

    private function hasMultipleSources(Cart $cart): bool
    {
        $hasWeklo = false;
        $hasSupplier = false;

        foreach ($cart->lines ?? [] as $line) {
            $product = $line->purchasable?->product;
            if ($product === null) {
                continue;
            }

            $supplierId = isset($product->pko_supplier_id) ? (int) $product->pko_supplier_id : null;
            if ($supplierId === null || $supplierId === 0) {
                $hasWeklo = true;
            } else {
                $hasSupplier = true;
            }
        }

        return $hasWeklo && $hasSupplier;
    }

    // ── Zone ──────────────────────────────────────────────────────────────────

    private function shouldQuote(string $country, string $postcode): bool
    {
        if (ZoneResolver::isMetropole($postcode, $country)) {
            return true;
        }

        if (ZoneResolver::isCorse($postcode, $country)) {
            return $this->hasActiveCorseSurcharge();
        }

        return false;
    }

    private function hasActiveCorseSurcharge(): bool
    {
        return ShippingSurcharge::query()
            ->where('enabled', true)
            ->whereIn('mode', ['auto', 'quote'])
            ->where(function ($q): void {
                $q->where('code', 'corse')
                    ->orWhereJsonContains('rule->type', 'corse')
                    ->orWhereJsonContains('rule->postcode_prefix', '20');
            })
            ->exists();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Partitionne les lignes du panier par port-mode résolu.
     *
     * @return array{Collection, Collection, Collection, Collection} [standard, flat, free, quote]
     */
    private function partitionLines(Collection $allLines): array
    {
        $standard = collect();
        $flat = collect();
        $free = collect();
        $quote = collect();

        foreach ($allLines as $line) {
            $product = $line->purchasable?->product;
            $mode = $product !== null ? PortModeResolver::resolve($product) : 'standard';

            match ($mode) {
                'flat' => $flat->push($line),
                'free' => $free->push($line),
                'quote' => $quote->push($line),
                default => $standard->push($line),
            };
        }

        return [$standard, $flat, $free, $quote];
    }

    private function emptyQuote(): ShippingQuote
    {
        return new ShippingQuote(
            options: [],
            defaultOptionIdentifier: self::DEFAULT_OPTION_IDENTIFIER,
            banners: [],
            blockers: [],
        );
    }
}
