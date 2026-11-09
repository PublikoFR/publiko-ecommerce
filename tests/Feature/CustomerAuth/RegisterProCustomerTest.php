<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerAuth;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Actions\RegisterProCustomer;
use Pko\CustomerAuth\Mail\AccountActivatedMail;
use Pko\CustomerAuth\Mail\CustomerRegisteredAdminMail;
use Pko\CustomerAuth\Mail\CustomerRegisteredMail;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\SireneResult;
use Pko\CustomerAuth\Sirene\Status;
use Pko\CustomerAuth\Support\EmailVerification;
use Pko\MailTemplates\Support\AdminRecipient;
use Pko\StorefrontCms\Models\Setting;
use Tests\TestCase;

class RegisterProCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function mockSireneActive(string $siret = '98104397900021'): void
    {
        $result = new SireneResult(
            status: Status::Active,
            siret: $siret,
            raisonSociale: 'ACME SAS',
            nafCode: '43.21A',
            addressLine1: '10 RUE DE LA PAIX',
            postcode: '75002',
            city: 'PARIS',
        );

        $mock = $this->createMock(SireneClient::class);
        $mock->method('verify')->willReturn($result);

        $this->app->instance(SireneClient::class, $mock);
    }

    private function defaultData(array $overrides = []): array
    {
        return array_merge([
            'siret' => '98104397900021',
            'email' => 'pro@example.test',
            'password' => 'secret123',
            'street' => '12 avenue des Roses',
            'postcode' => '69001',
            'city' => 'Lyon',
            'country' => 'FR',
        ], $overrides);
    }

    public function test_adresse_postale_est_persistee_sur_le_customer(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $customer = Customer::find($result['customer']->id);
        $this->assertSame('12 avenue des Roses', $customer->pko_street);
        $this->assertSame('69001', $customer->pko_postcode);
        $this->assertSame('Lyon', $customer->pko_city);
        $this->assertSame('FR', $customer->pko_country);
    }

    public function test_country_par_defaut_fr_si_absent(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $data = $this->defaultData();
        unset($data['country']);
        $result = app(RegisterProCustomer::class)->handle($data);

        $this->assertSame('FR', Customer::find($result['customer']->id)->pko_country);
    }

    public function test_email_confirmation_est_envoye(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        Mail::assertSent(CustomerRegisteredMail::class, function (CustomerRegisteredMail $mail) use ($result) {
            return $mail->hasTo($result['user']->email)
                && $mail->customer->id === $result['customer']->id;
        });
    }

    public function test_notification_admin_ne_part_pas_a_linscription(): void
    {
        // FEAT-006 : l'équipe est prévenue quand le compte est prêt à valider,
        // c'est-à-dire à la vérification de l'e-mail — pas à l'inscription.
        $this->mockSireneActive();
        Mail::fake();
        $this->configureAdminRecipient();

        app(RegisterProCustomer::class)->handle($this->defaultData());

        Mail::assertNotSent(CustomerRegisteredAdminMail::class);
    }

    public function test_email_reste_non_verifie_a_la_creation(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        // L'e-mail doit être confirmé via le lien signé du mail de bienvenue.
        $this->assertNull($result['user']->email_verified_at);
        $this->assertFalse($result['user']->hasVerifiedEmail());
    }

    public function test_siret_actif_laisse_le_compte_en_pending_avant_verification_email(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        // Le compte reste 'pending' même avec un SIRET actif : il ne passe
        // 'active' qu'à la validation manuelle par l'équipe (FEAT-006).
        $this->assertSame('pending', Customer::find($result['customer']->id)->pko_status);
    }

    public function test_groupe_par_defaut_est_attache(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $handles = $result['customer']->customerGroups()->pluck('handle')->toArray();
        $default = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');
        $this->assertContains($default, $handles, 'Le groupe par défaut doit être attaché au nouveau client.');
    }

    public function test_groupe_metier_choisi_est_attache(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $metier = CustomerGroup::where('pko_is_metier', true)->firstOrFail();

        $result = app(RegisterProCustomer::class)->handle(
            $this->defaultData(['customer_group_id' => $metier->id])
        );

        $handles = $result['customer']->customerGroups()->pluck('handle')->toArray();
        $this->assertContains($metier->handle, $handles, 'Le groupe métier choisi doit être attaché.');
    }

    public function test_groupe_metier_et_groupe_par_defaut_sont_attaches_ensemble(): void
    {
        // Régression : à l'inscription avec un métier choisi, le client doit être
        // rattaché AUX DEUX groupes simultanément — « Nouveau client » (défaut) ET
        // le métier. Un test qui vérifie chaque groupe isolément ne détecte pas une
        // régression où le métier écrase le groupe par défaut (ou l'inverse).
        $this->mockSireneActive();
        Mail::fake();

        $metier = CustomerGroup::where('pko_is_metier', true)->firstOrFail();

        $result = app(RegisterProCustomer::class)->handle(
            $this->defaultData(['customer_group_id' => $metier->id])
        );

        $handles = $result['customer']->customerGroups()->pluck('handle')->toArray();
        $default = (string) config('customer-auth.default_customer_group_handle', 'nouveau-client');

        $this->assertContains($default, $handles, 'Le groupe par défaut doit rester attaché même quand un métier est choisi.');
        $this->assertContains($metier->handle, $handles, 'Le groupe métier choisi doit être attaché.');
        $this->assertCount(2, $handles, 'Exactement deux groupes attendus : défaut + métier.');
    }

    public function test_groupe_attache_correspond_au_groupe_en_base(): void
    {
        $group = CustomerGroup::where('handle', (string) config('customer-auth.default_customer_group_handle', 'nouveau-client'))->first();

        $this->assertNotNull(
            $group,
            'Le groupe "'.config('customer-auth.default_customer_group_handle', 'nouveau-client').'" doit exister en base (PkoCustomerGroupSeeder).'
        );
    }

    public function test_groupe_par_defaut_est_attache_meme_si_son_handle_nest_pas_slugifie(): void
    {
        // Régression (constatée en dev) : le groupe « Nouveau client » créé à la
        // main depuis l'admin Filament garde un handle non slugifié
        // (« Nouveau client »). Le lookup strict `where('handle','nouveau-client')`
        // ne matchait alors rien : aucun groupe attaché à l'inscription, et accès
        // pro refusé, sans la moindre erreur visible.
        $default = CustomerGroup::where('handle', 'nouveau-client')->firstOrFail();

        // Écriture brute : passer par Eloquent déclencherait l'observer, qui
        // re-slugifierait aussitôt le handle — on veut justement l'état dégradé.
        DB::table('lunar_customer_groups')
            ->where('id', $default->id)
            ->update(['handle' => 'Nouveau client']);

        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $ids = $result['customer']->customerGroups()->pluck('lunar_customer_groups.id')->all();
        $this->assertContains(
            $default->id,
            $ids,
            'Le groupe par défaut doit être attaché même quand son handle est resté non slugifié.'
        );
    }

    public function test_handle_dun_groupe_est_slugifie_a_lenregistrement(): void
    {
        $group = CustomerGroup::create([
            'name' => 'Électricien courant faible',
            'handle' => 'Électricien courant faible',
        ]);

        $this->assertSame('electricien-courant-faible', $group->fresh()->handle);
    }

    public function test_handle_du_groupe_par_defaut_ne_peut_pas_deriver(): void
    {
        $default = CustomerGroup::where('handle', 'nouveau-client')->firstOrFail();

        $default->handle = 'autre-chose';
        $default->save();

        $this->assertSame(
            'nouveau-client',
            $default->fresh()->handle,
            'Le handle du groupe par défaut est verrouillé : inscription et accès pro en dépendent.'
        );
    }

    public function test_verification_email_ne_connecte_pas_et_laisse_le_compte_pending(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());
        $user = $result['user'];

        $this->get(EmailVerification::signedUrl($user))
            ->assertRedirect(route('register.email-verified'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame('pending', Customer::find($result['customer']->id)->pko_status);
        $this->assertGuest();
        Mail::assertNotSent(AccountActivatedMail::class);
    }

    public function test_verification_email_meme_si_siret_pending_laisse_le_compte_pending(): void
    {
        $mock = $this->createMock(SireneClient::class);
        $mock->method('verify')->willReturn(new SireneResult(
            status: Status::Pending,
            siret: '98104397900021',
        ));
        $this->app->instance(SireneClient::class, $mock);
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());

        $this->get(EmailVerification::signedUrl($result['user']));

        $this->assertSame('pending', Customer::find($result['customer']->id)->pko_status);
    }

    public function test_verification_email_notifie_lequipe_avec_les_coordonnees(): void
    {
        $this->mockSireneActive();
        Mail::fake();
        $this->configureAdminRecipient();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData([
            'phone' => '06 12 34 56 78',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'company_name' => 'ACME SAS',
        ]));

        $this->get(EmailVerification::signedUrl($result['user']));

        Mail::assertSent(CustomerRegisteredAdminMail::class, 1);
        Mail::assertSent(CustomerRegisteredAdminMail::class, function (CustomerRegisteredAdminMail $mail) use ($result): bool {
            $this->assertTrue($mail->hasTo('ops@example.test'));
            $this->assertSame('pro@example.test', $mail->values['email']);
            $this->assertSame('ACME SAS', $mail->values['company_name']);
            $this->assertSame('06 12 34 56 78', $mail->values['phone']);
            $this->assertSame('tel:+33612345678', $mail->values['phone_url']);
            $mail->build();
            $this->assertTrue($mail->hasReplyTo($result['user']->email));

            return true;
        });
    }

    public function test_notification_admin_absente_si_pas_de_destinataire(): void
    {
        $this->mockSireneActive();
        Mail::fake();
        Setting::set('admin_email', '');
        config()->set('customer-auth.admin_notification_email', null);
        config()->set('loyalty.admin_email', null);

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());
        $this->get(EmailVerification::signedUrl($result['user']));

        Mail::assertNotSent(CustomerRegisteredAdminMail::class);
    }

    public function test_lien_deja_utilise_est_idempotent(): void
    {
        $this->mockSireneActive();
        Mail::fake();
        $this->configureAdminRecipient();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());
        $url = EmailVerification::signedUrl($result['user']);

        $this->get($url)->assertRedirect(route('register.email-verified'));
        $this->get($url)->assertRedirect(route('register.email-verified'));

        // Un seul mail équipe : le second clic ne renotifie pas.
        Mail::assertSent(CustomerRegisteredAdminMail::class, 1);
        $this->assertSame('pending', Customer::find($result['customer']->id)->pko_status);
    }

    public function test_page_de_confirmation_annonce_la_validation_par_lequipe(): void
    {
        $this->get(route('register.email-verified'))
            ->assertOk()
            ->assertSee('Adresse e-mail confirmée')
            ->assertSee('valider votre compte pro')
            ->assertDontSee('Me connecter');
    }

    public function test_page_de_confirmation_propose_la_connexion_si_compte_deja_actif(): void
    {
        $this->mockSireneActive();
        Mail::fake();

        $result = app(RegisterProCustomer::class)->handle($this->defaultData());
        $result['customer']->update(['pko_status' => 'active']);

        $this->followingRedirects()
            ->get(EmailVerification::signedUrl($result['user']))
            ->assertOk()
            ->assertSee('Me connecter');
    }

    private function configureAdminRecipient(): void
    {
        Setting::set('admin_email', 'ops@example.test');
        Setting::forget();
        config()->set('customer-auth.admin_notification_email', 'ops@example.test');

        $this->assertSame('ops@example.test', AdminRecipient::email());
    }
}
