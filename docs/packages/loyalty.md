# pko/lunar-loyalty — fidélité B2B

Portage du module PrestaShop `publikoloyalty` (v1.1.0) vers Lunar. Phase 1 : back-office uniquement, storefront différé.

### Décisions
- **Package** : `packages/pko/loyalty`, namespace `Pko\Loyalty\`, ServiceProvider `LoyaltyServiceProvider` (enregistré dans `bootstrap/providers.php`).
- **Plugin Filament** : `LoyaltyPlugin` enregistré dans `AppServiceProvider` après `CatalogFeaturesPlugin`. Resources : `LoyaltyTier` (CRUD), `GiftHistory` (statut + notes + badge nav unviewed), `PointsHistory` (readonly). Page `LoyaltySettings` (ratio + email admin). Group nav : **Marketing**.
- **Trigger calcul points** : observer Eloquent sur `Lunar\Models\Order` (`updated`/`created`), déclenché quand `placed_at` passe à non-null. Choix vs `PaymentAttemptEvent` : robuste pour les paiements offline et idempotent (vérification d'existence dans `pko_loyalty_points_history.order_id` unique).
- **Source HT** : colonne `lunar_orders.sub_total` (entier cents, hors taxes). `points = floor((sub_total/100) / ratio)`.
- **Anti-doublon palier** : index unique `(customer_id, tier_id)` sur `pko_loyalty_gift_history`.
- **Notifications** : `Illuminate\Notifications\Notification` (mail). Client via routing sur `Customer->users()->first()->email`. Admin via `Setting::get('admin_email')` puis fallback `config('loyalty.admin_email')` / env `LOYALTY_ADMIN_EMAIL`.
- **Settings** : table dédiée `pko_loyalty_settings(key, value)` — pas de dépendance `spatie/laravel-settings` ajoutée. Lecture via `Pko\Loyalty\Models\Setting::get()`.

### Tables (préfixe `pko_loyalty_`)
- `pko_loyalty_tiers` — paliers (name, points_required, gift_*, position, active)
- `pko_loyalty_customer_points` — agrégat par client (unique customer_id)
- `pko_loyalty_points_history` — trace par commande (unique order_id → idempotence)
- `pko_loyalty_gift_history` — déblocages (status enum pending/processing/sent, admin_notes, admin_viewed)
- `pko_loyalty_settings` — kv config

### Variables d'env
- `LOYALTY_DEFAULT_RATIO` (défaut `1` — 1€HT = 1 point)
- `LOYALTY_ADMIN_EMAIL` — destinataire des notifications de déblocage côté admin

### Backlog phase 2
- Storefront sections (progress / next gifts / unlocked / history) — data déjà exposée via `LoyaltyManager::getCustomerSnapshot()`.
- Gestion remboursements / annulations (retrait points).
- Commande artisan `loyalty:recalculate` pour rejouer historique clients existants.

---

