<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pko\MailTemplates\Support\OnceMailer;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Mail\QuoteRequestAdminMail;
use Pko\QuoteRequests\Mail\QuoteRequestReceivedMail;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Support\FileSignature;
use Pko\QuoteRequests\Support\QuoteRequestRecipient;
use Throwable;

/**
 * Enregistre une demande de devis, range ses pièces jointes sur le disque
 * privé puis met en file les deux e-mails (équipe + accusé de réception).
 *
 * Les fichiers sont supposés déjà validés (taille, nombre, signature) par le
 * formulaire ; la signature est relue ici pour figer le MIME réel en base, pas
 * celui annoncé par le navigateur.
 */
final class StoreQuoteRequest
{
    /**
     * @param  array{name: string, company?: string|null, email: string, phone?: string|null, postcode?: string|null, message: string}  $data
     * @param  array<int, array{label: string, quantity: int}>  $lines
     * @param  array<string, mixed>  $context  cf. QuoteContext::fromQuery()
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(
        QuoteRequestMode $mode,
        array $data,
        array $lines,
        array $context,
        array $files,
        ?Authenticatable $user = null,
        ?string $ip = null,
    ): QuoteRequest {
        $disk = (string) config('quote-requests.attachments.disk', 'local');
        $directory = trim((string) config('quote-requests.attachments.directory', 'quote-requests'), '/');
        $stored = [];

        try {
            $request = DB::transaction(function () use ($mode, $data, $lines, $context, $files, $user, $ip, $disk, $directory, &$stored): QuoteRequest {
                $request = QuoteRequest::create([
                    'mode' => $mode,
                    'status' => 'new',
                    'name' => $data['name'],
                    'company' => self::nullable($data['company'] ?? null),
                    'email' => $data['email'],
                    'phone' => self::nullable($data['phone'] ?? null),
                    'postcode' => self::nullable($data['postcode'] ?? null),
                    'message' => $data['message'],
                    'lines' => $lines === [] ? null : array_values($lines),
                    'product_id' => $context['product_id'] ?? null,
                    'product_variant_id' => $context['product_variant_id'] ?? null,
                    'reference' => $context['reference'] ?? null,
                    'collection' => $context['collection'] ?? null,
                    'from_url' => $context['from'] ?? null,
                    'context' => array_filter([
                        'product_name' => $context['product_name'] ?? null,
                        'product_url' => $context['product_url'] ?? null,
                        'collection_name' => $context['collection_name'] ?? null,
                    ]) ?: null,
                    'customer_id' => self::customerId($user),
                    'user_id' => $user?->getAuthIdentifier(),
                    'ip' => $ip,
                ]);

                foreach ($files as $file) {
                    $type = FileSignature::detect((string) $file->getRealPath(), $file->getClientOriginalName());
                    if ($type === null) {
                        continue;
                    }

                    $extension = FileSignature::TYPES[$type]['extensions'][0];
                    $path = $file->storeAs(
                        $directory.'/'.$request->id,
                        Str::random(32).'.'.$extension,
                        ['disk' => $disk],
                    );
                    if ($path === false) {
                        throw new \RuntimeException('Impossible d\'enregistrer une pièce jointe.');
                    }
                    $stored[] = $path;

                    $request->files()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                        'mime_type' => FileSignature::mime($type),
                        'size' => (int) $file->getSize(),
                    ]);
                }

                return $request;
            });
        } catch (Throwable $e) {
            // Transaction annulée : on ne laisse pas de fichier privé orphelin.
            Storage::disk($disk)->delete($stored);

            throw $e;
        }

        $this->notify($request);

        return $request;
    }

    private function notify(QuoteRequest $request): void
    {
        $recipient = QuoteRequestRecipient::email();
        if ($recipient !== '') {
            OnceMailer::send(new QuoteRequestAdminMail($request), $recipient, $request);
        }

        OnceMailer::send(new QuoteRequestReceivedMail($request), $request->email, $request);
    }

    public static function customerId(?Authenticatable $user): ?int
    {
        $customer = $user !== null && method_exists($user, 'latestCustomer') ? $user->latestCustomer() : null;

        return $customer?->id;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
