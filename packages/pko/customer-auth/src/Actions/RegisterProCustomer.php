<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Pko\CustomerAuth\Mail\CustomerRegisteredAdminMail;
use Pko\CustomerAuth\Mail\CustomerRegisteredMail;
use Pko\CustomerAuth\Sirene\SireneClient;
use Pko\CustomerAuth\Sirene\SireneResult;
use Pko\CustomerAuth\Sirene\Status;
use Pko\CustomerAuth\Support\DefaultCustomerGroup;
use Pko\MailTemplates\Support\AdminRecipient;

class RegisterProCustomer
{
    public function __construct(private SireneClient $sirene) {}

    /**
     * @param  array{siret: string, email: string, password: string, phone?: string|null, first_name?: string|null, last_name?: string|null, activity?: string|null, company_name?: string|null, street?: string|null, postcode?: string|null, city?: string|null, country?: string|null, customer_group_id?: int|null}  $data
     * @return array{user: User, customer: Customer, sirene: SireneResult}
     */
    public function handle(array $data): array
    {
        $sirene = $this->sirene->verify($data['siret']);

        if ($sirene->status === Status::Inactive) {
            throw new \DomainException('Cet établissement ne semble pas actif dans la base INSEE. Vérifiez le SIRET ou contactez-nous.');
        }

        $result = DB::transaction(function () use ($data, $sirene) {
            $customer = Customer::create([
                'company_name' => $data['company_name'] ?? $sirene->raisonSociale,
                'tax_identifier' => $this->vatFromSiret($sirene->siret),
                // lunar_customers.first_name / last_name sont NOT NULL : à l'inscription
                // pro seul le SIRET est obligatoire (prénom/nom facultatifs), on
                // retombe sur une chaîne vide plutôt que null pour éviter un 500.
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'title' => null,
                'meta' => [
                    'siret' => $sirene->siret,
                    'naf_code' => $sirene->nafCode,
                    'activity' => $data['activity'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'sirene_address' => [
                        'line_1' => $sirene->addressLine1,
                        'postcode' => $sirene->postcode,
                        'city' => $sirene->city,
                    ],
                ],
                'sirene_status' => $sirene->status->value,
                'sirene_verified_at' => $sirene->isActive() ? now() : null,
                'naf_code' => $sirene->nafCode,
                // Le compte reste en attente tant que l'e-mail n'a pas été vérifié,
                // même si l'utilisateur est auto-connecté (SIRET actif). La
                // vérification e-mail (route verification.verify) fait passer le
                // compte à 'active' — à condition que le SIRET soit lui aussi actif,
                // sinon il reste 'pending' pour validation manuelle.
                'pko_status' => 'pending',
                'pko_street' => $data['street'] ?? null,
                'pko_postcode' => $data['postcode'] ?? null,
                'pko_city' => $data['city'] ?? null,
                'pko_country' => $data['country'] ?? 'FR',
            ]);

            // Groupe par défaut attribué à toute nouvelle inscription (« Nouveau client »).
            // Résolution tolérante : un handle non slugifié saisi dans l'admin ne
            // doit pas faire silencieusement échouer le rattachement.
            $groupIds = [];
            $defaultGroup = DefaultCustomerGroup::resolve();
            if ($defaultGroup) {
                $groupIds[$defaultGroup->id] = $defaultGroup->id;
            }

            // Groupe « métier » choisi à l'inscription (liste déroulante). On ne
            // rattache que des groupes réellement marqués métier, pour éviter
            // qu'une valeur forgée n'attribue un groupe arbitraire.
            if (! empty($data['customer_group_id'])) {
                $metierGroup = CustomerGroup::where('id', $data['customer_group_id'])
                    ->where('pko_is_metier', true)
                    ->first();
                if ($metierGroup) {
                    $groupIds[$metierGroup->id] = $metierGroup->id;
                }
            }

            if ($groupIds !== []) {
                $customer->customerGroups()->attach(array_values($groupIds));
            }

            $user = User::create([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($sirene->raisonSociale ?? $data['email']),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            // email_verified_at reste null : l'utilisateur doit confirmer son adresse
            // via le lien signé du mail de bienvenue. Il a néanmoins un accès complet
            // entretemps (bandeau de rappel côté storefront).

            $customer->users()->attach($user);

            return ['user' => $user, 'customer' => $customer, 'sirene' => $sirene];
        });

        // Envoi hors transaction : User/Customer sont déjà committés. Un échec
        // SMTP ne doit jamais faire échouer l'inscription (sinon 500 + compte
        // orphelin → « email already taken » au retry). On loggue et on continue.
        try {
            $welcome = new CustomerRegisteredMail($result['customer'], $result['user']);

            // Le modèle peut être désactivé en back-office. Le lien de vérification
            // reste alors accessible via le renvoi manuel (`verification.send`),
            // l'inscription n'est donc pas bloquée.
            if ($welcome->shouldSend()) {
                Mail::to($result['user']->email)->send($welcome);
            }
        } catch (\Throwable $e) {
            logger()->error('CustomerRegisteredMail failed', [
                'email' => $result['user']->email,
                'error' => $e->getMessage(),
            ]);
        }

        // Notification interne (récap des coordonnées, onboarding téléphone).
        // Même isolation que le mail client : un échec SMTP ne compromet pas
        // l'inscription. Destinataire : Storefront → Paramètres (`admin_email`).
        try {
            AdminRecipient::send(
                new CustomerRegisteredAdminMail($result['customer'], $result['user']),
                $result['customer'],
            );
        } catch (\Throwable $e) {
            logger()->error('CustomerRegisteredAdminMail failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * VAT intra FR from SIRET : FR + cléTVA (2) + 9 premiers SIREN.
     */
    private function vatFromSiret(string $siret): ?string
    {
        $siren = substr($siret, 0, 9);
        if (! ctype_digit($siren) || strlen($siren) !== 9) {
            return null;
        }
        $key = (12 + 3 * ((int) $siren % 97)) % 97;

        return 'FR'.str_pad((string) $key, 2, '0', STR_PAD_LEFT).$siren;
    }
}
