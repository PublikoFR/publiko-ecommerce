<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\MailTemplates\Mail\TemplatedMail;
use Tests\TestCase;

/**
 * Un mail en file est sérialisé à l'envoi puis désérialisé par le worker.
 * La suite tourne en QUEUE_CONNECTION=sync, qui saute cette étape : sans ce
 * test, un mail qui ne survit pas à la désérialisation passe au vert ici et
 * échoue dès qu'une vraie file (redis, database) est branchée.
 */
class QueuedTemplatedMailSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_mail_derive_survit_a_la_serialisation_de_la_file(): void
    {
        $mail = new QueuedTemplatedMailStub('Marie');

        /** @var QueuedTemplatedMailStub $restored */
        $restored = unserialize(serialize($mail));

        $this->assertSame('cart.abandoned', $restored->key);
        $this->assertSame(['first_name' => 'Marie', 'cart_url' => 'https://example.test/panier'], $restored->values);
        $this->assertSame('fr', $restored->templateLocale);
        $this->assertSame('Marie', $restored->firstName);
        $this->assertTrue($restored->shouldSend());
        $this->assertStringContainsString('Marie', $restored->render());
    }
}

/**
 * Sous-classe minimale : reproduit la situation des mails métier, dont les
 * propriétés de TemplatedMail sont restaurées depuis la portée de la classe fille.
 */
class QueuedTemplatedMailStub extends TemplatedMail implements ShouldQueue
{
    public function __construct(public readonly string $firstName)
    {
        parent::__construct('cart.abandoned', [
            'first_name' => $firstName,
            'cart_url' => 'https://example.test/panier',
        ]);
    }
}
