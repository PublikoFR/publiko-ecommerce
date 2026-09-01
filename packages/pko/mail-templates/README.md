# pko/lunar-mail-templates

Socle des e-mails transactionnels : le **contenu** (sujet + blocs) vit en base
(`pko_mail_templates`) et reste éditable, le **déclencheur** vit dans le code.

```php
Mail::to($user)->send(new TemplatedMail('order.confirmed', [
    'first_name' => $customer->first_name,
    'order_reference' => $order->reference,
]));
```

- Clés et placeholders déclarés dans `MailTemplateRegistry` (code, neutre de marque).
- Contenus par défaut dans `database/content/fr.php` (data, remplaçable par boutique).
- Si une clé n'existe pas en base, le contenu par défaut est utilisé — rien ne casse.
- Preview locale : `/_mail/{key}` (APP_ENV=local uniquement).

Dépendances : aucune sur les autres packages PKO. Le layout d'habillage est fourni
par `storefront-cms` s'il est présent, sinon un layout minimal interne.
