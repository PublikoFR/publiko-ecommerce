# Package `pko/lunar-secrets`

Gestion unifiée des credentials API pour les modules PKO (Stripe, Chronopost, Colissimo, futurs).

## But

Offrir à l'admin le choix **par module** de la source des credentials :
- **`env`** : les valeurs sont lues depuis le fichier `.env` (défaut, sécurité max).
- **`db`** : les valeurs sont éditables dans l'admin Filament et stockées en base, chiffrées via le cast Laravel `encrypted` (clé `APP_KEY`).

Le choix est persisté dans `pko_storefront_settings` sous `secrets.{module}.source`.

## Composants

| Élément | Rôle |
|---|---|
| `Pko\Secrets\Registry` | Singleton listant les modules enregistrés avec leurs clés logiques, la map env et la map de config paths à réécrire |
| `Pko\Secrets\SecretStore` | Façade applicative (`Secrets::get/set/useDatabase/useEnv/source`) |
| `Pko\Secrets\Providers\EnvProvider` | Source readonly → lit via `env()` |
| `Pko\Secrets\Providers\DatabaseProvider` | Source lecture/écriture → table `pko_secrets` + cache `pko.secrets.db.v1` (TTL 1 h) |
| `Pko\Secrets\Models\Secret` | Modèle Eloquent (cast `value => encrypted`) sur `pko_secrets` |
| `secret('stripe.secret')` | Helper global auto-loadé via `autoload.files` |
| `Pko\Secrets\Filament\Forms\SecretsFormSchema` | Factory de Filament `Section` réutilisable (toggle source + inputs conditionnels) |

## Enregistrement d'un module

Dans `app/Providers/AppServiceProvider::register()` (ou le provider d'un package tiers) :

```php
use Pko\Secrets\Facades\Secrets;

Secrets::register(
    'stripe',
    keys: [
        'public_key'    => 'STRIPE_KEY',
        'secret'        => 'STRIPE_SECRET',
        'webhook_lunar' => 'STRIPE_WEBHOOK_SECRET_LUNAR',
    ],
    defaultSource: 'env',
    label: 'Stripe',
    configMap: [
        // Optionnel : lorsque le module est en mode db, ces config paths
        // sont réécrits au boot pour que Lunar/Stripe/etc. lisent la valeur DB.
        'public_key'    => 'services.stripe.public_key',
        'secret'        => 'services.stripe.key',
        'webhook_lunar' => 'services.stripe.webhooks.lunar',
    ],
);
```

## Lecture

```php
Secrets::get('stripe', 'secret');
// ou via helper :
secret('stripe.secret');
```

En mode `env`, c'est équivalent à `env('STRIPE_SECRET')`. En mode `db`, c'est la valeur déchiffrée de la ligne `pko_secrets` correspondante.

## Bascule de source

Depuis n'importe où :

```php
Secrets::useDatabase('stripe');  // persiste secrets.stripe.source = 'db'
Secrets::useEnv('stripe');       // persiste secrets.stripe.source = 'env'
```

Depuis l'UI Filament, c'est fait automatiquement par `SecretsFormSchema::save(...)` quand l'utilisateur soumet le formulaire.

## Intégration dans une page Filament config

```php
use Pko\Secrets\Filament\Forms\SecretsFormSchema;

class StripeConfig extends BasePage implements HasForms
{
    use InteractsWithForms;

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(SecretsFormSchema::initialData('stripe'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                SecretsFormSchema::make('stripe', [
                    'public_key'    => 'Clé publique',
                    'secret'        => 'Clé secrète',
                    'webhook_lunar' => 'Webhook signing secret',
                ], heading: 'Credentials Stripe'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SecretsFormSchema::save('stripe', $this->form->getState());
    }
}
```

Côté Blade :

```blade
<form wire:submit="save">
    {{ $this->form }}
    <x-filament::button type="submit">Enregistrer</x-filament::button>
</form>
```

## Backfill automatique de `config()`

Au `boot()` de `SecretsServiceProvider`, chaque module en mode `db` se voit réécrire les `config()` paths déclarés via `configMap`. Ainsi une lib tierce qui lit `config('services.stripe.key')` obtient la valeur DB sans aucun patch.

No-op si la table `pko_secrets` ou `pko_storefront_settings` n'existe pas encore (phase d'install / migrations fraîches).

## Table `pko_secrets`

| Colonne | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `module` | varchar(64) | nom court (`stripe`, `chronopost`…) |
| `key` | varchar(128) | clé logique (`secret`, `password`…) |
| `value` | text nullable | **cast `encrypted`** — chiffré via APP_KEY |
| `timestamps` | | |
| Index | UNIQUE(`module`, `key`) | |

## Modules actuellement enregistrés

| Module | Clés logiques | Source par défaut |
|---|---|---|
| `stripe` | `public_key`, `secret`, `webhook_lunar` | `env` |
| `chronopost` | `account`, `password`, `sub_account` | `env` |
| `colissimo` | `contract_number`, `password` | `env` |

## Sécurité

- Les valeurs DB sont chiffrées (`encrypted` cast). Une fuite de dump DB sans `APP_KEY` ne compromet pas les secrets.
- Le helper `secret()` ne loggue jamais la valeur.
- Les champs password du form Filament sont `password()->revealable()` — visibles uniquement après clic.
- Basculer de `db` → `env` **ne vide pas** la table : les valeurs DB sont conservées et réutilisables au retour en `db`.
