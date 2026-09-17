<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\DefaultContentUpgrade;
use Pko\MailTemplates\Support\TemplateResolver;
use Pko\PageBuilder\Services\PageBuilderManager;
use Tests\TestCase;

/**
 * Un contenu par défaut revu arrive en base au déploiement, sans jamais écraser
 * un texte retouché en back-office.
 */
class DefaultContentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'loyalty.tier_unlocked';

    private const FILE = __DIR__.'/../../../packages/pko/mail-templates/database/content/upgrades/2026_09_17_loyalty_tier_unlocked_gift_card.php';

    /** @return array{subject: string, content: array<string, mixed>} */
    private function previous(): array
    {
        return (require self::FILE)[self::KEY];
    }

    /** @param array<string, mixed> $content */
    private function storeTemplate(string $subject, array $content, bool $enabled = true): MailTemplate
    {
        return MailTemplate::query()->create([
            'key' => self::KEY,
            'locale' => 'fr',
            'subject' => $subject,
            'content' => $content,
            'enabled' => $enabled,
        ]);
    }

    public function test_un_contenu_par_defaut_non_retouche_est_mis_a_jour(): void
    {
        $previous = $this->previous();
        $template = $this->storeTemplate($previous['subject'], $previous['content'], enabled: false);

        $this->assertSame(['loyalty.tier_unlocked' => DefaultContentUpgrade::UPDATED], DefaultContentUpgrade::applyFile(self::FILE));

        $template->refresh();
        $this->assertEquals(TemplateResolver::defaults()[self::KEY]['content'], $template->content);
        $this->assertFalse($template->enabled, 'Une désactivation en back-office doit être conservée.');
    }

    public function test_un_modele_reenregistre_sans_changement_est_mis_a_jour(): void
    {
        $previous = $this->previous();
        // L'éditeur enregistre l'arbre normalisé, avec identifiants et valeurs par défaut.
        $this->storeTemplate($previous['subject'], PageBuilderManager::normalize($previous['content']));

        $this->assertSame(DefaultContentUpgrade::UPDATED, DefaultContentUpgrade::apply(self::KEY, $previous));
    }

    public function test_un_texte_retouche_en_back_office_n_est_pas_ecrase(): void
    {
        $previous = $this->previous();
        $custom = $previous['content'];
        $custom['sections'][0]['columns'][0]['blocks'][1]['html'] = '<p>Texte maison</p>';
        $template = $this->storeTemplate($previous['subject'], $custom);

        $this->assertSame(DefaultContentUpgrade::CUSTOMIZED, DefaultContentUpgrade::apply(self::KEY, $previous));
        $this->assertSame('<p>Texte maison</p>', $template->refresh()->content['sections'][0]['columns'][0]['blocks'][1]['html']);
    }

    public function test_un_objet_retouche_bloque_aussi_la_mise_a_jour(): void
    {
        $previous = $this->previous();
        $this->storeTemplate('Objet maison', $previous['content']);

        $this->assertSame(DefaultContentUpgrade::CUSTOMIZED, DefaultContentUpgrade::apply(self::KEY, $previous));
    }

    public function test_un_contenu_deja_a_jour_est_laisse_tel_quel(): void
    {
        $next = TemplateResolver::defaults()[self::KEY];
        $this->storeTemplate($next['subject'], $next['content']);

        $this->assertSame(DefaultContentUpgrade::CURRENT, DefaultContentUpgrade::apply(self::KEY, $this->previous()));
    }

    public function test_sans_ligne_en_base_rien_n_est_cree(): void
    {
        $this->assertSame(DefaultContentUpgrade::MISSING, DefaultContentUpgrade::apply(self::KEY, $this->previous()));
        $this->assertSame(0, MailTemplate::query()->count());
    }
}
