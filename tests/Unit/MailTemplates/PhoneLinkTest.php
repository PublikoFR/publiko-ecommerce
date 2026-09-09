<?php

declare(strict_types=1);

namespace Tests\Unit\MailTemplates;

use Pko\MailTemplates\Support\PhoneLink;
use Tests\TestCase;

class PhoneLinkTest extends TestCase
{
    public function test_un_numero_francais_devient_un_lien_tel_international(): void
    {
        $this->assertSame('tel:+33612345678', PhoneLink::href('06 12 34 56 78'));
    }

    public function test_un_numero_deja_international_est_conserve(): void
    {
        $this->assertSame('tel:+33612345678', PhoneLink::href('+33 6 12 34 56 78'));
    }

    public function test_un_numero_vide_ne_produit_pas_de_lien(): void
    {
        $this->assertSame('', PhoneLink::href(null));
        $this->assertSame('', PhoneLink::href(''));
    }
}
