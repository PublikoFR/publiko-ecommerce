<?php

declare(strict_types=1);

namespace Pko\Pennylane\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Pko\Pennylane\Api\Exceptions\PennylaneApiException;
use Pko\Pennylane\Api\Resources\CustomersResource;
use Pko\Pennylane\Dto\CustomerData;
use Pko\Pennylane\Models\PennylaneCustomer;

final class CustomerMapper
{
    public function __construct(private readonly CustomersResource $customers) {}

    public function resolveOrCreate(Order $order): int
    {
        $customer = $order->customer;
        $billing = $order->billingAddress ?: $order->shippingAddress;

        $externalReference = $this->externalReference($order);

        $mapping = $customer
            ? PennylaneCustomer::where('lunar_customer_id', $customer->id)->first()
            : PennylaneCustomer::where('external_reference', $externalReference)->first();

        if ($mapping && $mapping->pennylane_customer_id) {
            $this->completeRegNoQuietly($mapping);

            return (int) $mapping->pennylane_customer_id;
        }

        $dto = $this->buildDto($order, $customer, $billing, $externalReference);

        $existing = $this->customers->findByExternalReference($externalReference);
        $pennylaneId = $existing['id'] ?? null;

        if (! $pennylaneId) {
            try {
                $created = $this->customers->create($dto->toArray(), $dto->isCompany);
                $pennylaneId = $created['id'] ?? null;
            } catch (PennylaneApiException $e) {
                if ($e->status === 409) {
                    $retry = $this->customers->findByExternalReference($externalReference);
                    $pennylaneId = $retry['id'] ?? null;
                } else {
                    throw $e;
                }
            }
        }

        if (! $pennylaneId) {
            throw new \RuntimeException('Impossible de résoudre le customer Pennylane (pas d\'id retourné).');
        }

        PennylaneCustomer::updateOrCreate(
            $customer
                ? ['lunar_customer_id' => $customer->id]
                : ['external_reference' => $externalReference],
            [
                'external_reference' => $externalReference,
                'pennylane_customer_id' => $pennylaneId,
                'payload_snapshot' => $dto->toArray(),
                'synced_at' => Carbon::now(),
            ],
        );

        return (int) $pennylaneId;
    }

    /**
     * Client Pennylane d'un client Lunar, hors contexte commande (avoir
     * autonome). Sans correspondance existante, la fiche est créée depuis la
     * dernière commande du client (adresse de facturation obligatoire).
     */
    public function resolveForCustomer(Customer $customer): int
    {
        $mapping = PennylaneCustomer::where('lunar_customer_id', $customer->id)->first();

        if ($mapping && $mapping->pennylane_customer_id) {
            $this->completeRegNoQuietly($mapping);

            return (int) $mapping->pennylane_customer_id;
        }

        $order = Order::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('placed_at')
            ->latest('placed_at')
            ->first();

        if (! $order) {
            throw new \RuntimeException("Client {$customer->id} sans commande : impossible de créer sa fiche Pennylane (adresse de facturation inconnue).");
        }

        return $this->resolveOrCreate($order);
    }

    /**
     * Complète le SIREN (`reg_no`) d'un client entreprise déjà créé dans
     * Pennylane sans lui : les fiches créées avant la lecture de
     * `meta['siret']` sont restées incomplètes. Retourne true si mis à jour.
     */
    public function completeRegNo(PennylaneCustomer $mapping): bool
    {
        $siren = CustomerData::sirenFrom($this->siretOf($mapping->customer));
        $snapshot = $mapping->payload_snapshot?->getArrayCopy() ?? [];

        // `name` n'existe que dans le payload entreprise : un particulier n'a pas de SIREN.
        if ($siren === null
            || ! $mapping->pennylane_customer_id
            || ! isset($snapshot['name'])
            || ($snapshot['reg_no'] ?? null) === $siren) {
            return false;
        }

        $this->customers->update((int) $mapping->pennylane_customer_id, ['reg_no' => $siren], true);

        $snapshot['reg_no'] = $siren;
        $mapping->payload_snapshot = $snapshot;
        $mapping->synced_at = Carbon::now();
        $mapping->save();

        return true;
    }

    /** Un SIREN manquant ne doit jamais bloquer l'émission d'une facture. */
    private function completeRegNoQuietly(PennylaneCustomer $mapping): void
    {
        try {
            $this->completeRegNo($mapping);
        } catch (\Throwable $e) {
            Log::warning('Pennylane : échec de la mise à jour du SIREN client', [
                'pennylane_customer_id' => $mapping->pennylane_customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Le SIRET est posé dans `meta` à l'inscription pro ; `account_ref` en repli (saisie back-office). */
    private function siretOf(?Customer $customer): ?string
    {
        $siret = $customer?->meta['siret'] ?? null;

        return filled($siret) ? (string) $siret : ($customer?->account_ref ?: null);
    }

    private function externalReference(Order $order): string
    {
        $prefix = (string) config('pennylane.external_reference_prefix.customer', 'lunar_cust_');

        return $order->customer_id
            ? $prefix.$order->customer_id
            : $prefix.'order_'.$order->id;
    }

    private function buildDto(
        Order $order,
        ?Customer $customer,
        ?OrderAddress $billing,
        string $externalReference,
    ): CustomerData {
        $company = $customer?->company_name ?: ($billing?->company_name ?? null);
        $isCompany = ! empty($company);

        $firstName = $billing?->first_name ?? $customer?->first_name;
        $lastName = $billing?->last_name ?? $customer?->last_name;
        $name = $isCompany
            ? $company
            : trim(($firstName ?? '').' '.($lastName ?? ''));

        if ($name === '') {
            $name = $billing?->contact_email ?? 'Client '.$order->id;
        }

        return new CustomerData(
            externalReference: $externalReference,
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            email: $billing?->contact_email ?: $this->userEmail($order),
            vatNumber: $customer?->tax_identifier ?? $billing?->tax_identifier,
            siret: $this->siretOf($customer),
            phone: $billing?->contact_phone,
            addressLine1: $billing?->line_one,
            addressLine2: $billing?->line_two,
            postalCode: $billing?->postcode,
            city: $billing?->city,
            countryAlpha2: $billing?->country?->iso2,
            isCompany: $isCompany,
            language: (string) config('pennylane.default_language', 'fr'),
        );
    }

    private function userEmail(Order $order): ?string
    {
        try {
            return $order->user?->email;
        } catch (\Throwable) {
            return null;
        }
    }
}
