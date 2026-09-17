<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\AdminNav\Filament\Widgets\HomeSlidesTable;
use Pko\AdminNav\Filament\Widgets\LoyaltyTiersTable;
use Pko\Loyalty\Filament\Resources\LoyaltyTierResource;
use Pko\Loyalty\Models\LoyaltyTier;
use Pko\StorefrontCms\Models\HomeSlide;
use Tests\TestCase;

/**
 * Hors page de Resource, l'action « Modifier » d'une table de hub doit mener
 * au formulaire — pas à une modale vide.
 */
class HubWidgetEditActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $staff = Staff::create([
            'first_name' => 'Hub',
            'last_name' => 'Edit',
            'email' => 'hub-edit@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }

    public function test_tier_edit_action_links_to_edit_page(): void
    {
        $tier = LoyaltyTier::create([
            'name' => 'Argent',
            'points_required' => 500,
            'gift_title' => 'Mug',
            'active' => true,
        ]);

        Livewire::test(LoyaltyTiersTable::class)
            ->assertTableActionHasUrl('edit', LoyaltyTierResource::getUrl('edit', ['record' => $tier]), $tier);
    }

    public function test_slide_edit_action_opens_filled_form(): void
    {
        $slide = HomeSlide::create(['title' => 'Rentrée', 'position' => 1, 'is_active' => true]);

        Livewire::test(HomeSlidesTable::class)
            ->mountTableAction('edit', $slide)
            ->assertTableActionDataSet(['title' => 'Rentrée'])
            ->setTableActionData(['title' => 'Rentrée 2026'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('Rentrée 2026', $slide->fresh()->title);
    }
}
