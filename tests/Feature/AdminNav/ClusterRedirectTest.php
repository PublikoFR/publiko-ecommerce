<?php

declare(strict_types=1);

namespace Tests\Feature\AdminNav;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Pko\AdminNav\Filament\Clusters\PkoCatalogueSettingsCluster;
use Pko\AdminNav\Filament\Clusters\PkoShopPaymentCluster;
use Pko\AdminNav\Filament\Clusters\PkoSystemDataCluster;
use Pko\AdminNav\Filament\Clusters\PkoTaxesCluster;
use Tests\TestCase;

/**
 * Un lien de fil d'Ariane vers un cluster ne doit pas afficher une page sans vue
 * (sous-nav masquée) : il redirige vers le premier composant accessible.
 */
class ClusterRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function clusters(): array
    {
        return [
            'système & données' => [PkoSystemDataCluster::class],
            'boutique & paiement' => [PkoShopPaymentCluster::class],
            'réglages catalogue' => [PkoCatalogueSettingsCluster::class],
            'taxes' => [PkoTaxesCluster::class],
        ];
    }

    /**
     * @param  class-string  $cluster
     *
     * @dataProvider clusters
     */
    public function test_cluster_url_redirects_instead_of_rendering_a_view_less_page(string $cluster): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'staff@example.test',
            'password' => 'secret123',
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');
        config(['admin-nav.hide_cluster_subnav' => true]);

        $response = $this->get($cluster::getUrl());

        $response->assertRedirect();
        $this->assertNotSame($cluster::getUrl(), $response->headers->get('Location'));
    }
}
