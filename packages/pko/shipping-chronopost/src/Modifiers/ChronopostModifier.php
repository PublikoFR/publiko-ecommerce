<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Modifiers;

use Pko\ShippingCommon\Modifiers\AbstractCarrierModifier;

class ChronopostModifier extends AbstractCarrierModifier
{
    protected function carrierCode(): string
    {
        return 'chronopost';
    }
}
