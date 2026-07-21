<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Support\CustomerGroupDeletionImpact;
use App\Support\CustomerGroupGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\DiscountTypes\AmountOff;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

/**
 * Cascade des liaisons groupe client ↔ objets, dans les deux sens.
 *
 * Sens objet → groupe : assuré par les observers Lunar (DiscountObserver,
 * ShippingMethod::deleting, ProductObserver, CollectionObserver). On le vérifie
 * ici pour se prémunir d'une régression d'un upgrade Lunar.
 *
 * Sens groupe → objet : c'est notre CustomerGroupDeletionImpact.
 */
class CustomerGroupCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le UrlGenerator Lunar exige une langue par défaut dès qu'un produit
        // ou une variante est créé (typé non-nullable).
        Language::firstOrCreate(
            ['code' => 'fr'],
            ['name' => 'Français', 'default' => true]
        );
    }

    private function makeGroup(string $handle, bool $default = false): CustomerGroup
    {
        return CustomerGroup::create([
            'name' => ucfirst($handle),
            'handle' => $handle,
            'default' => $default,
        ]);
    }

    /**
     * Crée une réduction SANS les liaisons auto-semées.
     *
     * `HasCustomerGroups` est monté sur Discount aussi : à la création, Lunar sème
     * une ligne par groupe existant. On repart d'une table propre pour que les
     * tests portent sur des rattachements explicites.
     */
    private function makeDiscount(string $name = 'Promo'): Discount
    {
        $discount = Discount::create([
            'name' => $name,
            'handle' => str($name)->slug()->toString(),
            'type' => AmountOff::class,
            'data' => ['fixed_value' => true, 'fixed_values' => ['EUR' => 10]],
            'starts_at' => now(),
        ]);

        $discount->customerGroups()->detach();

        return $discount;
    }

    /** Sens objet → groupe : Lunar détache la liaison quand on supprime la réduction. */
    public function test_deleting_a_discount_detaches_its_customer_groups(): void
    {
        $group = $this->makeGroup('pro');
        $discount = $this->makeDiscount();
        $discount->customerGroups()->attach($group, ['enabled' => true, 'starts_at' => now()]);

        $this->assertSame(1, DB::table('lunar_customer_group_discount')->count());

        $discount->delete();

        $this->assertSame(0, DB::table('lunar_customer_group_discount')->count());
        $this->assertNotNull(CustomerGroup::find($group->id), 'Le groupe ne doit pas partir avec la réduction.');
    }

    /** Sens groupe → objet : une réduction partagée est simplement détachée. */
    public function test_deleting_a_group_detaches_a_shared_discount(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');
        $vip = $this->makeGroup('vip');

        $discount = $this->makeDiscount('Partagée');
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);
        $discount->customerGroups()->attach($vip, ['enabled' => true, 'starts_at' => now()]);

        $impact = CustomerGroupDeletionImpact::analyse($pro);
        $this->assertSame([], $impact['orphans'], 'Partagée avec VIP : pas orpheline.');
        $this->assertSame(1, $impact['shared']);

        CustomerGroupDeletionImpact::apply($pro, deleteOrphans: false);

        $this->assertNull(CustomerGroup::find($pro->id));
        $this->assertNotNull(Discount::find($discount->id), 'La réduction doit survivre.');
        $this->assertSame(1, DB::table('lunar_customer_group_discount')->count());
    }

    /** Une réduction rattachée au seul groupe supprimé est signalée comme orpheline. */
    public function test_exclusive_discount_is_reported_as_orphan(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');

        $discount = $this->makeDiscount('Exclusive');
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);

        $impact = CustomerGroupDeletionImpact::analyse($pro);

        $this->assertCount(1, $impact['orphans']);
        $this->assertSame('réduction', $impact['orphans'][0]['label']);
        $this->assertSame('Exclusive', $impact['orphans'][0]['name']);
    }

    /** Choix « conserver » : la réduction reste en base, détachée. */
    public function test_orphan_is_kept_when_requested(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');
        $discount = $this->makeDiscount('Exclusive');
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);

        CustomerGroupDeletionImpact::apply($pro, deleteOrphans: false);

        $this->assertNotNull(Discount::find($discount->id));
        $this->assertSame(0, DB::table('lunar_customer_group_discount')->count());
    }

    /** Choix « supprimer aussi » : la réduction part avec le groupe. */
    public function test_orphan_is_deleted_when_requested(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');
        $discount = $this->makeDiscount('Exclusive');
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);

        CustomerGroupDeletionImpact::apply($pro, deleteOrphans: true);

        $this->assertNull(Discount::find($discount->id));
        $this->assertNull(CustomerGroup::find($pro->id));
    }

    /**
     * Les tarifs sont SUPPRIMÉS, jamais détachés : `customer_group_id` est nullable
     * et un prix à NULL vaut pour tous les groupes — détacher publierait les tarifs
     * négociés en prix public.
     */
    public function test_group_prices_are_deleted_never_nulled(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');

        $currency = Currency::create([
            'code' => 'EUR', 'name' => 'Euro', 'exchange_rate' => 1,
            'decimal_places' => 2, 'enabled' => true, 'default' => true,
        ]);
        $variant = ProductVariant::factory()->create();

        Price::create([
            'price' => 1000,
            'currency_id' => $currency->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
            'customer_group_id' => $pro->id,
            'min_quantity' => 1,
        ]);

        $this->assertSame(1, CustomerGroupDeletionImpact::analyse($pro)['prices']);

        CustomerGroupDeletionImpact::apply($pro, deleteOrphans: false);

        $this->assertSame(0, DB::table('lunar_prices')->count(), 'Le tarif devait être supprimé.');
        $this->assertSame(
            0,
            DB::table('lunar_prices')->whereNull('customer_group_id')->count(),
            'Un tarif à NULL deviendrait public : interdit.'
        );
    }

    /**
     * Une liaison auto-semée (enabled = false) ne rattache rien : elle ne doit ni
     * compter comme partage, ni faire croire que la réduction est utilisée.
     */
    public function test_seeded_inactive_link_is_ignored(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');

        $discount = $this->makeDiscount('Inactive');
        $discount->customerGroups()->attach($pro, ['enabled' => false, 'starts_at' => now()]);

        $impact = CustomerGroupDeletionImpact::analyse($pro);

        $this->assertSame([], $impact['orphans'], 'Une liaison inactive ne crée pas d’orphelin.');
        $this->assertSame(0, $impact['shared']);
    }

    /**
     * Cas subtil : la réduction est rattachée à trois groupes mais n'est ACTIVE que
     * pour celui qu'on supprime. Elle devient donc bel et bien inutilisable, et doit
     * être signalée — un simple comptage des liaisons l'aurait manqué.
     */
    public function test_orphan_detected_when_other_links_are_all_inactive(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');
        $vip = $this->makeGroup('vip');

        $discount = $this->makeDiscount('Réservée aux pros');
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);
        $discount->customerGroups()->attach($vip, ['enabled' => false, 'starts_at' => now()]);

        $impact = CustomerGroupDeletionImpact::analyse($pro);

        $this->assertCount(1, $impact['orphans']);
        $this->assertSame('Réservée aux pros', $impact['orphans'][0]['name']);
    }

    /** Le groupe par défaut et le groupe pro restent protégés malgré la cascade. */
    public function test_protected_groups_still_block(): void
    {
        $default = $this->makeGroup('particuliers', default: true);

        $this->assertNotNull(CustomerGroupGuard::blockReason($default));
    }

    /** Les liaisons ne bloquent plus : elles sont cascadées. */
    public function test_linked_group_is_no_longer_blocked(): void
    {
        $this->makeGroup('particuliers', default: true);
        $pro = $this->makeGroup('pro');
        $discount = $this->makeDiscount();
        $discount->customerGroups()->attach($pro, ['enabled' => true, 'starts_at' => now()]);

        $this->assertNull(
            CustomerGroupGuard::blockReason($pro),
            'Une réduction rattachée ne doit plus empêcher la suppression.'
        );
    }
}
