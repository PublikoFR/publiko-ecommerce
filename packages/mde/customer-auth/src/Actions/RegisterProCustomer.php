<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Mde\CustomerAuth\Sirene\SireneClient;
use Mde\CustomerAuth\Sirene\SireneResult;
use Mde\CustomerAuth\Sirene\Status;

class RegisterProCustomer
{
    public function __construct(private SireneClient $sirene) {}

    /**
     * @param  array{siret: string, email: string, password: string, phone?: string|null, first_name?: string|null, last_name?: string|null, activity?: string|null, company_name?: string|null}  $data
     * @return array{user: User, customer: Customer, sirene: SireneResult}
     */
    public function handle(array $data): array
    {
        $sirene = $this->sirene->verify($data['siret']);

        if ($sirene->status === Status::Inactive) {
            throw new \DomainException('Cet établissement ne semble pas actif dans la base INSEE. Vérifiez le SIRET ou contactez-nous.');
        }

        return DB::transaction(function () use ($data, $sirene) {
            $customer = Customer::create([
                'company_name' => $data['company_name'] ?? $sirene->raisonSociale,
                'tax_identifier' => $this->vatFromSiret($sirene->siret),
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
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
            ]);

            $groupHandle = (string) config('mde-customer-auth.default_customer_group_handle', 'installateurs');
            $group = CustomerGroup::where('handle', $groupHandle)->first();
            if ($group) {
                $customer->customerGroups()->attach($group);
            }

            $user = User::create([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($sirene->raisonSociale ?? $data['email']),
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $customer->users()->attach($user);

            return ['user' => $user, 'customer' => $customer, 'sirene' => $sirene];
        });
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
