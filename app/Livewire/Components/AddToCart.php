<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Illuminate\View\View;
use Livewire\Component;
use Lunar\Base\Purchasable;
use Lunar\DataTypes\Price;
use Lunar\Facades\CartSession;
use Lunar\Facades\Pricing;

class AddToCart extends Component
{
    /**
     * The purchasable model we want to add to the cart.
     */
    public ?Purchasable $purchasable = null;

    /**
     * The quantity to add to cart.
     */
    public int $quantity = 1;

    /**
     * Rendu compact (carte produit) : simple bouton d'ajout, sans stepper.
     */
    public bool $compact = false;

    public function rules(): array
    {
        return [
            'quantity' => 'required|numeric|min:1|max:10000',
        ];
    }

    public function addToCart(): void
    {
        $this->validate();

        // `canBeFulfilledAtQuantity()` (contrat Lunar\Base\Purchasable) respecte le mode
        // d'achat de la variante : `always` reste commandable à stock zéro — c'est le cas
        // des produits en stock fournisseur, affichés « Sur commande ». Un simple
        // `stock < quantity` bloquait toute la vente sur approvisionnement.
        if (! $this->purchasable->canBeFulfilledAtQuantity($this->quantity)) {
            $available = max(0, $this->purchasable->getTotalInventory());

            $this->addError('quantity', $available > 0
                ? "Stock insuffisant : {$available} disponible(s)."
                : 'Ce produit est momentanément indisponible.');

            return;
        }

        CartSession::manager()->add($this->purchasable, $this->quantity);
        $this->dispatch('add-to-cart');
    }

    /**
     * Prix total formaté pour la quantité sélectionnée (respecte les remises
     * dégressives par palier de quantité) — affiché dans le bouton d'ajout.
     */
    public function getTotalPriceProperty(): ?string
    {
        try {
            $matched = Pricing::for($this->purchasable)->qty($this->quantity)->get()->matched;
        } catch (\Throwable) {
            return null;
        }

        if (! $matched) {
            return null;
        }

        return (new Price($matched->price->value * $this->quantity, $matched->price->currency))->formatted();
    }

    public function render(): View
    {
        return view('livewire.components.add-to-cart');
    }
}
