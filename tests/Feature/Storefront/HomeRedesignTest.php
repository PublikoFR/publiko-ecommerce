<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\FieldTypes\Text as LunarText;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Url;
use Pko\AdminNav\Filament\Pages\HomepageHub;
use Pko\AdminNav\Livewire\HomeSectionsForm;
use Pko\Storefront\Support\StorefrontLinks;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Models\Setting;
use Pko\StorefrontCms\Support\HomeSolutionZones;
use Tests\TestCase;

/**
 * FEAT-009 — réaménagement de la page d'accueil : header (bandeau supérieur,
 * menu, bandeau sous le menu), footer, schéma des solutions, sélection pros et
 * bandeau compte pro.
 */
class HomeRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_links_without_url_or_with_unsafe_scheme_are_hidden(): void
    {
        $links = StorefrontLinks::visible([
            ['label' => 'FAQ', 'href' => '/pages/faq'],
            ['label' => 'Sans URL', 'href' => null],
            ['label' => 'Vide', 'href' => '  '],
            ['label' => 'XSS', 'href' => 'javascript:alert(1)'],
            ['label' => 'Protocole relatif', 'href' => '//evil.test'],
            ['label' => '', 'href' => '/sans-libelle'],
            ['label' => 'Externe', 'href' => 'https://example.test'],
            ['label' => 'Tel', 'href' => 'tel:+33600000000'],
        ]);

        $this->assertSame(['FAQ', 'Externe', 'Tel'], array_column($links, 'label'));
    }

    public function test_header_and_footer_render_configured_links(): void
    {
        Setting::set('brand.tagline', 'Accroche de test');
        Setting::set('banner.enabled', true);
        Setting::set('banner.text', 'Livraison offerte dès {{port_franco}}');
        Setting::set('topbar.links', [['label' => 'Questions fréquentes', 'href' => '/pages/faq']]);
        Setting::set('nav.secondary', [
            ['label' => 'Pièces détachées', 'href' => '/collections/pieces'],
            ['label' => 'Lien masqué', 'href' => null],
            ['label' => 'Lien piégé', 'href' => 'javascript:alert(1)'],
        ]);
        Setting::set('access_band.items', [['icon' => 'lock', 'text' => 'Tarifs après validation du compte pro']]);
        Setting::set('footer.columns', [
            ['title' => 'Votre accompagnement', 'links' => [['label' => 'Identifier une pièce', 'href' => '/pages/identifier']]],
            ['title' => 'Colonne vide', 'links' => [['label' => 'Rien', 'href' => null]]],
        ]);
        Setting::set('footer.baseline', 'Achat réservé aux professionnels validés');
        Setting::set('contact.phone', '01 23 45 67 89');
        $this->reloadSettingsIntoConfig();

        $response = $this->get('/')->assertOk();

        $response->assertSee('Accroche de test');
        $response->assertDontSee('{{port_franco}}', false);
        $response->assertSee('Questions fréquentes');
        $response->assertSee('href="/collections/pieces"', false);
        $response->assertDontSee('Lien masqué');
        $response->assertDontSee('javascript:alert', false);
        $response->assertSee('Tarifs après validation du compte pro');
        $response->assertSee('Demander un devis');
        $response->assertSee('Identifier une pièce');
        $response->assertDontSee('Colonne vide');
        $response->assertSee('Achat réservé aux professionnels validés');
        $response->assertSee('href="tel:0123456789"', false);
        // Contenus retirés du footer par FEAT-009.
        $response->assertDontSee('Newsletter');
    }

    public function test_solutions_only_show_zones_linked_to_an_enabled_collection(): void
    {
        $gates = $this->makeCollection('Portails maison', 'portails-test');
        $disabled = $this->makeCollection('Désactivée', 'desactivee-test');
        $disabled->pko_enabled = false;
        $disabled->save();

        $items = HomeSolutionZones::resolve([
            ['zone' => 'shutters', 'collection_id' => null],
            ['zone' => 'gates', 'collection_id' => $gates->id, 'label' => null, 'description' => 'Ma description'],
            ['zone' => 'parts', 'collection_id' => $disabled->id],
            ['zone' => 'inconnue', 'collection_id' => $gates->id],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('gates', $items[0]['key']);
        $this->assertSame('01', $items[0]['number']);
        $this->assertSame('Portails', $items[0]['label'], 'Libellé vide = libellé par défaut de la zone.');
        $this->assertSame('Ma description', $items[0]['description']);
        $this->assertStringEndsWith('/collections/portails-test', $items[0]['url']);
    }

    public function test_home_renders_solutions_module_when_a_zone_is_mapped(): void
    {
        $gates = $this->makeCollection('Portails maison', 'portails-test');
        Setting::set('home.solutions', [['zone' => 'gates', 'collection_id' => $gates->id]]);
        $this->reloadSettingsIntoConfig();

        $this->get('/')
            ->assertOk()
            ->assertSee('À chaque installation, sa solution.')
            ->assertSee('/collections/portails-test', false)
            ->assertSee('Voir la famille');
    }

    public function test_pro_band_adapts_to_account_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Ouvrir un compte pro')->assertSee('Déjà inscrit ? Se connecter');

        $this->actingAs($this->makeUser('pending@example.test', 'pending'));
        $this->get('/')->assertOk()
            ->assertDontSee('Ouvrir un compte pro')
            ->assertSee('en cours de validation', false);

        $this->actingAs($this->makeUser('active@example.test', 'active'));
        $this->get('/')->assertOk()->assertSee('Accéder à mon espace pro');
    }

    public function test_admin_sections_form_persists_home_settings(): void
    {
        $staff = Staff::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'home-sections@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');

        $this->get(HomepageHub::getUrl(['tab' => 'sections']))->assertOk()->assertSee('À chaque installation, sa solution');
        $this->get(StorefrontSettings::getUrl())->assertOk()->assertSee('Bandeau sous le menu')->assertSee('Pied de page');

        $gates = $this->makeCollection('Portails maison', 'portails-test');

        Livewire::test(HomeSectionsForm::class)
            ->set('data.solutions', [['zone' => 'gates', 'collection_id' => (string) $gates->id, 'label' => '', 'description' => '']])
            ->set('data.selection', [['icon' => 'tag', 'title' => 'Offres', 'subtitle' => '', 'href' => '/pages/offres']])
            ->set('data.community_url', '')
            ->call('save')
            ->assertHasNoErrors();

        Setting::forget();
        $this->assertEquals([['zone' => 'gates', 'collection_id' => $gates->id, 'label' => null, 'description' => null]], Setting::get('home.solutions'));
        $this->assertSame('/pages/offres', Setting::get('home.selection')[0]['href']);
        $this->assertNull(Setting::get('home.community_url'));

        Livewire::test(HomeSectionsForm::class)
            ->set('data.selection', [['icon' => 'tag', 'title' => 'Piège', 'subtitle' => '', 'href' => 'javascript:alert(1)']])
            ->call('save')
            ->assertHasErrors(['data.selection.0.href']);
    }

    /**
     * Le merge Setting → config est fait au boot du provider : on le rejoue
     * après avoir écrit des réglages dans le test.
     */
    private function reloadSettingsIntoConfig(): void
    {
        foreach (Setting::all(['key', 'value']) as $row) {
            if ($row->value !== null && $row->value !== '') {
                config(['storefront.'.$row->key => $row->value]);
            }
        }
    }

    private function makeCollection(string $name, string $slug): LunarCollection
    {
        $groupId = LunarCollection::query()->value('collection_group_id') ?? CollectionGroup::query()->value('id');

        $collection = new LunarCollection([
            'collection_group_id' => $groupId,
            'type' => 'static',
            'sort' => 'custom',
            'attribute_data' => collect([
                'name' => new TranslatedText(collect(['fr' => new LunarText($name)])),
            ]),
        ]);
        $collection->saveAsRoot();

        $collection->urls()->delete();
        Url::create([
            'language_id' => Language::query()->value('id'),
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'slug' => $slug,
            'default' => true,
        ]);

        return $collection->refresh();
    }

    private function makeUser(string $email, string $pkoStatus): User
    {
        $user = User::create([
            'name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        // email_verified_at n'est pas fillable : sans forceFill, le compte
        // « pending » serait vu comme « e-mail non confirmé ».
        $user->forceFill(['email_verified_at' => now()])->save();

        $customer = Customer::create([
            'first_name' => '',
            'last_name' => '',
            'company_name' => 'Test SARL',
            'pko_status' => $pkoStatus,
            'sirene_status' => 'pending',
        ]);

        $handle = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
        $group = CustomerGroup::firstOrCreate(['handle' => $handle], ['name' => 'Nouveau client']);
        $customer->customerGroups()->attach($group);
        $customer->users()->attach($user);

        return $user;
    }
}
