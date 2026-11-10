<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Models\QuoteRequest;

/**
 * Enregistre un message du formulaire de contact classique dans la table des
 * demandes (mode `contact`) : l'équipe le retrouve dans la page admin
 * « Contacts » et peut le marquer comme traité. L'e-mail à la boutique reste
 * envoyé par le formulaire lui-même.
 */
final class StoreContactMessage
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, subject: string, message: string}  $data
     */
    public function handle(array $data, ?Authenticatable $user = null, ?string $ip = null): QuoteRequest
    {
        $phone = trim((string) ($data['phone'] ?? ''));

        return QuoteRequest::create([
            'mode' => QuoteRequestMode::Contact,
            'status' => QuoteRequestStatus::New,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone === '' ? null : $phone,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'customer_id' => StoreQuoteRequest::customerId($user),
            'user_id' => $user?->getAuthIdentifier(),
            'ip' => $ip,
        ]);
    }
}
