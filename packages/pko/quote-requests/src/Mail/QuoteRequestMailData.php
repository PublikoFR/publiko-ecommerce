<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Mail;

use Pko\MailTemplates\Support\PhoneLink;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Support\MailAttachmentSelection;

/**
 * Valeurs de placeholders des deux e-mails de demande de devis.
 */
final class QuoteRequestMailData
{
    /** @return array<string, string> */
    public static function customer(QuoteRequest $request): array
    {
        return [
            'brand_name' => function_exists('brand_name') ? brand_name() : (string) config('app.name'),
            'contact_name' => $request->name,
            'request_number' => $request->number(),
            'mode_label' => $request->mode->label(),
            'product_name' => (string) ($request->productLabel() ?? ''),
            'reference' => (string) ($request->reference ?? ''),
        ];
    }

    /** @return array<string, string> */
    public static function admin(QuoteRequest $request): array
    {
        $request->loadMissing('files');
        $context = $request->context ?? [];

        $lines = collect($request->lines ?? [])
            ->map(fn (array $line): string => trim(($line['label'] ?? '').' × '.($line['quantity'] ?? 1)))
            ->implode(' ; ');

        $productUrl = is_string($context['product_url'] ?? null) ? url($context['product_url']) : '';

        return [
            'request_number' => $request->number(),
            'mode_label' => $request->mode->label(),
            'contact_name' => $request->name,
            'company_name' => (string) ($request->company ?? '—'),
            'email' => $request->email,
            'phone' => (string) ($request->phone ?: '—'),
            'phone_url' => class_exists(PhoneLink::class) ? PhoneLink::href((string) $request->phone) : '',
            'postcode' => (string) ($request->postcode ?: '—'),
            'message' => $request->message,
            'lines' => $lines !== '' ? $lines : '—',
            'product_name' => (string) ($request->productLabel() ?? '—'),
            'reference' => (string) ($request->reference ?: '—'),
            'collection' => (string) ($context['collection_name'] ?? $request->collection ?? '—'),
            'product_url' => $productUrl,
            'from_url' => $request->from_url ? url($request->from_url) : '—',
            'attachments' => MailAttachmentSelection::for($request)->summary(),
            'attachments_count' => (string) $request->files->count(),
            'admin_url' => self::adminUrl($request),
        ];
    }

    private static function adminUrl(QuoteRequest $request): string
    {
        $route = 'filament.lunar.resources.quote-requests.view';

        return app('router')->has($route)
            ? route($route, $request)
            : url('/admin/quote-requests/'.$request->id);
    }
}
