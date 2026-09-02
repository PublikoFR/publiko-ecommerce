<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use Pko\MailTemplates\Support\EmailHtml;
use Pko\MailTemplates\Support\EmailLayout;
use Tests\TestCase;

/**
 * Compatibilité du rendu avec les clients mail : colonnes qui se replient,
 * styles inline sur le HTML riche.
 */
class EmailRenderingTest extends TestCase
{
    /**
     * La largeur des colonnes doit tenir dans le gabarit, gouttières comprises.
     * Si la somme dépasse, les colonnes se replient dès le desktop.
     */
    public function test_les_colonnes_tiennent_dans_la_largeur_du_gabarit(): void
    {
        foreach ([2, 3] as $count) {
            $total = EmailLayout::columnWidth($count) * $count
                + EmailLayout::COLUMN_GAP * ($count - 1);

            $this->assertLessThanOrEqual(
                EmailLayout::CONTENT_WIDTH,
                $total,
                "{$count} colonnes débordent du gabarit."
            );
        }
    }

    /** Au-delà de 3 colonnes, chacune prend toute la largeur : elles s'empilent. */
    public function test_au_dela_de_trois_colonnes_le_rendu_empile(): void
    {
        $this->assertSame(EmailLayout::CONTENT_WIDTH, EmailLayout::columnWidth(4));
        $this->assertSame(100, EmailLayout::msoColumnPercent(4));
    }

    /**
     * Le repli mobile repose sur `max-width` en pixels : deux colonnes doivent
     * être plus larges que la moitié d'un écran étroit pour ne pas tenir côte à
     * côte et donc s'empiler.
     */
    public function test_deux_colonnes_ne_tiennent_pas_cote_a_cote_sur_mobile(): void
    {
        $mobileWidth = 320;

        $this->assertGreaterThan(
            $mobileWidth / 2,
            EmailLayout::columnWidth(2),
            'Les colonnes resteraient côte à côte sur mobile au lieu de s\'empiler.'
        );
    }

    public function test_le_rendu_multi_colonnes_contient_le_repli_outlook(): void
    {
        $html = $this->renderColumns(2);

        $this->assertStringContainsString('[if mso]', $html);
        $this->assertStringContainsString('display:inline-block', $html);
        $this->assertStringContainsString('max-width:'.EmailLayout::columnWidth(2).'px', $html);
    }

    /** Sans `font-size:0`, un blanc apparaît entre deux colonnes inline-block. */
    public function test_le_conteneur_de_colonnes_neutralise_l_espace_inter_blocs(): void
    {
        $this->assertStringContainsString('font-size:0', $this->renderColumns(2));
    }

    public function test_les_balises_du_texte_riche_recoivent_des_styles_inline(): void
    {
        $html = EmailHtml::inlineStyles('<p>Bonjour <a href="https://example.test">ici</a>.</p><ul><li>un</li></ul>');

        $this->assertStringContainsString('<p style="margin:', $html);
        $this->assertStringContainsString('color:#00453e', $html);
        $this->assertStringContainsString('<ul style="margin:', $html);
        $this->assertStringContainsString('<li style="margin:', $html);
    }

    /**
     * La mise en forme choisie par le rédacteur doit primer : nos styles sont
     * posés en premier dans l'attribut, les siens ensuite l'emportent.
     */
    public function test_le_style_du_redacteur_prime_sur_le_style_par_defaut(): void
    {
        $html = EmailHtml::inlineStyles('<p style="color:#ff0000">rouge</p>');

        $this->assertStringContainsString('color:#ff0000', $html);
        $this->assertLessThan(
            strpos($html, 'color:#ff0000'),
            strpos($html, 'margin:'),
            'Le style par défaut doit précéder celui du rédacteur.'
        );
    }

    public function test_un_html_vide_ne_produit_rien(): void
    {
        $this->assertSame('', EmailHtml::inlineStyles('   '));
    }

    /** Un fragment mal formé ne doit pas faire échouer l'envoi. */
    public function test_un_html_casse_est_rendu_tel_quel_sans_exception(): void
    {
        $this->assertIsString(EmailHtml::inlineStyles('<p>non fermé <span>'));
    }

    private function renderColumns(int $count): string
    {
        $columns = [];
        for ($i = 1; $i <= $count; $i++) {
            $columns[] = ['blocks' => [['type' => 'text', 'html' => "<p>Colonne {$i}</p>"]]];
        }

        return view('pko-mail-templates::message', [
            'content' => ['heading' => '', 'sections' => [
                ['id' => 's1', 'layout' => $count.'col', 'columns' => $columns],
            ]],
            'subjectLine' => 'Test',
        ])->render();
    }
}
