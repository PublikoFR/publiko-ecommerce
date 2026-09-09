<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Support;

use App\Models\User;
use Lunar\Models\Customer;
use Pko\MailTemplates\Support\PhoneLink;

/**
 * Placeholders de la notification équipe envoyée à chaque inscription.
 *
 * @return array<string, string>
 */
final class CustomerAdminMailData
{
    /** @return array<string, string> */
    public static function values(Customer $customer, User $user): array
    {
        $phone = (string) data_get($customer->meta, 'phone', '');
        $siret = (string) data_get($customer->meta, 'siret', '');
        $activity = (string) data_get($customer->meta, 'activity', '');

        $address = implode(', ', array_filter([
            $customer->pko_street,
            trim(($customer->pko_postcode ?? '').' '.($customer->pko_city ?? '')),
            $customer->pko_country,
        ], static fn (mixed $part): bool => is_string($part) && $part !== ''));

        return [
            'company_name' => (string) ($customer->company_name ?? ''),
            'contact_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
            'email' => (string) $user->email,
            'phone' => $phone,
            'phone_url' => PhoneLink::href($phone),
            'siret' => $siret,
            'vat_number' => (string) ($customer->tax_identifier ?? ''),
            'naf_code' => (string) ($customer->naf_code ?? ''),
            'activity' => $activity,
            'address' => $address,
            'groups' => $customer->customerGroups()->pluck('name')->implode(', '),
            'status' => (string) ($customer->pko_status ?? ''),
            'sirene_status' => (string) ($customer->sirene_status ?? ''),
            'admin_url' => url('/admin/customers/'.$customer->id),
        ];
    }
}
