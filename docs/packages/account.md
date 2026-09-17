# Package pko/account — Espace compte client (storefront)

Package : `packages/pko/account/`  
ServiceProvider : `Pko\Account\AccountServiceProvider`

## Rôle

Espace personnel du client B2B côté storefront. Expose les pages Livewire suivantes :

| Route name | Composant | Description |
|---|---|---|
| `account.dashboard` | `Dashboard` | Vue d'ensemble du compte |
| `account.profile` | `ProfilePage` | Infos personnelles + mot de passe |
| `account.company` | `CompanyPage` | Informations entreprise |
| `account.addresses` | `AddressesPage` | Carnet d'adresses |
| `account.orders` | `OrdersPage` | Liste des commandes |
| `account.order.view` | `OrderDetailPage` | Détail d'une commande |
| `account.loyalty` | `LoyaltyPage` | Programme de fidélité |
| `account.invoices` | `InvoicesPage` | Factures (Pennylane) |

## Sécurité

Toutes les routes sont protégées par le middleware `pko.pro_access`.  
`AccountContext::customer()` → `auth()->user()->customers()->first()` via la table pivot `lunar_customer_user`.

### Règle obligatoire sur les méthodes d'écriture Livewire

**`mount()` seul ne suffit pas à sécuriser les écritures.**  
Les Livewire requests suivantes (updates, actions) ne re-passent pas par `mount()`.  
Tout `save*()` ou `update*()` qui modifie une donnée liée au client **doit** re-vérifier `customer_id === order->customer_id` dans la méthode elle-même avec `abort_unless(..., 403)`.

Exemple : `OrderDetailPage::saveSiteName()` re-garde l'appartenance même si `mount()` l'a déjà vérifiée.

## Champs éditables sur la commande

### Nom du chantier (`pko_site_name`)

- Affiché et modifiable dans `OrderDetailPage` via le composant Livewire inline.
- Stocké dans `lunar_orders.pko_site_name` (nullable string 255).
- Saisi au checkout (CheckoutPage) et propagé par pipeline.
- Validation : max 255, strip_tags côté serveur.

Voir `docs/admin.md` § "Nom du chantier" pour la chaîne complète cart → order.

## Libellés de statut de commande

Les slugs Lunar (`payment-received`, `awaiting-quote`…) restent la valeur stockée et comparée. L'affichage (espace client, e-mails, checkout) passe par le helper `order_status_label()` (`Pko\Account\Support\OrderStatusLabel`), qui lit `config('lunar.orders.statuses.<slug>.label')` et retombe sur le slug si aucun libellé n'existe.

Helpers globaux (autoload `packages/pko/account/src/helpers.php`) :

| Helper | Usage |
|---|---|
| `order_status_label(?string $status)` | Statut de commande |
| `payment_driver_label(?string $driver)` | Driver de transaction (`stripe` → « Paiement en ligne ») |
| `payment_status_label(?string $status)` | Statut de transaction |

Ne pas dupliquer les libellés de statut : toute nouvelle valeur va dans `config/lunar/orders.php`.
