<?php

declare(strict_types=1);

use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Support\QuoteRequestUrl;

if (! function_exists('quote_request_url')) {
    /**
     * URL du formulaire de devis unique, avec mode et contexte d'origine.
     *
     * @param  array{product?: mixed, ref?: string|null, collection?: string|null, from?: string|null}  $context
     */
    function quote_request_url(QuoteRequestMode|string $mode = 'general', array $context = []): string
    {
        return QuoteRequestUrl::make($mode, $context);
    }
}
