<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
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
use Pko\ShippingCommon\Pricing\ShippingCalculator;
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

    /**
     * Une recherche a-t-elle déjà été lancée ? Distingue « pas encore cherché »
     * de « cherché, zéro résultat » — sans quoi la saisie manuelle s'affiche
     * d'emblée alors que la liste automatique n'a jamais été tentée.
     */
    public bool $pickupSearched = false;

    /**
     * Le service de recherche a-t-il échoué (credentials absents, SOAP KO) ?
     * Distinct d'une recherche aboutie sans résultat : le message client diffère.
     */
    public bool $pickupServiceUnavailable = false;

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

        // Carte préchargée sur le code postal de livraison : le client n'a rien à
        // cliquer pour voir les points relais autour de chez lui. Il peut ensuite
        // changer le code postal et relancer la recherche.
        $this->autoSearchPickupPoints();
    }

    /**
     * Bascule vers Chrono Relais : charger la liste tout de suite, sans attendre
     * un clic sur « Rechercher ».
     */
    public function updatedChosenOption(): void
    {
        $this->autoSearchPickupPoints();
    }

    /**
     * Recherche silencieuse : ne pose pas d'erreur de validation si le code
     * postal est absent (contrairement à l'action explicite du bouton).
     */
    private function autoSearchPickupPoints(): void
    {
        if (! $this->requiresPickupPoint || $this->pickupSearched) {
            return;
        }

        if (trim($this->pickupSearchPostcode) === '') {
            return;
        }

        $this->runPickupSearch();
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
        $this->resetErrorBag('pickupSearchPostcode');

        if (trim($this->pickupSearchPostcode) === '') {
            $this->addError('pickupSearchPostcode', 'Veuillez renseigner un code postal.');

            return;
        }

        // Relance explicite : on repart d'une recherche neuve même si une
        // précédente a déjà eu lieu (changement de code postal).
        $this->runPickupSearch();
    }

    /**
     * Interroge le provider et met à jour l'état d'affichage.
     */
    private function runPickupSearch(): void
    {
        $postcode = trim($this->pickupSearchPostcode);
        $country = (string) ($this->shippingAddress?->country?->iso2 ?? 'FR');

        // La ville n'est transmise que si elle correspond encore au code postal
        // recherché : chez Chronopost elle prime sur le code postal, donc garder
        // la ville de l'adresse après que le client a saisi un autre code postal
        // renverrait les points relais de l'ancienne ville.
        $addressPostcode = trim((string) ($this->shippingAddress?->postcode ?? ''));
        $city = $addressPostcode !== '' && $addressPostcode === $postcode
            ? (string) ($this->shippingAddress?->city ?? '')
            : null;

        $provider = app(PickupPointProvider::class);

        // serviceCode null : l'identifiant interne 'chronopost.chrono_relais' n'est
        // pas un productCode Chronopost ; le client SOAP pose lui-même le produit
        // Chrono Relais (86). Le poids du panier écarte les points qui ne peuvent
        // pas recevoir le colis (poidsMaxi).
        $points = $provider->search($postcode, $country, null, $city !== '' ? $city : null, $this->cartWeightGrams());

        $this->pickupPoints = array_map(
            fn (PickupPoint $point) => $point->toArray(),
            $points,
        );

        $this->pickupSearched = true;
        // Un provider en panne (credentials Chronopost absents, SOAP injoignable)
        // renvoie [] comme une recherche légitimement vide : sans ce drapeau le
        // client voyait un écran identique dans les deux cas, et le bouton
        // « Rechercher » paraissait inerte.
        $this->pickupServiceUnavailable = $this->pickupPoints === []
            && $provider->lastSearchError() !== null;
    }

    /**
     * Poids du panier en grammes, ou null s'il est inconnu (panier absent, poids
     * nul, unité de poids non gérée) — le filtre poidsMaxi est alors ignoré.
     */
    private function cartWeightGrams(): ?int
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return null;
        }

        try {
            $grams = (int) round(WeightCalculator::fromCart($cart) * 1000);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $grams > 0 ? $grams : null;
    }

    /**
     * Message affiché quand la liste automatique ne donne rien.
     */
    public function getPickupEmptyMessageProperty(): ?string
    {
        if (! $this->pickupSearched || $this->pickupPoints !== []) {
            return null;
        }

        if ($this->pickupServiceUnavailable) {
            return 'La recherche automatique de points relais est momentanément indisponible. '
                .'Saisissez les coordonnées de votre point relais ci-dessous.';
        }

        return 'Aucun point relais trouvé autour de ce code postal. '
            .'Essayez un code postal voisin, ou saisissez votre point relais ci-dessous.';
    }

    /**
     * Empreinte du jeu de points courant, utilisée en wire:key sur le conteneur
     * de la carte. Le conteneur Leaflet est en wire:ignore (Livewire ne doit pas
     * toucher au DOM que Leaflet gère) : sans clé qui change, une nouvelle
     * recherche laisserait l'ancienne carte et ses anciens pins en place.
     */
    public function getPickupPointsFingerprintProperty(): string
    {
        return md5(implode('|', array_column($this->pickupPoints, 'id')));
    }

    /**
     * Points géolocalisables : seuls ceux-là peuvent être affichés sur la carte.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMappablePickupPointsProperty(): array
    {
        return array_values(array_filter(
            $this->pickupPoints,
            fn (array $p) => ! empty($p['latitude']) && ! empty($p['longitude']),
        ));
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
     * Bandeaux retournés par ShippingCalculator pour le panier courant.
     * Source unique — ne pas recalculer dans ce composant.
     *
     * @return list<array<string, mixed>>
     */
    public function getBannersProperty(): array
    {
        $cart = CartSession::current();
        if ($cart === null) {
            return [];
        }

        return app(ShippingCalculator::class)->calculate($cart)->banners;
    }

    /**
     * True si au moins un bandeau 'franco_reached' est présent dans le quote.
     */
    public function getIsFrancoReachedProperty(): bool
    {
        foreach ($this->banners as $banner) {
            if ($banner['type'] === 'franco_reached') {
                return true;
            }
        }

        return false;
    }

    /**
     * True si au moins un bandeau 'excluded_lines' est présent dans le quote.
     */
    public function getHasExcludedLinesProperty(): bool
    {
        foreach ($this->banners as $banner) {
            if ($banner['type'] === 'excluded_lines') {
                return true;
            }
        }

        return false;
    }

    /**
     * True si au moins un bandeau 'multi_colis' est présent dans le quote.
     */
    public function getHasMultipleSourcesProperty(): bool
    {
        foreach ($this->banners as $banner) {
            if ($banner['type'] === 'multi_colis') {
                return true;
            }
        }

        return false;
    }

    /**
     * Cents restants depuis le bandeau 'franco_progress' du quote.
     * Retourne 0 si le seuil est atteint ou si aucun bandeau de progression n'est présent.
     */
    public function getFrancoRemainingCentsProperty(): int
    {
        foreach ($this->banners as $banner) {
            if ($banner['type'] === 'franco_progress') {
                return (int) ($banner['remaining_cents'] ?? 0);
            }
        }

        return 0;
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
