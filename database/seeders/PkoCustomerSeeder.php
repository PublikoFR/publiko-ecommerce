<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;

class PkoCustomerSeeder extends Seeder
{
    /**
     * @var list<array{title:?string, first_name:string, last_name:string, company_name:?string, tax_identifier:?string, group:string, meta:?array<string,string>}>
     */
    private const CUSTOMERS = [
        ['title' => 'M.', 'first_name' => 'Julien', 'last_name' => 'Dupont', 'company_name' => null, 'tax_identifier' => null, 'group' => 'particuliers', 'meta' => null],
        ['title' => 'Mme', 'first_name' => 'Camille', 'last_name' => 'Martin', 'company_name' => null, 'tax_identifier' => null, 'group' => 'particuliers', 'meta' => null],
        ['title' => 'M.', 'first_name' => 'Antoine', 'last_name' => 'Bernard', 'company_name' => null, 'tax_identifier' => null, 'group' => 'particuliers', 'meta' => null],
        ['title' => 'M.', 'first_name' => 'Thierry', 'last_name' => 'Leroy', 'company_name' => 'Leroy Fermetures', 'tax_identifier' => 'FR12345678901', 'group' => 'installateurs', 'meta' => ['siret' => '12345678900015']],
        ['title' => 'Mme', 'first_name' => 'Sophie', 'last_name' => 'Girard', 'company_name' => 'Girard Domotique SARL', 'tax_identifier' => 'FR98765432109', 'group' => 'installateurs', 'meta' => ['siret' => '98765432100022']],
    ];

    public function run(): void
    {
        $groups = CustomerGroup::query()->pluck('id', 'handle');

        foreach (self::CUSTOMERS as $data) {
            $isPro = $data['group'] === 'installateurs';

            $customer = Customer::query()->updateOrCreate(
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                ],
                [
                    'title' => $data['title'],
                    'company_name' => $data['company_name'],
                    'tax_identifier' => $data['tax_identifier'],
                    'meta' => $data['meta'],
                    'sirene_status' => $isPro ? 'active' : null,
                    'sirene_verified_at' => $isPro ? now() : null,
                    'naf_code' => $isPro ? '4752A' : null,
                ],
            );

            $customer->customerGroups()->syncWithoutDetaching([
                $groups[$data['group']],
            ]);

            if ($isPro) {
                $email = strtolower($data['first_name'].'.'.$data['last_name']).'@mde-distribution.test';
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $data['first_name'].' '.$data['last_name'],
                        'password' => Hash::make('testing123'),
                    ],
                );
                $customer->users()->syncWithoutDetaching([$user->id]);
            }
        }
    }
}
