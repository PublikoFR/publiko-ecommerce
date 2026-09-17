<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\Loyalty\Mail\TierUnlockedMail;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\MailTemplates\Support\ContentGuard;
use Pko\MailTemplates\Support\TemplateResolver;
use Tests\TestCase;

/**
 * E-mail « Nouveau palier » : le cadeau est présenté dans un encart à part,
 * avec sa photo quand le palier en a une.
 */
class TierUnlockedGiftCardTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, string> $overrides */
    private function render(array $overrides = []): string
    {
        return (new TemplatedMail('loyalty.tier_unlocked', $overrides + [
            'first_name' => 'Romain',
            'tier_name' => 'Diamond',
            'tier_benefit' => 'PS5',
            'total_points' => '5000',
            'gift_title' => 'PS5',
            'gift_description' => 'Édition digitale',
            'gift_image_url' => 'https://cdn.example.test/ps5.jpg',
        ]))->render();
    }

    public function test_le_cadeau_est_rendu_dans_un_encart_avec_sa_photo(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('background:#f6faea', $html);
        $this->assertStringContainsString('src="https://cdn.example.test/ps5.jpg"', $html);
        $this->assertStringContainsString('alt="PS5"', $html);
        $this->assertStringContainsString('Édition digitale', $html);
        // Deux colonnes dans un encart de 20 px de marge interne de chaque côté.
        $this->assertStringContainsString('max-width:240px', $html);
    }

    public function test_sans_photo_le_texte_prend_toute_la_largeur(): void
    {
        $html = $this->render(['gift_image_url' => '', 'gift_description' => '']);

        $this->assertStringContainsString('background:#f6faea', $html);
        $this->assertStringNotContainsString('<img src=""', $html);
        $this->assertStringNotContainsString('max-width:240px', $html);
        $this->assertStringContainsString('PS5', $html);
    }

    public function test_le_contenu_par_defaut_passe_le_controle_de_coherence(): void
    {
        $default = TemplateResolver::defaults()['loyalty.tier_unlocked'];

        $this->assertNull(ContentGuard::check('loyalty.tier_unlocked', $default['subject'], $default['content'], true));
    }

    public function test_le_mail_fournit_les_variables_du_cadeau(): void
    {
        $tier = LoyaltyTier::create([
            'name' => 'Diamond',
            'points_required' => 5000,
            'gift_title' => 'PS5',
            'gift_description' => null,
            'active' => true,
        ]);

        $mail = new TierUnlockedMail(null, $tier, 5000);

        $this->assertSame('PS5', $mail->values['gift_title']);
        $this->assertSame('', $mail->values['gift_description']);
        $this->assertSame('', $mail->values['gift_image_url']);
    }
}
