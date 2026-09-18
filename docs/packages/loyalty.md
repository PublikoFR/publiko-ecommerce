# pko/lunar-loyalty — fidélité B2B

Portage du module PrestaShop `publikoloyalty` (v1.1.0) vers Lunar. Phase 1 : back-office uniquement, storefront différé.

### Décisions
- **Package** : `packages/pko/loyalty`, namespace `Pko\Loyalty\`, ServiceProvider `LoyaltyServiceProvider` (enregistré dans `bootstrap/providers.php`).
- **Plugin Filament** : `LoyaltyPlugin` enregistré dans `AppServiceProvider` après `CatalogFeaturesPlugin`. Resources : `LoyaltyTier` (CRUD), `GiftHistory` (statut + notes + badge nav unviewed), `PointsHistory` (readonly). Page `LoyaltySettings` (ratio + email admin). Group nav : **Marketing**.
- **Trigger calcul points** : observer Eloquent sur `Lunar\Models\Order` (`updated`/`created`), déclenché quand `placed_at` passe à non-null. Choix vs `PaymentAttemptEvent` : robuste pour les paiements offline et idempotent (vérification d'existence dans `pko_loyalty_points_history.order_id` unique).
- **Source HT** : colonne `lunar_orders.sub_total` (entier cents, hors taxes). `points = floor((sub_total/100) / ratio)`.
- **Anti-doublon palier** : index unique `(customer_id, tier_id)` sur `pko_loyalty_gift_history`.
- **Déblocage multi-paliers** : `unlockEligibleTiers()` débloque **tous** les paliers éligibles (points_required <= solde) sans GiftHistory, pas seulement le plus haut atteint. Sans ça, un palier ajouté après coup sous le solde déjà acquis d'un client (ou un saut de plusieurs paliers en une seule commande) reste invisible pour toujours : ni « à venir » (déjà dépassé), ni « débloqué » (jamais inscrit en base). Commande `php artisan loyalty:recalculate` (`--dry-run` pour compter sans écrire) pour rattraper les clients déjà existants — idempotente, ne crée que les GiftHistory manquantes.
- **Notifications** : client via `TierUnlockedMail` (`TemplatedMail`, clé `loyalty.tier_unlocked`). Équipe via `TierUnlockedAdminMail` (clé `loyalty.tier_unlocked_admin`, audience équipe) envoyé par `AdminRecipient::send()` à `admin_notification_email()` (Storefront → Paramètres). Repli env `LOYALTY_ADMIN_EMAIL`. Le champ e-mail admin n'est plus dans Fidélité.
- **Settings** : table dédiée `pko_loyalty_settings(key, value)` — pas de dépendance `spatie/laravel-settings` ajoutée. Lecture via `Pko\Loyalty\Models\Setting::get()`.
- **Photo du cadeau** : plus de colonne `gift_image_url` (migration `2026_08_31_000000_drop_gift_image_url_from_pko_loyalty_tiers`). `LoyaltyTier` utilise `HasMediaAttachments` (`pko/lunar-media-core`), média rattaché au mediagroup `gift_image` via `MediaPicker` dans `LoyaltyTierResource`. Accesseur `getGiftImageUrlAttribute()` conservé (mappe sur `firstMediaUrl('gift_image')`) pour ne pas casser les usages existants (notifications, vue storefront).
- **Ordre des paliers** : champ `position` retiré du formulaire admin. Le glisser-déposer (`->reorderable('position', false)`) est désactivé côté UI mais **pas supprimé du code** (économie de compute, réactivable en repassant `true`) — la liste est triée par `points_required` croissant (`->defaultSort('points_required')`), colonne déjà `->sortable()`.

- **Cycle annuel (remise à zéro au 1er janvier)** : `customer_points.points_year` rattache le solde à une année civile. Un solde d'une année passée vaut 0. Double mécanisme : `LoyaltyManager::ensureCurrentYear()` remet à zéro à la volée avant toute écriture (gain ou retrait), et la commande `loyalty:reset-annual` (planifiée **chaque jour** à 00:05, pas `yearlyOn(1, 1)` : rattrape un scheduler arrêté le 1er janvier, no-op le reste de l'année) remet à zéro en base pour que l'admin affiche aussi 0. `getCustomerSnapshot()` renvoie 0 pour un solde d'année passée même si la commande n'est pas encore passée.
- **Paliers re-débloquables chaque année** : `gift_history.year`, unicité `(customer_id, tier_id, year)` (avant : `(customer_id, tier_id)`). Le snapshot expose `unlocked_tiers` = année en cours (piste Flow) et `gift_history` = tout l'historique (liste « Suivi de vos cadeaux »).
- **Retrait de points sur remboursement / avoir** : observer `RefundTransactionObserver` sur `Lunar\Models\Transaction` (`saved`, `type=refund`, `success=true`). Les avoirs Pennylane naissent de ces mêmes transactions (`TransactionPennylaneObserver`) → un seul point d'entrée. `revokeForRefunds()` retire **au prorata** : `cible = round(points_earned × Σ remboursé TTC / total TTC)`, plafonnée à `points_earned`, stockée dans `points_history.points_revoked` ; seul l'écart avec la cible précédente est retiré (idempotent, remboursements partiels successifs gérés). Solde plancher 0, `current_tier_id` recalculé. Si le solde repasse sous un palier débloqué **cette année**, le cadeau encore `pending` est supprimé ; `processing`/`sent` conservé. Remboursement d'une commande dont les points ont été gagnés une année passée → ignoré (points déjà remis à zéro). Pas de statut Lunar « annulée » configuré : une annulation passe par un remboursement.

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
- Sections affichées : solde de points + **piste « Flow »** (coverflow façon iPod) qui fusionne cadeaux débloqués + cadeau en cours + cadeaux à venir en une seule frise : débloqués (couleur pleine, badge check lime, ordre chronologique) à gauche, cadeau en cours au centre (anneau + halo + badge + mini barre de progression `accent-500`), cadeaux à venir verrouillés (badge `lock`, image conservée en couleur) à droite. Puis liste compacte « Suivi de vos cadeaux débloqués » (statut + date) et historique des points (`points_history`).
- **Piste Flow — implémentation** : composant Alpine `loyaltyFlow` (`resources/js/loyalty-flow.js`, exposé en fabrique globale `window.loyaltyFlow` — même pattern que `pickupMap`, cf. le commentaire du fichier pour la course évitée avec `alpine:init`). Le positionnement n'est **pas** dérivé du scroll natif du navigateur : chaque carte est en `position:absolute`, et un offset virtuel continu (`active`, piloté par molette/drag/tactile) détermine en direct `translateX`/`rotateY`/`opacity` de chaque carte via `cardStyle(index)`. Ce choix permet de faire défiler/pivoter même quand le contenu ne déborde pas nativement, un mouvement continu pendant le drag (pas de saut entre états figés), et une animation de snap (`transition-[transform,opacity]`) qui remet la carte centrée à plat au relâchement. Toutes les cartes latérales partagent la **même taille et le même border-radius** (`rounded-lg`) — seule la rotation varie, jamais l'échelle (sinon effet de « pop » brutal au passage au centre). La bascule est linéaire sur tout l'écart entre deux cartes (`d * -58`, plafonné à ±58°) : l'angle max n'est atteint qu'au moment exact où la carte suivante prend le relais au centre — une rampe qui sature trop tôt fige visuellement la rotation avant la fin du trajet.
- Clés du snapshot : `total_points`, `prev_points` (dernier palier atteint, base de la piste), `next_tier` (LoyaltyTier|null), `upcoming_tiers` (**tous** les prochains LoyaltyTier, triés par points_required), `progress_percent`, `points_to_next`, `unlocked_tiers` (GiftHistory[]), `points_history` (PointsHistory[]), `all_tiers_unlocked`, `no_tiers_configured`.
- Icônes `gift`, `lock`, `trophy` ajoutées au set DS partagé `storefront::components.ui.icon`.
- **Couleur du connecteur de progression** : lime (`accent-500`, vert clair de la charte), jamais `primary`/forest — cf. §3.3 règle 4 (accent lime réservé à un seul élément fort par vue, ici le cadeau en cours de déblocage).

### Backlog phase 2
- Remboursements / annulations : fait (cf. « Retrait de points sur remboursement »).

---

