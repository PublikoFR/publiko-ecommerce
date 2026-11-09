# pko/lunar-quote-requests

Formulaire de demande de devis unique et contextuel (`/demande-de-devis`) : demande
générale, projet, identification de pièce par photo ; pièces jointes privées
validées par signature binaire ; suivi des demandes en back-office Filament.

```php
quote_request_url('part_photo', ['product' => $variant, 'from' => request()->getRequestUri()]);
```

Install : `"pko/lunar-quote-requests": "@dev"` dans le `composer.json` racine, puis
`composer install`, `php artisan migrate`, `php artisan pko:mail-templates:sync`,
`make shield-sync`. Plugin Filament : `Pko\QuoteRequests\Filament\QuoteRequestsPlugin`.

Dépendances : `pko/lunar-mail-templates` (e-mails), `pko/lunar-storefront-cms`
(réglages boutique, layout storefront).

Doc : `docs/packages/quote-requests.md`.
