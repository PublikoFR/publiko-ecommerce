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
- **Photo du cadeau** : plus de colonne `gift_image_url` (migration `2026_08_31_000000_drop_gift_image_url_from_pko_loyalty_tiers`). `LoyaltyTier` utilise `HasMediaAttachments` (`pko/lunar-media-core`), média rattaché au mediagroup `gift_image` via `MediaPicker` dans `LoyaltyTierResource`. Accesseur `getGiftImageUrlAttribute()` conservé (mappe sur `firstMediaUrl('gift_image')`) pour ne pas casser les usages existants (notifications, vue storefront).
- **Ordre des paliers** : champ `position` retiré du formulaire admin — réordonnancement uniquement par glisser-déposer dans la liste (`->reorderable('position')` + `->defaultSort('position')`, pattern identique à `HomeTileResource`).

### Tables (préfixe `pko_loyalty_`)
- `pko_loyalty_tiers` — paliers (name, points_required, gift_title, gift_description, position, active ; photo via `pko_mediables`)
- `pko_loyalty_customer_points` — agrégat par client (unique customer_id)
- `pko_loyalty_points_history` — trace par commande (unique order_id → idempotence)
- `pko_loyalty_gift_history` — déblocages (status enum pending/processing/sent, admin_notes, admin_viewed)
- `pko_loyalty_settings` — kv config

### Variables d'env
- `LOYALTY_DEFAULT_RATIO` (défaut `1` — 1€HT = 1 point)
- `LOYALTY_ADMIN_EMAIL` — destinataire des notifications de déblocage côté admin

### Storefront — page fidélité client (`account.loyalty`)
- Livewire `Pko\Account\Livewire\LoyaltyPage` (package `account`), vue `account::livewire.loyalty-page`.
- Données via `LoyaltyManager::getCustomerSnapshot(int $customerId)` — signature **int**, passer `$customer->id` (pas l'objet `Customer`, sinon `TypeError` sous `strict_types` → page vide).
- Sections affichées : solde de points + **stepper horizontal** (Vous → cadeau qui arrive au milieu → cadeau suivant à droite ; connecteur 1 rempli au prorata `prev_points → next_tier.points_required`), puis un **carousel horizontal** listant la **totalité** des cadeaux à venir (`upcoming_tiers`, non limité — scroll-snap CSS, pas de JS). Fonctionne même sans commande (calculé sur les paliers actifs). Puis cadeaux débloqués (`unlocked_tiers`) et historique des points (`points_history`).
- Clés du snapshot : `total_points`, `prev_points` (dernier palier atteint, base du wizard), `next_tier` (LoyaltyTier|null), `upcoming_tiers` (**tous** les prochains LoyaltyTier, triés par points_required), `progress_percent`, `points_to_next`, `unlocked_tiers` (GiftHistory[]), `points_history` (PointsHistory[]), `all_tiers_unlocked`, `no_tiers_configured`.
- Icône `gift` ajoutée au set DS partagé `storefront::components.ui.icon`.

### Backlog phase 2
- Gestion remboursements / annulations (retrait points).
- Commande artisan `loyalty:recalculate` pour rejouer historique clients existants.

---

