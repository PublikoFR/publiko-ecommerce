<?php

declare(strict_types=1);

namespace Tests\Feature\MailTemplates;

use App\Models\Staff;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages\ListMailTemplates;
use Pko\MailTemplates\Models\MailTemplate;
use Tests\TestCase;

class MailTemplateListTest extends TestCase
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
     * Les boutons d'action n'affichent qu'un picto : l'infobulle est leur seul
     * libellé visible. Elle doit être un vrai tooltip (Alpine + Tippy, via
     * `x-tooltip`) et non un attribut `title`, qui n'est pas stylable, apparaît
     * après un délai imposé par le navigateur et reste invisible au tactile.
     */
    public function test_les_actions_exposent_de_vrais_tooltips(): void
    {
        $html = Livewire::test(ListMailTemplates::class)->html();

        // `x-tooltip` est la directive Alpine branchée sur Tippy : c'est elle qui
        // distingue un vrai tooltip d'un simple attribut `title`.
        $this->assertStringContainsString('x-tooltip', $html);

        // Filament passe le libellé par `@js()`, qui encode les accents en \uXXXX :
        // on décode avant de comparer, sinon « Aperçu » n'est jamais trouvé.
        preg_match_all('/x-tooltip="\{[^}]*content:\s*\x27([^\x27]+)\x27/', $html, $matches);

        $tooltips = array_map(
            static fn (string $raw): string => json_decode('"'.$raw.'"') ?? $raw,
            array_unique($matches[1] ?? []),
        );

        foreach (['Aperçu du message', 'Envoyer un e-mail de test', 'Modifier le contenu'] as $label) {
            $this->assertContains($label, $tooltips, "Infobulle manquante : {$label}");
        }
    }

    public function test_la_liste_affiche_tous_les_modeles(): void
    {
        Livewire::test(ListMailTemplates::class)
            ->assertCanSeeTableRecords(MailTemplate::all());
    }

    public function test_les_trois_actions_sont_presentes(): void
    {
        $record = MailTemplate::query()->where('key', 'order.confirmed')->firstOrFail();

        Livewire::test(ListMailTemplates::class)
            ->assertTableActionExists('preview')
            ->assertTableActionExists('send_test')
            ->assertTableActionVisible('send_test', $record);
    }

    /** Un modèle sans contenu n'a rien à envoyer : l'action doit disparaître. */
    public function test_l_envoi_de_test_est_masque_sur_un_modele_desactive(): void
    {
        $record = MailTemplate::query()->where('key', 'order.ready_for_pickup')->firstOrFail();

        Livewire::test(ListMailTemplates::class)
            ->assertTableActionHidden('send_test', $record);
    }
}
