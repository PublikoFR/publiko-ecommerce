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
    │   │   ├── CustomerInvoicesResource.php # create, finalize, get, find, link_credit_note, delete, changelog
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
PENNYLANE_ENABLED=true                  # kill-switch global : à false, aucune requête n'est envoyée
PENNYLANE_API_TOKEN=...                 # obligatoire
PENNYLANE_INVOICE_TEMPLATE_ID=         # optionnel : sans ID, Pennylane applique son modèle par défaut
PENNYLANE_TRIGGER_STATUS=payment-received  # statut Lunar qui déclenche la facture
PENNYLANE_AUTO_CREDIT_NOTE=true         # avoir auto sur refund
PENNYLANE_DEADLINE_DAYS=0               # délai de paiement (facture cash par défaut)
PENNYLANE_LANG=fr                       # converti en locale API (fr → fr_FR)
PENNYLANE_QUEUE=default                 # queue dédiée possible
PENNYLANE_SANDBOX=false                 # sans effet : c'est le token qui désigne live ou sandbox (voir « Tester sur la sandbox »)
PENNYLANE_HTTP_TIMEOUT=15
PENNYLANE_HTTP_RETRY=3
```

### Kill-switch `PENNYLANE_ENABLED`

**À mettre à `false` sur tout environnement non-production** (dev, staging, local
avec un token réel). Le seul garde-fou historique était la présence du token :
dès qu'un token de production traînait dans le `.env` de dev, chaque commande de
test partait en facture réelle dans la comptabilité.

`PennylaneClient::isEnabled()` court-circuite `isConfigured()`, donc le flag
coupe d'un coup les deux observers (commande + transaction), le job de synchro,
les commandes artisan et le polling changelog schedulé. Un appel direct au client
lève `PennylaneNotConfiguredException::disabled()`. La page admin **Pennylane**
affiche un bandeau d'avertissement et désactive le bouton « Tester la connexion ».

### Format des filtres API (v2)

L'API v2 attend le paramètre `filter` comme **chaîne JSON**, pas comme tableau.
`PennylaneClient::normalizeQuery()` fait le `json_encode` avant tout GET. Sans ça
l'API renvoie `400 The filter's value (...) should be a string, but we received a
hash` — le sérialiseur de `Http::get()` produisant `filter[0][field]=...`.

### Contrat API v2 — pièges vérifiés sur la sandbox (2026-09-18)

Le package a d'abord été écrit sans appel réel. Confronté à la spec
(`https://pennylane.readme.io/openapi/accounting.json`) et à la sandbox, il
divergeait sur tous les points ci-dessous. La spec OpenAPI fait foi.

| Sujet | Contrat v2 |
|---|---|
| Création client | `POST /company_customers` ou `/individual_customers` selon le type (`POST /customers` → 404). Lecture/recherche : `GET /customers`. |
| Référence client | `external_reference` (pas `source_id`), filtrable en `eq`. |
| Adresse de facturation | Obligatoire, avec `address`, `postal_code`, `city`, `country_alpha2` tous renseignés. `CustomerData` lève une `PennylaneException` lisible si un champ manque. |
| Lignes | `invoice_lines` (pas `line_items`). Prix unitaire **HT** dans `raw_currency_unit_price` (string, 6 décimales max, remise déduite). |
| TVA | `vat_rate` est un **code** (`FR_200`, `FR_100`, `FR_55`, `FR_21`, `exempt`), pas un pourcentage. Voir `Support\VatRate`. |
| Langue | Locale complète (`fr_FR`). |
| Finalisation | Lue sur le booléen `draft`. `status` décrit le **paiement** (`upcoming`, `paid`, `late`…) : la valeur `finalized` n'existe pas. |
| Avoir | Facture à **montants négatifs** créée par le même endpoint, puis `POST /customer_invoices/{facture}/link_credit_note` une fois finalisée. Pas de champ `credit_note` ni `parent_invoice_id` à la création. |
| Modèle de facture | `customer_invoice_template_id` est optionnel. Le lister demande le scope `customer_invoice_templates:readonly`. |
| Chronologie | Une facture ne peut pas être finalisée avec une date antérieure à la dernière facture finalisée (422). La facture est donc datée **du jour de la synchro**, pas de la commande ; la référence de commande figure dans l'objet du PDF. Sinon, tout backfill et tout job rejoué après une commande plus récente échoueraient. |
| Numérotation | La finalisation échoue en 422 (« Configurez d'abord la numérotation des factures ») tant que la numérotation n'est pas configurée dans le compte Pennylane, sandbox compris. |

**Taux de TVA** : `tax_breakdown` est vide sur nos commandes Lunar. Le taux est
donc déduit de `tax_total / (sub_total - discount_total)` puis rapproché du taux
légal français le plus proche (tolérance 0,15 point, pour absorber l'arrondi au
centime). Un taux sans équivalent fait échouer la synchro plutôt que d'émettre une
facture fausse.

**TVA à 0 % sur toutes les commandes** : ce n'est pas un bug Pennylane. `lunar:install`
crée une « Default Tax Zone » avec tous les pays et sans taux, et Lunar retient la
*première* zone active contenant le pays. Tant que la France y figure, la zone
« France métropolitaine » est ignorée. `PkoTaxSeeder` retire désormais la France des
autres zones. Sur une base existante, relancer `make artisan CMD='db:seed --class=PkoTaxSeeder'`.

**Port** : quand une commande porte `shipping_total` sans ligne `shipping` (anciennes
commandes), une ligne « Frais de port » est ajoutée. Sa TVA est celle qui n'est
imputée à aucune ligne produit.

**Contrôle du total** : après création, `InvoiceSynchronizer` compare le TTC recalculé
par Pennylane (`currency_amount`) au total de la commande et logue un warning
au-delà d'un centime d'écart (remise de commande non répartie sur les lignes, par exemple).

### Tester sur la sandbox

Un compte Pennylane active un « Environnement de test » depuis son profil (cela
demande un abonnement). On obtient alors un second compte, isolé du live. La clé
API générée depuis ce compte s'utilise telle quelle : l'URL de l'API est la même,
c'est le token qui fixe l'environnement. `GET /me` renvoie un `reg_no` de la forme
`sandbox-<id>`, à vérifier avant toute écriture. En dev : enregistrer la clé dans
Secrets (source `db`), puis `pennylane:resync-order <id> --sync`.

### Queue

Avec `QUEUE_CONNECTION=sync`, `SyncOrderInvoiceJob` s'exécute **dans la requête de
checkout** : toute erreur Pennylane fait échouer la commande client. Prévoir une
queue asynchrone (`database` + worker) sur les environnements où l'intégration est
active. `OrderPennylaneObserver` catch en dernier recours pour ne jamais casser le
tunnel d'achat.

## Flux facture

1. **Déclenchement** : `OrderPennylaneObserver::updated()` détecte `status` changé vers `PENNYLANE_TRIGGER_STATUS`, dispatche `SyncOrderInvoiceJob`.
2. **Job** (retries 60/300/900/3600/14400s, `ShouldBeUnique` 1h) :
   - `PennylaneInvoice::firstOrCreate(external_reference=order_<id>)` — garde-fou doublon
   - Early return si déjà `finalized`
   - `CustomerMapper::resolveOrCreate` → trouve ou crée le client Pennylane (GET par `external_reference` puis POST si absent, stocke mapping local)
   - `OrderToInvoiceMapper::build` → DTO avec lignes HT remisées, TVA déduite des montants, port ajouté si besoin
   - Reprise : facture retrouvée par `external_reference`, sinon `create(draft=false)` ; `finalize` seulement si elle est encore en brouillon
   - Stocke `pennylane_id`, `pennylane_invoice_number`, `status=finalized`, snapshot payload

## Flux avoir (credit note)

1. **Déclenchement** : `TransactionPennylaneObserver::created()` détecte `type=refund` + `success=true`.
2. **Job** (retries 60/180/600/1800/3600/14400s) :
   - Cherche la facture parent `PennylaneInvoice` (type=invoice, status=finalized, même order_id)
   - Absente → `release(120)` (re-queue), jusqu'à épuisement des tries
   - Présente : `TransactionToCreditNoteMapper` répartit le remboursement TTC au prorata du TTC de chaque taux de TVA de la commande (une ligne négative par taux)
   - `create` (montants négatifs), `finalize` si besoin, puis `linkCreditNote` sur la facture parent (sauté si `credited_invoice` est déjà renseigné)

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
- `CustomerInvoicesResourceTest` : create, finalize (PUT), findByExternalReference (404 → null), `isFinalized` sur `draft`, link_credit_note
- `CustomersResourceTest` : endpoint de création par type, filtre `external_reference`
- `DtoTest` : `raw_currency_unit_price` + code TVA, précision sous le centime, adresse obligatoire, avoir sans champs v1
- `VatRateTest` : correspondance taux → code, arrondis, taux inconnu rejeté

Exécution : `make test-only T=tests/Unit/Pennylane`. Tous utilisent `Http::fake()`, pas de vraie base ni de vrai token.

## Résilience aux upgrades Lunar

- Aucun fichier `vendor/` modifié
- Hooks via API publique Lunar : `Order::observe()`, `Transaction::observe()`
- Pas de swap ni d'extension de Resource Lunar (cluster Pennylane complètement indépendant)
- FK en `nullOnDelete` → survit à une purge Lunar
- Filament Plugin isolé, ajouté via `->plugin(PennylanePlugin::make())` dans `AppServiceProvider`

## Liens dans la fiche commande (colonne latérale)

`Services\OrderDocuments::forOrder($order)` renvoie la facture et les avoirs de la
commande (libellé, état `ready`/`pending`/`failed`, lien signé valable 12 h). Il est
mis en cache le temps de la requête (`once()`), car la fiche l'appelle plusieurs fois.
Il alimente deux emplacements, gérés par `app/Filament/Extensions/OrderPageLayoutExtension.php` :

- bloc **vue d'ensemble**, sous le client : ligne « Facture F-… » + boutons Voir et PDF ;
- bloc **Transactions** : rappel de la facture et de chaque avoir, avant l'adresse de facturation.

Les liens pointent vers les routes admin (proxy), jamais vers `public_file_url`.

**Garde d'authentification** : les routes `admin/pennylane/*` exigent `auth:staff`,
c'est-à-dire le garde du back-office Lunar. Le middleware `auth` seul vérifie le garde
par défaut `web`, celui des clients du site. Avec lui, un admin connecté uniquement au
back-office était renvoyé vers la connexion, et un client connecté pouvait ouvrir un
lien signé qui aurait fuité.

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

**Streaming** : `DownloadPennylanePdfController` utilise désormais `InvoicePdfFetcher`, qui résout `CustomerInvoicesResource::pdfUrl($id)` (lit `public_file_url`, URL valable 30 min, dans la réponse `GET /customer_invoices/{id}`), télécharge le PDF côté serveur, retourne le PDF avec filename `Facture-F20260001.pdf` / `Avoir-F20260002.pdf`.

**Affichage dans le navigateur** : `?inline=1` sur les trois routes PDF (admin et client) renvoie `Content-Disposition: inline` au lieu de `attachment` ; les boutons « Voir » ouvrent ce lien dans un nouvel onglet. Le paramètre ne touche à aucun contrôle d'accès. Côté admin, il est **inclus dans l'URL signée** (`OrderDocuments::url(..., inline: true)`) : l'ajouter à un lien de téléchargement signé invalide la signature (403). Le PDF est servi en mémoire (`response()` et non `streamDownload`), avec `private, no-store`, `nosniff` et `Referrer-Policy: no-referrer`.

## Gotchas

- Les lignes de commande Lunar stockent les montants **en plus petite unité monétaire** (centimes pour EUR). Toute conversion en décimal passe par `$line->unit_price->value / 100`.
- Pennylane **refuse la modification d'une facture finalisée** → un avoir est obligatoire pour corriger. Le package le fait automatiquement via `TransactionPennylaneObserver`.
- Pennylane **assigne le numéro à la finalisation**, jamais à la création du draft. `create(draft=false)` crée directement une facture finalisée ; `finalize()` ne sert qu'à reprendre un brouillon.
- La numérotation Pennylane est **partagée entre Lunar et Pennylane manuel**. Aucun conflit possible, c'est un compteur unique côté Pennylane.

## Références

- API v2 : https://pennylane.readme.io/reference/postcustomerinvoices
- Endpoint finalize : https://pennylane.readme.io/reference/finalizecustomerinvoice
- Changelog : https://pennylane.readme.io/reference/getcustomerinvoiceschanges


## Espace client et e-mails de documents (septembre 2026)

### PDF original, sans copie locale

`InvoicePdfFetcher::fetch()` récupère exclusivement le PDF émis par Pennylane,
via `CustomerInvoicesResource::pdfUrl()`. Le contenu reste en mémoire pendant
le téléchargement ou l'envoi SMTP : aucun fichier PDF, URL publique ou binaire
n'est persisté sur disque, en base ou dans la file. La signature `%PDF-` est
contrôlée avant de servir le contenu. Une réponse 409 (API ou fichier) ou une URL
vide lève `InvoicePdfNotReady`; les autres erreurs restent réessayables, avec un
message qui ne divulgue pas l'URL publique.

`public_file_url` est accessible sans authentification pendant environ 30 minutes.
Elle ne doit donc jamais apparaître dans une vue, une redirection ou un e-mail.
Le contrôleur sert le binaire avec `private, no-store` et `nosniff`. Si le PDF est
encore indisponible, il répond 503 avec `Retry-After: 60`.

### Liste et téléchargement client

- `GET /compte/factures` liste les factures **et avoirs finalisés**, paginés par 10,
  sans appel réseau. La date vient du snapshot de création (repli sur la date de
  création locale), le TTC de la commande ou du remboursement Lunar, affiché
  négativement pour les avoirs. Ce montant local est indicatif en cas d'écart
  comptable : le PDF reste la référence.
- `GET /compte/factures/{invoice}/pdf` (`pennylane.customer.pdf`) traverse les mêmes
  middlewares `web`, `auth`, `pro.customer` que le compte. La requête exige un
  document finalisé avec ID Pennylane et une commande dont `customer_id` est celui
  de `AccountContext::customer()`. Document étranger, supprimé ou non finalisé :
  404, avant tout appel réseau. Invité : redirection vers la connexion.
- `pko/lunar-account` expose le contrat `CustomerInvoices` et une implémentation
  vide par défaut. `pko/lunar-pennylane` fournit `CustomerInvoiceListing` et la route
  de téléchargement. Les lignes transmises à la vue contiennent seulement les
  données d'affichage et les URL internes, jamais un modèle comptable sérialisé.
  La dépendance reste **Pennylane → Account**, sans cycle.
- Les routes admin signées sont conservées. Le contrôleur accepte les modèles
  `Order` / `Transaction` fournis par le route model binding Laravel ; attendre un
  entier provoquait une erreur 500 lorsque le binding de commande s'appliquait.

### Envoi asynchrone et reprise

`InvoiceEmailObserver` réagit à la création finalisée ou à la **transition** vers
`finalized`, y compris via le polling. Il met `InvoiceFinalizedMail` en file après
commit. Une mise à jour sans transition et l'early return des synchroniseurs ne
redéclenchent rien. Une panne de mise en file est journalisée sans déclasser le
document finalisé ; utiliser la commande de reprise ci-dessous.

La Mailable étend `TemplatedMail`, implémente `ShouldQueue` et transporte uniquement
l'identifiant local et les données de template, jamais le PDF. Au traitement :

1. Relire le document et le template ; abandonner si déjà envoyé, non finalisé,
   sans commande, ou si le template a été désactivé.
2. Acquérir **par UPDATE conditionnel avant réseau** une réservation
   (`email_claimed_at`, `email_claim_token`), uniquement si `emailed_at` est vide.
   Un second worker ne peut envoyer simultanément. Le bail dure 10 minutes,
   au-delà du timeout du job (120 secondes) ; un worker tué est récupérable.
3. Résoudre le destinataire : `billingAddress.contact_email`, puis `order.user.email`,
   puis le premier utilisateur du customer. Une adresse vide utilise le repli ;
   une adresse invalide fait échouer l'envoi plutôt que de choisir un autre contact.
4. Récupérer le PDF et l'attacher (`Facture-<numéro>.pdf` ou `Avoir-<numéro>.pdf`),
   envoyer puis renseigner `emailed_at` seulement après acceptation par le transport.
5. Libérer la réservation en vérifiant son token. Sur erreur PDF/SMTP, conserver
   `emailed_at = null` et laisser le worker réessayer : **6 tentatives**, backoff
   **60, 180, 600, 1800, 3600 secondes**.

Limite SMTP explicite : il n'existe pas de transaction atomique SMTP + MySQL.
Un arrêt brutal après acceptation SMTP mais avant l'écriture `emailed_at` peut
entraîner un doublon à la reprise. Les répétitions normales, jobs dupliqués et
workers concurrents sont protégés ; `emailed_at` atteste l'acceptation du transport,
pas la livraison finale en boîte de réception.

Commande de reprise (également utilisable pour les documents historiques) :

```bash
make artisan CMD='pennylane:email-invoice <id-local>'
```

Elle ne recrée aucune facture et refuse de renvoyer un document déjà envoyé.
Les anciens documents ne sont pas envoyés automatiquement par la migration.
Après épuisement des retries, vérifier la file d'échecs et utiliser cette commande
après correction. Ne pas effacer `emailed_at` pour contourner l'idempotence.

### Templates et déploiement

La dépendance `pko/lunar-mail-templates` est déclarée dans Pennylane. Deux modèles
éditables sont ajoutés au registre et aux contenus par défaut :
`billing.invoice_finalized` et `billing.credit_note_finalized`. Deux clés permettent
une rédaction et une activation indépendantes, avec une seule Mailable pour
partager l'envoi et la sécurité. L'ancien modèle SAV `credit_note.available`
reste distinct. La marque est fournie par `brand_name()`.

Appliquer la migration du package puis synchroniser sans écraser les textes :

```bash
make artisan CMD='pko:mail-templates:sync --key=billing.invoice_finalized --key=billing.credit_note_finalized'
```

Le site envoie lui-même le message. L'endpoint Pennylane `send_by_email` n'est
**jamais appelé**, pour préserver la transmission future via une plateforme agréée
pour la facturation électronique B2B. Ce flux ajoute une notification client, pas
une nouvelle émission comptable.

Tests ciblés : `make test-only T=tests/Feature/Pennylane/CustomerInvoicesTest.php`
et `make test-only T=tests/Unit/Pennylane`. Le test d'envoi utilise le transport
mémoire (en complément de `Mail::fake()` pour le dispatch) pour contrôler réellement
le MIME, la pièce jointe, le destinataire et le marquage après succès. Tout HTTP y
est simulé et les requêtes imprévues sont interdites.


### Recette réelle du 18 septembre 2026 (dev / sandbox)

- Route client traversée avec l'utilisateur du customer de la commande 18 :
  HTTP 200, `Facture-F-2026-09-1.pdf`, 33 976 octets, signature PDF valide.
- Commande de reprise pour les documents locaux 2 et 3 ; traitement par la file
  Redis et le worker scheduler existant, réception confirmée par l'API Mailpit.
- Facture : pièce jointe de 33 976 octets, SHA-256
  `7b181f5848820136e38687504eec8ccdef0a3555ec046615e23336888ef2e952`, identique
  à celle du téléchargement client.
- Avoir F-2026-09-2 : pièce jointe de 33 083 octets, SHA-256
  `bd0e0adebff27f6e19bbba84691da1d0bb5eb9fbe579fbf05a4d2bad68f01485`.
- Les deux `emailed_at` sont renseignés, les réservations libérées, aucune URL
  publique dans le corps des messages. Aucun PDF n'a été écrit sur disque.
