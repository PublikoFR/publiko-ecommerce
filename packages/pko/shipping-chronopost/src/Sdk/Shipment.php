<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Sdk;

use ladromelaboratoire\chronopostws\shipment as SdkShipment;

/**
 * `shipment` du SDK qui fabrique ses `refValue` via {@see RefValue}. Injecté dans
 * `chronopost::$shipment` par ChronopostClient::makeSdk() — le SDK se contourne, il
 * ne se patche pas.
 */
class Shipment extends SdkShipment
{
    public function setrefValue($refs)
    {
        if (array_key_exists(0, $refs)) {
            foreach ($refs as $index => $ref) {
                $this->refValue[$index] = new RefValue($this->useExceptions);
                $this->refValue[$index]->loadArray($ref);
            }

            return true;
        }

        $this->refValue[0] = new RefValue($this->useExceptions);

        return $this->refValue[0]->loadArray($refs);
    }
}
