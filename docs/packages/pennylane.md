# pko/lunar-pennylane

Package interne d'intégration de la plateforme comptable **Pennylane** (API v2) au back-office Lunar. Émet automatiquement une facture Pennylane à chaque commande passée au statut cible et un avoir proraté à chaque remboursement.

## Pourquoi un wrapper maison

Le seul wrapper Laravel existant (`Ashraam/PennylaneLaravel`) est abandonné depuis 2021, ciblait Laravel 8 et l'API v1 de Pennylane. On s'appuie sur l'API v2 officielle (`https://app.pennylane.com/api/external/v2`) via le HTTP Client natif de Laravel — surface d'appel réduite (< 10 endpoints), meilleure maintenabilité.

## Principes

- **Pennylane est source de vérité de la numérotation** (obligation légale FR : séquence unique, continue, chronologique). Lunar stocke `invoice_number` mais ne le génère jamais.
- **Idempotence** via `external_reference` :
  - facture : `order_<id>`
  - avoir : `refund_<txn_id>`
  - client : `lunar_cust_<id>`
- **Aucune modification de `vendor/`** — uniquement `Order::observe()` + `Transaction::observe()` + un Filament Plugin autonome. Survit aux upgrades Lunar.

## Architecture

```
packages/pko/pennylane/
├── composer.json
├── config/pennylane.php
├── database/migrations/<ts>_create_pko_pennylane_tables.php
├── lang/fr/admin.php
└── src/
    ├── PennylaneServiceProvider.php
    ├── Api/
    │   ├── PennylaneClient.php              # Wrapper HTTP (Bearer, retry 5xx/429)
    │   ├── Resources/
    │   │   ├── CustomerInvoicesResource.php # create, finalize, get, find, changelog
    │   │   └── CustomersResource.php        # create, update, find
    │   └── Exceptions/
    ├── Dto/                                 # CreateInvoiceData, CreateCreditNoteData, InvoiceLineData, CustomerData
    ├── Services/
    │   ├── CustomerMapper.php               # Lunar.Customer → Pennylane.customer_id
    │   ├── OrderToInvoiceMapper.php         # Order+OrderLines → DTO
    │   ├── TransactionToCreditNoteMapper.php
    │   ├── InvoiceSynchronizer.php          # orchestre upsert client + create + finalize
    │   └── CreditNoteSynchronizer.php
    ├── Models/
    │   ├── PennylaneInvoice.php             # table pko_pennylane_invoices
    │   └── PennylaneCustomer.php            # table pko_pennylane_customers
    ├── Observers/
    │   ├── OrderPennylaneObserver.php       # dispatch SyncOrderInvoiceJob sur transition status
    │   └── TransactionPennylaneObserver.php # dispatch SyncRefundCreditNoteJob sur refund créé
    ├── Jobs/
    │   ├── SyncOrderInvoiceJob.php          # ShouldBeUnique, retries 60/300/900/3600/14400s
    │   └── SyncRefundCreditNoteJob.php      # re-queue 120s si facture parent pas prête
    ├── Console/Commands/
    │   ├── PennylaneResyncOrderCommand.php       # pennylane:resync-order {id}
    │   ├── PennylaneBackfillCommand.php          # pennylane:backfill --since=YYYY-MM-DD
    │   └── PennylanePollChangelogCommand.php     # scheduled every 15min
    └── Filament/
        ├── PennylanePlugin.php                   # enregistre cluster + resource
        ├── Clusters/PennylaneCluster.php
        └── Resources/PennylaneInvoiceResource.php (lecture + action resync)
```

## Tables DB

### `pko_pennylane_invoices`

| Colonne | Type | Note |
|---|---|---|
| `id` | PK | |
| `order_id` | FK `lunar_orders` nullable | `nullOnDelete` |
| `transaction_id` | FK `lunar_transactions` nullable | renseigné pour les avoirs |
| `parent_invoice_id` | FK self nullable | facture parent d'un avoir |
| `type` | enum(`invoice`, `credit_note`) | |
| `pennylane_id` | unsignedBigInteger unique nullable | ID Pennylane |
| `pennylane_invoice_number` | string nullable indexé | ex. `F20260001` |
| `external_reference` | string unique | clé d'idempotence |
| `status` | enum(`pending`, `draft`, `finalized`, `failed`) | |
| `last_error` | text nullable | |
| `payload_snapshot` | json | payload envoyé à Pennylane |
| `synced_at` | timestamp nullable | |

### `pko_pennylane_customers`

Mapping 1:1 Lunar Customer ↔ Pennylane customer_id, avec `external_reference` unique.

## Configuration

Variables d'environnement (cf. `config/pennylane.php`) :

```dotenv
PENNYLANE_API_TOKEN=...                 # obligatoire
PENNYLANE_INVOICE_TEMPLATE_ID=42        # ID template facture Pennylane (admin Pennylane → Paramètres)
PENNYLANE_TRIGGER_STATUS=payment-received  # statut Lunar qui déclenche la facture
PENNYLANE_AUTO_CREDIT_NOTE=true         # avoir auto sur refund
PENNYLANE_DEADLINE_DAYS=0               # délai de paiement (facture cash par défaut)
PENNYLANE_LANG=fr
PENNYLANE_QUEUE=default                 # queue dédiée possible
PENNYLANE_SANDBOX=false                 # simple flag logique (sandbox = compte séparé côté Pennylane)
PENNYLANE_HTTP_TIMEOUT=15
PENNYLANE_HTTP_RETRY=3
```

## Flux facture

1. **Déclenchement** : `OrderPennylaneObserver::updated()` détecte `status` changé vers `PENNYLANE_TRIGGER_STATUS`, dispatche `SyncOrderInvoiceJob`.
2. **Job** (retries 60/300/900/3600/14400s, `ShouldBeUnique` 1h) :
   - `PennylaneInvoice::firstOrCreate(external_reference=order_<id>)` — garde-fou doublon
   - Early return si déjà `finalized`
   - `CustomerMapper::resolveOrCreate` → trouve ou crée le client Pennylane (GET par `external_reference` puis POST si absent, stocke mapping local)
   - `OrderToInvoiceMapper::build` → DTO avec lines (cents → décimal, TVA depuis `tax_breakdown`)
   - `CustomerInvoicesResource::create` puis `finalize` (séparation exigée par l'API)
   - Stocke `pennylane_id`, `pennylane_invoice_number`, `status=finalized`, snapshot payload

## Flux avoir (credit note)

1. **Déclenchement** : `TransactionPennylaneObserver::created()` détecte `type=refund` + `success=true`.
2. **Job** (retries 60/180/600/1800/3600/14400s) :
   - Cherche la facture parent `PennylaneInvoice` (type=invoice, status=finalized, même order_id)
   - Absente → `release(120)` (re-queue), jusqu'à épuisement des tries
   - Présente : `TransactionToCreditNoteMapper` proratise le montant refund selon la TVA globale de la commande
   - `CustomerInvoicesResource::create(credit_note=true, parent_invoice_id=...)` puis `finalize`

## Commandes CLI

| Commande | Usage |
|---|---|
| `make artisan CMD='pennylane:resync-order 42'` | Resynchroniser une commande (dispatch job) |
| `make artisan CMD='pennylane:resync-order 42 --sync'` | Synchrone (debug) |
| `make artisan CMD='pennylane:backfill --since=2026-01-01'` | Créer factures pour commandes existantes |
| `make artisan CMD='pennylane:poll-changelog'` | Ingérer changelog Pennylane (scheduled toutes les 15 min) |

## Filament

Cluster **Pennylane** (icône `document-currency-euro`, sort 70) avec une Resource lecture seule `PennylaneInvoiceResource` :
- Colonnes : n° facture, commande, type, statut, date sync
- Filtres : type (facture/avoir), statut
- Action ligne : `Resynchroniser` (dispatch job)
- Pas de création manuelle (`canCreate() => false`)

## Tests

`tests/Unit/Pennylane/` :
- `PennylaneClientTest` : Bearer, throw sur token manquant, parsing erreurs
- `CustomerInvoicesResourceTest` : create, finalize (PUT), findByExternalReference (404 → null)
- `DtoTest` : format currency_amount, stripping des nulls, company vs individual, credit_note

Exécution : `make test`. Tous utilisent `Http::fake()`, pas de vraie base ni de vrai token.

## Résilience aux upgrades Lunar

- Aucun fichier `vendor/` modifié
- Hooks via API publique Lunar : `Order::observe()`, `Transaction::observe()`
- Pas de swap ni d'extension de Resource Lunar (cluster Pennylane complètement indépendant)
- FK en `nullOnDelete` → survit à une purge Lunar
- Filament Plugin isolé, ajouté via `->plugin(PennylanePlugin::make())` dans `AppServiceProvider`

## Bouton de téléchargement sur la page commande

Le bouton natif Lunar `Télécharger le PDF` est **masqué** au profit d'une action Pennylane dédiée, via `OrderInvoiceActionsExtension` (pattern `ResourceExtension` : `headerActions()`). Enregistré dans `AppServiceProvider` sous `LunarPanel::extensions[OrderResource::class]`.

Trois états pour la facture :
- ✅ **Finalisée** → bouton `Télécharger facture F20260001` (couleur primaire) → stream PDF via `pennylane.invoice.pdf` (URL signée, 5 min)
- ⏳ **Pending/draft** → bouton grisé `Facture bientôt disponible`, notification warning au clic
- ❌ **Failed** → bouton rouge `⚠ Facture en échec`, ouvre un modal avec `last_error` + `external_reference` + date; clic principal relance `SyncOrderInvoiceJob`

Avoirs : rendus dans un `ActionGroup` `Avoirs Pennylane (N)` — une entrée par transaction `refund` avec `success=true`. Même logique trois états, même pattern de log/retry.

**Routes** :
- `GET admin/pennylane/invoice/{order}/pdf` → `pennylane.invoice.pdf` (signée)
- `GET admin/pennylane/credit-note/{transaction}/pdf` → `pennylane.credit-note.pdf` (signée)

**Streaming** : `DownloadPennylanePdfController` résout `CustomerInvoicesResource::pdfUrl($id)` (cherche `public_file_url` / `file_url` / `pdf_url` / `download_url` dans la réponse `GET /customer_invoices/{id}`), télécharge le PDF côté serveur, retourne un `streamDownload` avec filename `Facture-F20260001.pdf` / `Avoir-F20260002.pdf`.

## Gotchas

- Les lignes de commande Lunar stockent les montants **en plus petite unité monétaire** (centimes pour EUR). Toute conversion en décimal passe par `$line->unit_price->value / 100`.
- Pennylane **refuse la modification d'une facture finalisée** → un avoir est obligatoire pour corriger. Le package le fait automatiquement via `TransactionPennylaneObserver`.
- Pennylane **assigne le numéro à la finalisation**, jamais à la création du draft. Le flux standard est donc `create(draft=false)` puis `finalize()` — équivalent à une création finalisée atomique.
- La numérotation Pennylane est **partagée entre Lunar et Pennylane manuel**. Aucun conflit possible, c'est un compteur unique côté Pennylane.

## Références

- API v2 : https://pennylane.readme.io/reference/postcustomerinvoices
- Endpoint finalize : https://pennylane.readme.io/reference/finalizecustomerinvoice
- Changelog : https://pennylane.readme.io/reference/getcustomerinvoiceschanges
