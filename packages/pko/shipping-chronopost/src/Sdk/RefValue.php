<?php

declare(strict_types=1);

namespace Pko\ShippingChronopost\Sdk;

use ladromelaboratoire\chronopostws\wsdata\wsrefvalue;

/**
 * `refValue` du SDK avec une validation de `idRelais` conforme aux identifiants réels.
 *
 * La doc (et donc `wsregex::__reg_IdRelai`) annonce `[0-9]{4}[A-Za-z]` (ex. 3847U),
 * mais `recherchePointChronopostInter` renvoie aussi des points `999AA` (ex. 611BX,
 * plus de la moitié des points à Bordeaux / Paris / Lyon le 2026-09-25). Le SDK
 * refusait ces points : aucune étiquette relais n'aurait pu partir vers eux.
 */
class RefValue extends wsrefvalue
{
    public const ID_RELAIS_PATTERN = '/^[0-9A-Z]{5}$/';

    public $idRelais;

    public function setidRelai($id)
    {
        return $this->setVariableReg($this->idRelais, $id, self::ID_RELAIS_PATTERN);
    }
}
