<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Extensions\CollectionEnabledExtension;
use App\Filament\Extensions\DisableBrokenChartsExtension;
use App\Filament\Extensions\HideLunarMediaExtension;
use App\Filament\Pages\SireneConfig;
use App\Filament\Pages\StripeConfig;
use App\Filament\Pages\TreeManager;
use App\Filament\Pages\WekloDashboard;
use App\Filament\Resources\PkoAttributeGroupResource;
use App\Filament\Resources\PkoCollectionGroupResource;
use App\Filament\Resources\PkoProductOptionResource;
use App\Filament\Resources\PkoProductResource;
use App\Filament\Resources\PkoProductTypeResource;
use App\Generators\PkoProductUrlGenerator;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\ActivityResource;
use Lunar\Admin\Filament\Resources\AttributeGroupResource;
use Lunar\Admin\Filament\Resources\BrandResource;
use Lunar\Admin\Filament\Resources\ChannelResource;
use Lunar\Admin\Filament\Resources\CollectionGroupResource;
use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Filament\Resources\CurrencyResource;
use Lunar\Admin\Filament\Resources\CustomerResource;
use Lunar\Admin\Filament\Resources\LanguageResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Filament\Resources\ProductOptionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductTypeResource;
use Lunar\Admin\Filament\Resources\StaffResource;
use Lunar\Admin\Filament\Resources\TagResource;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Lunar\Admin\LunarPanelManager;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\ProductVariant;
use Lunar\Shipping\ShippingPlugin;
use Pko\AdminNav\Filament\AdminNavPlugin;
use Pko\AdminNav\Filament\Resources\PkoActivityResource;
use Pko\AdminNav\Filament\Resources\PkoChannelResource;
use Pko\AdminNav\Filament\Resources\PkoCurrencyResource;
use Pko\AdminNav\Filament\Resources\PkoLanguageResource;
use Pko\AdminNav\Filament\Resources\PkoStaffResource;
use Pko\AdminNav\Filament\Resources\PkoTagResource;
use Pko\AdminNav\Filament\Resources\PkoTaxClassResource;
use Pko\AdminNav\Filament\Resources\PkoTaxRateResource;
use Pko\AdminNav\Filament\Resources\PkoTaxZoneResource;
use Pko\AiImporter\Filament\AiImporterPlugin;
use Pko\CatalogFeatures\Filament\CatalogFeaturesPlugin;
use Pko\CatalogFeatures\Filament\Extensions\ProductFeaturesExtension;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\Loyalty\Filament\Extensions\CustomerLoyaltyExtension;
use Pko\Loyalty\Filament\LoyaltyPlugin;
use Pko\Pennylane\Filament\Extensions\OrderInvoiceActionsExtension;
use Pko\Pennylane\Filament\PennylanePlugin;
use Pko\ProductDocuments\ProductDocumentsPlugin;
use Pko\Secrets\Facades\Secrets;
use Pko\ShippingCommon\Filament\Extensions\OrderQuoteActionsExtension;
use Pko\ShippingCommon\Filament\SwapLunarShippingResourcesPlugin;
use Pko\ShippingCommon\Filament\TransportersPlugin;
use Pko\StorefrontCms\Filament\Extensions\BrandContentExtension;
use Pko\StorefrontCms\Filament\MediaManagerShimPlugin;
use Pko\StorefrontCms\Filament\Pages\StorefrontSettings;
use Pko\StorefrontCms\Filament\StorefrontCmsPlugin;
use Pko\StoreLocator\Filament\StoreLocatorPlugin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->swapLunarResources();
        $this->swapLunarPages();
        $this->registerSecretModules();

        LunarPanel::panel(function (Panel $panel): Panel {
            return $panel
                ->spa(false)
                ->path('admin')
                ->brandName(brand_name())
                // Logos/favicon configurables depuis Storefront → Paramètres
                // (Setting brand.logo / brand.logo_dark / brand.favicon), avec
                // repli sur les assets Weklo. Closures → lecture DB à l'affichage.
                ->brandLogo(fn (): string => brand_logo() ?? asset('img/weklo-lockup.png'))
                ->darkModeBrandLogo(fn (): string|HtmlString => brand_logo_dark()
                    ?? new HtmlString('<span class="wk-logo-dark">weklo</span>'))
                ->brandLogoHeight('2.5rem')
                ->favicon(fn (): string => brand_favicon() ?? asset('img/weklo-mark.png'))
                ->renderHook(
                    PanelsRenderHook::USER_MENU_BEFORE,
                    fn (): string => view('filament.hooks.user-identity')->render(),
                )
                ->renderHook(
                    PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                    fn (): string => view('filament.hooks.view-shop')->render(),
                )
                ->font('Hanken Grotesk')
                ->colors([
                    'primary' => [
                        50 => '#eef5f3', 100 => '#d6e7e3', 200 => '#abccc5', 300 => '#79aaa1',
                        400 => '#43847a', 500 => '#136356', 600 => '#00453e', 700 => '#003a34',
                        800 => '#002e29', 900 => '#00211e', 950 => '#001512',
                    ],
                    'accent' => [
                        50 => '#f6faea', 100 => '#ecf4cf', 200 => '#dbe9a3', 300 => '#c8dd72',
                        400 => '#b8d24c', 500 => '#aac932', 600 => '#8aa922', 700 => '#6a841d',
                        800 => '#50641b', 900 => '#3c4b19', 950 => '#232c10',
                    ],
                    'gray' => [
                        50 => '#f6f8f7', 100 => '#eef1f0', 200 => '#e0e4e2', 300 => '#c5ccc9',
                        400 => '#9aa3a0', 500 => '#76817d', 600 => '#586460', 700 => '#3f4a46',
                        800 => '#283330', 900 => '#16201d', 950 => '#0c110f',
                    ],
                    'success' => ['500' => '#2f9e57', '600' => '#27894a', '700' => '#1f6e3c'],
                    'warning' => ['500' => '#e8a317', '600' => '#c98a10', '700' => '#a36f08'],
                    'danger' => ['500' => '#d64545', '600' => '#c23a3a', '700' => '#9c2a2a'],
                    'info' => ['500' => '#2f80b8', '600' => '#276b9c', '700' => '#1d5781'],
                ])
                ->viteTheme('resources/css/filament/admin/theme.css')
                ->pages([
                    StripeConfig::class,
                    SireneConfig::class,
                    TreeManager::class,
                    StorefrontSettings::class,
                ])
                ->discoverClusters(
                    in: base_path('packages/pko/shipping-common/src/Filament/Clusters'),
                    for: 'Pko\\ShippingCommon\\Filament\\Clusters',
                )
                ->discoverClusters(
                    in: base_path('packages/pko/lunar-admin-nav/src/Filament/Clusters'),
                    for: 'Pko\\AdminNav\\Filament\\Clusters',
                )
                ->plugin(FilamentShieldPlugin::make())
                ->plugin(ShippingPlugin::make())
                ->plugin(SwapLunarShippingResourcesPlugin::make())
                ->plugin(TransportersPlugin::make())
                ->plugin(CatalogFeaturesPlugin::make())
                ->plugin(ProductDocumentsPlugin::make())
                ->plugin(AiImporterPlugin::make())
                ->plugin(LoyaltyPlugin::make())
                ->plugin(StorefrontCmsPlugin::make())
                ->plugin(StoreLocatorPlugin::make())
                ->plugin(MediaManagerShimPlugin::make())
                ->plugin(PennylanePlugin::make())
                ->plugin(AdminNavPlugin::make());
        })->register();

        LunarPanel::extensions([
            PkoProductResource::class => [
                ProductFeaturesExtension::class,
                HideLunarMediaExtension::class,
            ],
            CollectionResource::class => [
                HideLunarMediaExtension::class,
                CollectionEnabledExtension::class,
            ],
            BrandResource::class => [
                HideLunarMediaExtension::class,
                BrandContentExtension::class,
            ],
            CustomerResource::class => [
                CustomerLoyaltyExtension::class,
            ],
            ManageOrder::class => [
                OrderInvoiceActionsExtension::class,
                OrderQuoteActionsExtension::class,
            ],
            Dashboard::class => [
                DisableBrokenChartsExtension::class,
            ],
        ]);
    }

    public function boot(): void
    {
        // Vérification SIRET : le SireneClient est liaisonné par le package
        // customer-auth à partir de la config .env uniquement. On surcharge ici
        // (couche app = racine de composition) pour résoudre l'activation via le
        // Setting Back-office et les clés via le système Secrets (.env / BDD), avec
        // repli sur la config. Résolution paresseuse (closure singleton) → aucune
        // requête DB au boot, uniquement au moment de la vérification.
        $this->app->singleton(SireneClient::class, fn (): SireneClient => new SireneClient(
            baseUrl: (string) config('customer-auth.sirene.base_url'),
            consumerKey: (string) (Secrets::get('insee', 'consumer_key') ?: config('customer-auth.sirene.consumer_key')),
            consumerSecret: (string) (Secrets::get('insee', 'consumer_secret') ?: config('customer-auth.sirene.consumer_secret')),
            enabled: (bool) brand_setting('sirene.enabled', config('customer-auth.sirene.enabled')),
            timeout: (int) config('customer-auth.sirene.timeout'),
        ));

        // Garde anti-wipe : depuis un worktree PKOS (container_name fige dans
        // compose.yaml → pas d'isolation, on tape sur la base de dev weklo), on
        // interdit migrate:fresh / migrate:refresh / migrate:reset / db:wipe.
        // PKOS_WORKTREE est injecte par le Makefile (cible -e PKOS_WORKTREE=1).
        // On exclut l'env testing : la suite (RefreshDatabase) lance migrate:fresh
        // sur la base `testing` (forcee par phpunit.xml), qui est sure — la prohiber
        // casserait `make test`. Hors worktree (flag absent) : comportement inchange.
        DB::prohibitDestructiveCommands(
            (bool) env('PKOS_WORKTREE', false) && ! $this->app->environment('testing')
        );

        // Passport 13 ne fournit pas de vue de consentement par défaut : on
        // enregistre la nôtre (écran d'autorisation du connecteur MCP claude.ai).
        // Sans ça, /oauth/authorize -> BindingResolutionException.
        if (class_exists(Passport::class)) {
            Passport::authorizationView('oauth.authorize');
        }

        // Regenerate product URL slug when variants change — MPN is only
        // available after variant creation so Lunar's native post-create hook
        // runs too early. See App\Generators\PkoProductUrlGenerator::regenerate.
        ProductVariant::saved(function (ProductVariant $variant): void {
            if ($variant->product) {
                app(PkoProductUrlGenerator::class)->regenerate($variant->product);
            }
        });

        Blade::anonymousComponentPath(
            resource_path('views/filament/resources/pko-product/partials'),
            'pko-product'
        );

        // ── Collection scope : navVisible ────────────────────────────────────
        // A collection is "nav-visible" if pko_enabled=true AND no ancestor in
        // the nestedset has pko_enabled=false. The subquery uses _lft/_rgt to
        // detect ancestors without N+1 queries.
        Builder::macro('navVisible', function (): Builder {
            /** @var Builder $this */
            return $this
                ->where('pko_enabled', true)
                ->whereNotExists(function ($q): void {
                    $q->from('lunar_collections as anc')
                        ->whereColumn('anc._lft', '<', 'lunar_collections._lft')
                        ->whereColumn('anc._rgt', '>', 'lunar_collections._rgt')
                        ->where('anc.pko_enabled', false);
                });
        });

        // ── Product scope : storefrontVisible ────────────────────────────────
        // A product is visible on the storefront if it belongs to at least one
        // nav-visible collection (pko_enabled=true, no disabled ancestor).
        // Uses EXISTS + indexed columns to avoid N+1 on large catalogs.
        Builder::macro('storefrontVisible', function (): Builder {
            /** @var Builder $this */
            return $this->whereExists(function ($q): void {
                $q->from('lunar_collection_product as cp')
                    ->join('lunar_collections as svc', 'svc.id', '=', 'cp.collection_id')
                    ->whereColumn('cp.product_id', 'lunar_products.id')
                    ->where('svc.pko_enabled', true)
                    ->whereNotExists(function ($q2): void {
                        $q2->from('lunar_collections as anc')
                            ->whereColumn('anc._lft', '<', 'svc._lft')
                            ->whereColumn('anc._rgt', '>', 'svc._rgt')
                            ->where('anc.pko_enabled', false);
                    });
            });
        });
    }

    /**
     * Declare secret keys for each module so they can be toggled between .env and DB
     * storage from the admin UI. Registered at register() so the helper secret() is
     * usable during bootstrap of other providers.
     */
    private function registerSecretModules(): void
    {
        Secrets::register(
            'stripe',
            keys: [
                'public_key' => 'STRIPE_KEY',
                'secret' => 'STRIPE_SECRET',
                'webhook_lunar' => 'STRIPE_WEBHOOK_SECRET_LUNAR',
            ],
            defaultSource: 'env',
            label: 'Stripe',
            configMap: [
                'public_key' => 'services.stripe.public_key',
                'secret' => 'services.stripe.key',
                'webhook_lunar' => 'services.stripe.webhooks.lunar',
            ],
        );

        Secrets::register(
            'insee',
            keys: [
                'consumer_key' => 'INSEE_API_KEY',
                'consumer_secret' => 'INSEE_API_SECRET',
            ],
            defaultSource: 'env',
            label: 'INSEE Sirene',
            configMap: [
                'consumer_key' => 'customer-auth.sirene.consumer_key',
                'consumer_secret' => 'customer-auth.sirene.consumer_secret',
            ],
        );

        Secrets::register(
            'chronopost',
            keys: [
                'account' => 'CHRONOPOST_ACCOUNT',
                'password' => 'CHRONOPOST_PASSWORD',
                'sub_account' => 'CHRONOPOST_SUB_ACCOUNT',
            ],
            defaultSource: 'env',
            label: 'Chronopost',
            configMap: [
                'account' => 'chronopost.credentials.account',
                'password' => 'chronopost.credentials.password',
                'sub_account' => 'chronopost.credentials.sub_account',
            ],
        );

        Secrets::register(
            'colissimo',
            keys: [
                'contract_number' => 'COLISSIMO_CONTRACT',
                'password' => 'COLISSIMO_PASSWORD',
            ],
            defaultSource: 'env',
            label: 'Colissimo',
            configMap: [
                'contract_number' => 'colissimo.credentials.contract_number',
                'password' => 'colissimo.credentials.password',
            ],
        );

        Secrets::register(
            'laposte',
            keys: [
                'api_key' => 'LAPOSTE_API_KEY',
            ],
            defaultSource: 'env',
            label: 'La Poste — API Suivi',
        );

        Secrets::register(
            'pennylane',
            keys: [
                'api_token' => 'PENNYLANE_API_TOKEN',
                'invoice_template_id' => 'PENNYLANE_INVOICE_TEMPLATE_ID',
            ],
            defaultSource: 'env',
            label: 'Pennylane',
            configMap: [
                'api_token' => 'pennylane.api_token',
                'invoice_template_id' => 'pennylane.customer_invoice_template_id',
            ],
        );
    }

    /**
     * Swap 4 Lunar resources with our subclasses that override navigation placement.
     * Must run BEFORE LunarPanel::panel()->register() reads the static $resources array.
     */
    private function swapLunarResources(): void
    {
        $swaps = [
            ProductResource::class => PkoProductResource::class,
            ProductTypeResource::class => PkoProductTypeResource::class,
            ProductOptionResource::class => PkoProductOptionResource::class,
            AttributeGroupResource::class => PkoAttributeGroupResource::class,
            CollectionGroupResource::class => PkoCollectionGroupResource::class,
            TaxZoneResource::class => PkoTaxZoneResource::class,
            TaxClassResource::class => PkoTaxClassResource::class,
            TaxRateResource::class => PkoTaxRateResource::class,
            // Organisation A — clusterisation des réglages (sub-nav on-page).
            TagResource::class => PkoTagResource::class,
            ChannelResource::class => PkoChannelResource::class,
            LanguageResource::class => PkoLanguageResource::class,
            CurrencyResource::class => PkoCurrencyResource::class,
            StaffResource::class => PkoStaffResource::class,
            ActivityResource::class => PkoActivityResource::class,
        ];

        $prop = (new \ReflectionClass(LunarPanelManager::class))->getProperty('resources');

        /** @var array<int, class-string> $resources */
        $resources = $prop->getValue();

        foreach ($swaps as $original => $replacement) {
            $idx = array_search($original, $resources, true);
            if ($idx !== false) {
                $resources[$idx] = $replacement;
            }
        }

        $prop->setValue(null, $resources);
    }

    /**
     * Swap le dashboard Lunar (grille de widgets) par WekloDashboard (page design
     * custom), en conservant le slug `dashboard`. Même mécanisme de réflexion que
     * swapLunarResources() — doit tourner AVANT LunarPanel::panel()->register().
     */
    private function swapLunarPages(): void
    {
        $prop = (new \ReflectionClass(LunarPanelManager::class))->getProperty('pages');

        /** @var array<int, class-string> $pages */
        $pages = $prop->getValue();

        $idx = array_search(Dashboard::class, $pages, true);
        if ($idx !== false) {
            $pages[$idx] = WekloDashboard::class;
        }

        $prop->setValue(null, $pages);
    }
}
