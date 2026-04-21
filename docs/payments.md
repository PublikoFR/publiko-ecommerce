# Paiements — Stripe

### 4.1 Choix : Lunar Payments natif + addon Stripe officiel

**Décision** : le système de paiement Lunar est driver-based. Chaque type de paiement (`cash-in-hand`, `card`…) est défini dans `config/lunar/payments.php` et mappe vers un driver enregistré via `Payments::extend('<driver>', ...)`.

**Drivers installés** :

| Type Lunar | Driver | Package | Webhook |
|---|---|---|---|
| `cash-in-hand` | `offline` | core | — |
| `card` | `stripe` | `lunarphp/stripe` | `POST /stripe/webhook` |

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

### 4.4 PayPal (phase 2)

`lunarphp/paypal` existe officiellement. À installer quand le besoin est confirmé côté front. **Attention** : il s'enregistre aussi sur le type `card`, ce qui entre en conflit avec Stripe. Options :

- Créer un type dédié `paypal` dans `config/lunar/payments.php`
- Arbitrer entre Stripe et PayPal

---

