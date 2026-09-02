<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\MailTemplates\Mail\TemplatedMail;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\MailTemplateRegistry;
use Pko\MailTemplates\Support\Placeholders;
use Illuminate\Support\Facades\Mail;
use Pko\MailTemplates\Support\MailPreview;
use Pko\MailTemplates\Support\TemplateResolver;
use Tests\TestCase;

class MailTemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Enveloppe une liste de blocs dans l'arbre page-builder minimal attendu
     * (une section, une colonne), pour garder les cas de test lisibles.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{heading: string, sections: array<int, array<string, mixed>>}
     */
    private static function page(array $blocks): array
    {
        return [
            'heading' => '',
            'sections' => [
                ['id' => 's1', 'layout' => '1col', 'columns' => [['blocks' => $blocks]]],
            ],
        ];
    }

    public function test_chaque_cle_declaree_possede_un_contenu_par_defaut(): void
    {
        $defaults = TemplateResolver::defaults('fr');

        foreach (MailTemplateRegistry::keys() as $key) {
            $this->assertArrayHasKey($key, $defaults, "Contenu manquant pour la clé {$key}.");
        }
    }

    public function test_aucun_contenu_orphelin_hors_du_registre(): void
    {
        foreach (array_keys(TemplateResolver::defaults('fr')) as $key) {
            $this->assertTrue(
                MailTemplateRegistry::has($key),
                "Le contenu « {$key} » ne correspond à aucune clé déclarée."
            );
        }
    }

    /**
     * Un placeholder utilisé dans un texte mais non déclaré ne serait jamais
     * remplacé : le client recevrait un mail affichant « :order_reference ».
     */
    public function test_les_placeholders_utilises_sont_tous_declares(): void
    {
        foreach (TemplateResolver::defaults('fr') as $key => $content) {
            $declared = MailTemplateRegistry::get($key)['placeholders'];
            $used = Placeholders::found($content['subject'], $content['content']);

            $undeclared = array_diff($used, $declared);

            $this->assertSame(
                [],
                array_values($undeclared),
                "Placeholders non déclarés dans « {$key} » : ".implode(', ', $undeclared)
            );
        }
    }

    /** Les placeholders obligatoires doivent réellement figurer dans le contenu. */
    public function test_les_placeholders_obligatoires_sont_presents(): void
    {
        foreach (TemplateResolver::defaults('fr') as $key => $content) {
            $meta = MailTemplateRegistry::get($key);

            if (($content['enabled'] ?? true) === false) {
                continue;
            }

            $used = Placeholders::found($content['subject'], $content['content']);

            foreach ($meta['required'] as $required) {
                $this->assertContains(
                    $required,
                    $used,
                    "Le placeholder obligatoire « {$required} » est absent du contenu « {$key} »."
                );
            }
        }
    }

    public function test_le_rendu_substitue_les_placeholders(): void
    {
        $mail = new TemplatedMail('order.confirmed', [
            'first_name' => 'Camille',
            'order_reference' => 'CMD-10432',
            'order_total' => '1 248,90',
            'order_url' => 'https://example.test/commande',
        ]);

        $this->assertTrue($mail->shouldSend());

        $html = $mail->render();

        $this->assertStringContainsString('CMD-10432', $html);
        $this->assertStringContainsString('1 248,90', $html);
        $this->assertStringContainsString('https://example.test/commande', $html);
        $this->assertStringNotContainsString(':order_reference', $html);
        $this->assertStringNotContainsString(':order_total', $html);
    }

    /**
     * Sans tri par longueur décroissante, `:order` écraserait le préfixe de
     * `:order_reference` et laisserait un `_reference` orphelin.
     */
    public function test_un_placeholder_prefixe_d_un_autre_ne_le_tronque_pas(): void
    {
        $result = Placeholders::apply(
            'Commande :order_reference pour :order',
            ['order' => 'A', 'order_reference' => 'CMD-1']
        );

        $this->assertSame('Commande CMD-1 pour A', $result);
    }

    public function test_un_mail_desactive_ne_doit_pas_partir(): void
    {
        // 07 et 08 : déclencheur câblé, contenu client pas encore fourni.
        $mail = new TemplatedMail('order.ready_for_pickup', ['order_reference' => 'CMD-1']);

        $this->assertFalse($mail->shouldSend());
    }

    public function test_le_contenu_en_base_prime_sur_le_defaut(): void
    {
        MailTemplate::query()->create([
            'key' => 'order.confirmed',
            'locale' => 'fr',
            'subject' => 'Sujet réécrit en back-office',
            'content' => self::page([['type' => 'text', 'html' => '<p>Commande :order_reference bien reçue.</p>']]),
            'enabled' => true,
        ]);

        $mail = new TemplatedMail('order.confirmed', ['order_reference' => 'CMD-9']);
        $html = $mail->render();

        $this->assertSame('Sujet réécrit en back-office', $mail->build()->subject);
        $this->assertStringContainsString('Commande CMD-9 bien reçue.', $html);
    }

    /** Désactiver un mail depuis le back-office doit suffire à couper l'envoi. */
    public function test_la_desactivation_en_base_coupe_l_envoi(): void
    {
        MailTemplate::query()->create([
            'key' => 'order.confirmed',
            'locale' => 'fr',
            'subject' => 'x',
            'content' => self::page([['type' => 'text', 'html' => '<p>y</p>']]),
            'enabled' => false,
        ]);

        $this->assertFalse((new TemplatedMail('order.confirmed'))->shouldSend());
    }

    /** L'envoi de test doit partir même en rafale : il contourne OnceMailer. */
    public function test_l_envoi_de_test_n_est_pas_bride_par_la_garde_anti_doublon(): void
    {
        Mail::fake();

        MailPreview::sendTest('order.confirmed', 'staff@example.test');
        MailPreview::sendTest('order.confirmed', 'staff@example.test');

        Mail::assertSent(TemplatedMail::class, 2);
    }

    /** L'objet est préfixé, sans quoi un test se confond avec un vrai message. */
    public function test_l_envoi_de_test_prefixe_l_objet(): void
    {
        Mail::fake();

        MailPreview::sendTest('order.confirmed', 'staff@example.test');

        Mail::assertSent(
            TemplatedMail::class,
            fn (TemplatedMail $mail): bool => str_starts_with($mail->build()->subject, '[TEST] ')
        );
    }

    /** Un modèle désactivé n'a rien à envoyer : refus explicite plutôt que mail vide. */
    public function test_l_envoi_de_test_refuse_un_modele_desactive(): void
    {
        Mail::fake();

        $this->expectException(\RuntimeException::class);

        MailPreview::sendTest('order.ready_for_pickup', 'staff@example.test');
    }

    public function test_le_contenu_est_echappe_dans_le_rendu(): void
    {
        $mail = new TemplatedMail('order.confirmed', [
            'first_name' => '<script>alert(1)</script>',
            'order_reference' => 'CMD-1',
            'order_total' => '10',
            'order_url' => 'https://example.test',
        ]);

        $html = $mail->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
