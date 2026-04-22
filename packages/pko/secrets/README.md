# pko/lunar-secrets

Gestion unifiée des credentials des modules PKO avec choix de source **par module** : `env` (valeurs lues depuis `.env`) ou `db` (table `pko_secrets`, cast `encrypted`).

## Enregistrement d'un module

```php
use Pko\Secrets\Facades\Secrets;

Secrets::register('stripe', [
    'public_key'    => 'STRIPE_KEY',
    'secret'        => 'STRIPE_SECRET',
    'webhook_lunar' => 'STRIPE_WEBHOOK_SECRET_LUNAR',
], defaultSource: 'env');
```

## Lecture

```php
$secret = Secrets::get('stripe', 'secret');
// ou
$secret = secret('stripe.secret');
```

## Écriture (mode DB uniquement)

```php
Secrets::set('stripe', 'secret', 'sk_live_xxx');
```

## Toggle de source (par module)

Stocké dans `pko_storefront_settings` sous la clé `secrets.{module}.source`.

```php
Secrets::useDatabase('stripe');
Secrets::useEnv('stripe');
Secrets::source('stripe'); // 'env' | 'db'
```

Les valeurs DB sont chiffrées via le cast Laravel `encrypted` (clé `APP_KEY`).
