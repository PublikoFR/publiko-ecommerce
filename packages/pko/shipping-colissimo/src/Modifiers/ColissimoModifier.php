<?php

declare(strict_types=1);

namespace Pko\ShippingColissimo\Modifiers;

use Pko\ShippingCommon\Modifiers\AbstractCarrierModifier;

class ColissimoModifier extends AbstractCarrierModifier
{
    protected function carrierCode(): string
    {
        return 'colissimo';
    }
}
