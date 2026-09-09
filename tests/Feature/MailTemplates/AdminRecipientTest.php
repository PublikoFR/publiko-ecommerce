<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\MailTemplates\Support\AdminRecipient;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

class AdminRecipientTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_setting_storefront_prime_sur_la_config(): void
    {
        config()->set('customer-auth.admin_notification_email', 'env@example.test');
        Setting::set('admin_email', 'ops@example.test');

        $this->assertSame('ops@example.test', AdminRecipient::email());
    }

    public function test_sans_setting_on_retombe_sur_la_config(): void
    {
        config()->set('customer-auth.admin_notification_email', 'env@example.test');
        config()->set('loyalty.admin_email', null);

        $this->assertSame('env@example.test', AdminRecipient::email());
    }

    public function test_sans_destinataire_la_chaine_est_vide(): void
    {
        config()->set('customer-auth.admin_notification_email', null);
        config()->set('loyalty.admin_email', null);

        $this->assertSame('', AdminRecipient::email());
    }
}
