<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Support\Ecotax;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Currency as CurrencyContract;

class Cart extends Component
{
    /**
     * The editable cart lines.
     */
    public array $lines;

    public bool $linesVisible = false;

    protected $listeners = [
        'add-to-cart' => 'handleAddToCart',
    ];

    public function rules(): array
    {
        return [
            'lines.*.quantity' => 'required|numeric|min:1|max:10000',
        ];
    }

    public function mount(): void
    {
        $this->mapLines();
    }

    /**
     * Get the current cart instance.
     */
    public function getCartProperty()
    {
        return CartSession::current();
    }

    /**
     * Return the cart lines from the cart.
     */
    public function getCartLinesProperty(): Collection
    {
        return $this->cart->lines ?? collect();
    }

    /** Total de l'éco-participation du panier (cents HT, inclus dans le sous-total). */
    public function getEcotaxTotalProperty(): int
    {
        return $this->cart ? Ecotax::forCart($this->cart) : 0;
    }

    /**
     * Update the cart lines.
     */
    public function updateLines(): void
    {
        $this->validate();

        CartSession::updateLines(
            collect($this->lines)
        );
        $this->mapLines();
        $this->dispatch('cartUpdated');
    }

    public function removeLine($id): void
    {
        CartSession::remove($id);
        $this->mapLines();
    }

    /**
     * Map the cart lines.
     *
     * We want to map out our cart lines like this so we can
     * add some validation rules and make them editable.
     */
    public function mapLines(): void
    {
        $this->lines = $this->cartLines->map(function ($line) {
            return [
                'id' => $line->id,
                'identifier' => $line->purchasable->getIdentifier(),
                'quantity' => $line->quantity,
                'description' => $line->purchasable->getDescription(),
                'thumbnail' => $line->purchasable->getThumbnail()?->getUrl(),
                'option' => $line->purchasable->getOption(),
                'options' => $line->purchasable->getOptions()->implode(' / '),
                'sub_total' => $line->subTotal->formatted(),
                'unit_price' => $line->unitPrice->formatted(),
                'ecotax' => self::formatLineEcotax($line, $this->cart?->currency),
            ];
        })->toArray();
    }

    /** Éco-participation de la ligne (incluse dans le prix), formatée ; null si nulle. */
    private static function formatLineEcotax(CartLine $line, ?CurrencyContract $currency): ?string
    {
        $cents = Ecotax::forCartLine($line);

        return $cents > 0 ? Ecotax::format($cents, $currency) : null;
    }

    public function handleAddToCart(): void
    {
        $this->mapLines();
        $this->linesVisible = true;
    }

    public function render(): View
    {
        return view('livewire.components.cart');
    }
}
