<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use App\Models\Staff;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\ContentGuard;
use Pko\MailTemplates\Support\ContentGuardException;
use Tests\TestCase;

/**
 * Garde-fous de l'éditeur back-office : le client peut réécrire ses textes,
 * pas casser la mécanique des e-mails.
 */
class EditMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $staff = Staff::query()->first();

        if ($staff !== null) {
            $this->actingAs($staff, 'staff');
        }
    }

    /**
     * Arbre page-builder minimal (une section, une colonne) autour d'une liste
     * de blocs, pour garder les cas de test lisibles.
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

    public function test_un_contenu_complet_est_accepte(): void
    {
        $error = ContentGuard::check(
            'order.confirmed',
            'Votre commande :order_reference',
            self::page([['type' => 'text', 'html' => '<p>Montant : :order_total € HT.</p>']]),
            enabled: true,
        );

        $this->assertNull($error);
    }

    /** Supprimer une variable obligatoire produirait un e-mail sans référence. */
    public function test_supprimer_une_variable_obligatoire_est_refuse(): void
    {
        $error = ContentGuard::check(
            'order.shipped',
            'Votre commande est en route',
            self::page([['type' => 'text', 'html' => '<p>Bonjour, votre colis est parti.</p>']]),
            enabled: true,
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString(':order_reference', $error);
        $this->assertStringContainsString(':tracking_url', $error);
    }

    /** Une variable inventée ne serait jamais remplacée et s'afficherait telle quelle. */
    public function test_une_variable_inconnue_est_refusee(): void
    {
        $error = ContentGuard::check(
            'order.confirmed',
            'Commande :order_reference',
            self::page([['type' => 'text', 'html' => '<p>Bonjour :prenom_du_client.</p>']]),
            enabled: true,
        );

        $this->assertNotNull($error);
        $this->assertStringContainsString(':prenom_du_client', $error);
    }

    /** Un modèle désactivé peut rester incomplet : c'est le cas des 07 et 08. */
    public function test_un_modele_desactive_tolere_un_contenu_incomplet(): void
    {
        $error = ContentGuard::check(
            'order.ready_for_pickup',
            'Brouillon',
            self::page([['type' => 'text', 'html' => '<p>Texte en attente du client.</p>']]),
            enabled: false,
        );

        $this->assertNull($error);
    }

    /** Une variable inconnue reste refusée même sur un modèle désactivé. */
    public function test_une_variable_inconnue_est_refusee_meme_desactive(): void
    {
        $error = ContentGuard::check(
            'order.ready_for_pickup',
            'Brouillon',
            self::page([['type' => 'text', 'html' => '<p>Bonjour :inconnu.</p>']]),
            enabled: false,
        );

        $this->assertNotNull($error);
    }

    /**
     * La règle est bien branchée sur le chemin d'écriture réel, pas seulement
     * testée à part. Depuis que le contenu est édité par le PageBuilder, la
     * garde vit dans `MailTemplate::saving()` : c'est elle qu'on vérifie, car
     * elle couvre aussi bien l'écran Filament que l'éditeur de blocs.
     */
    public function test_le_modele_refuse_un_contenu_invalide_a_l_enregistrement(): void
    {
        $template = MailTemplate::query()->where('key', 'order.shipped')->firstOrFail();

        $this->expectException(ContentGuardException::class);

        $template->update([
            'enabled' => true,
            'content' => self::page([['type' => 'text', 'html' => '<p>Votre colis est parti.</p>']]),
        ]);
    }

    /** Le même contenu incomplet passe si le modèle est désactivé. */
    public function test_le_modele_accepte_un_contenu_incomplet_si_desactive(): void
    {
        $template = MailTemplate::query()->where('key', 'order.shipped')->firstOrFail();

        $template->update([
            'enabled' => false,
            'content' => self::page([['type' => 'text', 'html' => '<p>Votre colis est parti.</p>']]),
        ]);

        $this->assertFalse($template->refresh()->enabled);
    }
}
