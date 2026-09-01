<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use App\Models\Staff;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages\EditMailTemplate;
use Pko\MailTemplates\Models\MailTemplate;
use Pko\MailTemplates\Support\ContentGuard;
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

    public function test_un_contenu_complet_est_accepte(): void
    {
        $error = ContentGuard::check(
            'order.confirmed',
            'Votre commande :order_reference',
            [['type' => 'paragraph', 'text' => 'Montant : :order_total € HT.']],
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
            [['type' => 'paragraph', 'text' => 'Bonjour, votre colis est parti.']],
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
            [['type' => 'paragraph', 'text' => 'Bonjour :prenom_du_client.']],
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
            [['type' => 'paragraph', 'text' => 'Texte en attente du client.']],
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
            [['type' => 'paragraph', 'text' => 'Bonjour :inconnu.']],
            enabled: false,
        );

        $this->assertNotNull($error);
    }

    /** La règle est bien branchée sur l'écran d'édition, pas seulement testée à part. */
    public function test_l_ecran_d_edition_refuse_un_contenu_invalide(): void
    {
        $template = MailTemplate::query()->where('key', 'order.shipped')->firstOrFail();

        // Contenu privé de ses variables obligatoires : c'est l'état que
        // l'écran doit refuser d'enregistrer tant que le modèle est actif.
        $template->update([
            'blocks' => [['type' => 'paragraph', 'text' => 'Votre colis est parti.']],
            'enabled' => true,
        ]);

        Livewire::test(EditMailTemplate::class, ['record' => $template->getKey()])
            ->fillForm(['subject' => 'Objet sans aucune variable'])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertNotSame('Objet sans aucune variable', $template->refresh()->subject);
    }
}
