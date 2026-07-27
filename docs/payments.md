# Paiements — Stripe

### 4.1 Choix : Lunar Payments natif + addon Stripe officiel

**Décision** : le système de paiement Lunar est driver-based. Chaque type de paiement (`card`, `sepa`…) est défini dans `config/lunar/payments.php` et mappe vers un driver enregistré via `Payments::extend('<driver>', ...)`.

**Drivers installés** :

| Type Lunar | Driver | Package | Webhook |
|---|---|---|---|
| `card` | `stripe` | `lunarphp/stripe` | `POST /stripe/webhook` |
| `sepa` | `stripe` | `lunarphp/stripe` + `App\Livewire\SepaPaymentForm` | `POST /stripe/webhook` |

**SEPA Direct Debit (F26)** :
- Affiché uniquement si `customer->sepa_enabled === true` (colonne `lunar_customers.sepa_enabled`).
- Composant dédié `App\Livewire\SepaPaymentForm` qui crée un `PaymentIntent` avec `payment_method_types: ['sepa_debit']` (distinct de l'intent carte, stocké dans `cart.meta['sepa_intent_id']`).
- Spécificité SEPA : après `confirmPayment()`, Stripe retourne `status = 'processing'` (async, 2-5 jours). Le webhook `payment_intent.succeeded` met à jour le statut final. Dans `mount()` de `CheckoutPage`, le code force `placed_at` + statut `payment-pending` pour SEPA `processing`, puis redirige vers la page de confirmation.
- Vue Stripe publiée et traduite : `resources/views/vendor/lunar/stripe/components/payment-form.blade.php` (bouton "Payer", spinner FR).
- Type `paymentType` inclus dans `$queryString` pour survivre au redirect Stripe.

### 4.2 Rejet de Laravel Cashier

**Pourquoi pas Cashier** :

- Cashier est un moteur d'**abonnement SaaS** lié à `App\Models\User`, pas un encaisseur de commandes.
- Il duplique les tables Lunar (`lunar_orders`, `lunar_transactions`) et casse la cohérence.
- Les transactions Stripe doivent transiter par `Lunar\Models\Transaction` pour que l'historique commande reste unifié.

**Règle** : Cashier réservé à d'éventuels abonnements dédiés s'ils apparaissent un jour (produits par abonnement, facturation récurrente). Jamais pour encaisser une commande.

### 4.3 Stripe (lunarphp/stripe)

- Config : `config/lunar/stripe.php` (`policy`, `webhook_path`, `status_mapping`…)
- Credentials dans `config/services.php` → `'stripe'`
- Env vars : `STRIPE_PK`, `STRIPE_SECRET`, `LUNAR_STRIPE_WEBHOOK_SECRET`
- Webhook URL publique à renseigner dans le Stripe Dashboard : `https://<host>/stripe/webhook`
- Migration dédiée : `lunar_stripe_payment_intents`
- Page admin dédiée : `app/Filament/Pages/StripeConfig.php` (groupe **Configuration**) avec bouton « Tester la connexion » qui appelle `StripeClient::balance->retrieve()`.

### 4.4 Paiement d'une commande sur devis (lien Stripe)

Les commandes `awaiting-quote` (produits `pko_quote_only`, cf. `docs/shipping.md` §5.3) sont réglées via un **lien de paiement** hors checkout standard, géré par `Pko\ShippingCommon\Http\Controllers\QuotePaymentController` :

- Réutilise l'addon `lunarphp/stripe` déjà en place (`\Stripe\PaymentIntent`), **pas** de nouveau driver ni de Cashier.
- Le PaymentIntent est créé pour `total commande + frais de port final` (frais transmis dans l'URL signée, persistés dans `order.meta['quote_payment']`).
- Le `return_url` Stripe (`pko.quote.pay.confirm`) vérifie le PaymentIntent côté serveur, enregistre une `Lunar\Models\Transaction` (driver `stripe`, type `capture`) et bascule la commande en `payment-received` → déclenche la création des expéditions.
- Ce flux n'utilise **pas** le webhook `stripe/webhook` de l'addon (celui-ci est lié au flux cart→order). La confirmation passe par le `return_url` + vérification serveur de l'intent.
- Tests : `tests/Feature/Shipping/QuotePaymentControllerTest.php` (mock via `Lunar\Stripe\Facades\Stripe::fake()`, aucun appel Stripe réel).

### 4.4b Lien dashboard Stripe sur les transactions (admin)

Le composant infolist des transactions de commande (`lunarpanel::infolists.components.transaction`) est **overridé** dans `resources/views/vendor/lunarpanel/infolists/components/transaction.blade.php` (mécanisme de surcharge de vues Laravel, `vendor/` intact) : la référence de transaction du driver `stripe` devient un lien cliquable vers le paiement dans le dashboard Stripe (`https://dashboard.stripe.com/[test/]payments/{reference}`). La bascule test/live suit le préfixe de `config('services.stripe.key')` (`sk_test_`). Override full-file → surveiller le drift si Lunar met à jour ce blade.

### 4.5 PayPal (phase 2)

`lunarphp/paypal` existe officiellement. À installer quand le besoin est confirmé côté front. **Attention** : il s'enregistre aussi sur le type `card`, ce qui entre en conflit avec Stripe. Options :

- Créer un type dédié `paypal` dans `config/lunar/payments.php`
- Arbitrer entre Stripe et PayPal

---

