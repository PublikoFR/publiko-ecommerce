<?php

declare(strict_types=1);

namespace Tests\Feature\AdminNav;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Tests\TestCase;

/**
 * Structure de la sidebar admin (Builder::build) : groupes, libellés et placement
 * des entrées déplacées (Fidélité / Groupes de clients en Configuration).
 */
class SidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, list<string>>
     */
    private function sidebar(): array
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');

        // Rendre une page du panel pour que Filament résolve la navigation custom.
        $this->get('/admin')->assertOk();

        $groups = [];
        /** @var NavigationGroup $group */
        foreach (filament()->getNavigation() as $group) {
            $groups[(string) ($group->getLabel() ?? '')] = collect($group->getItems())
                ->map(fn (NavigationItem $item): string => (string) $item->getLabel())
                ->values()
                ->all();
        }

        return $groups;
    }

    public function test_sidebar_groups_and_moved_entries(): void
    {
        $groups = $this->sidebar();

        $this->assertSame(
            ['', 'Ventes & Clients', 'Catalogue', 'Marketing', 'Page d\'accueil', 'Configuration'],
            array_keys($groups),
        );

        $this->assertContains('Contacts', $groups['Ventes & Clients']);
        $this->assertNotContains('Groupes de clients', $groups['Ventes & Clients']);
        $this->assertNotContains('Fidélité', $groups['Marketing']);
        $this->assertContains('Articles & Pages', $groups['Page d\'accueil']);

        $config = $groups['Configuration'];
        $this->assertSame('Groupes de clients', end($config));
        $this->assertContains('Fidélité', $config);
    }
}
