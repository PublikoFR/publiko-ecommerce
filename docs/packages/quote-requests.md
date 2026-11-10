# pko/lunar-quote-requests — formulaire de devis unique

Un seul parcours pour tous les CTA « Demander un devis », « Devis pour un projet »
et « Identification d'une pièce ». Page publique Livewire `/demande-de-devis`
(route `quote-request`), demandes persistées et suivies en back-office
(Ventes & Clients → Demandes de devis). Aucun prix n'est affiché dans ce parcours.

## Modes

`Pko\QuoteRequests\Enums\QuoteRequestMode` — un formulaire, deux jeux de textes :

| Mode | Valeur | Spécificités |
|---|---|---|
| Demande de devis | `general` (défaut) | produits, références, chantier ou projet : description obligatoire, pièces jointes facultatives |
| Identification de pièce | `part_photo` | **au moins une photo** (JPG/PNG/WebP) obligatoire |

Décision (10/11/2026, demande client) : l'ancien mode « projet » n'apportait
qu'un texte différent et un téléphone obligatoire, il est **fusionné** dans la
demande générale. `?mode=project` (anciens liens, réglages en base) retombe sur
`general` ; la migration `2026_11_10_120000_…` a passé les demandes existantes
en `general`. Les **lignes produit / quantité** ont aussi été retirées du
formulaire (jugées inutiles : la description et les pièces jointes suffisent) ;
la description est donc toujours obligatoire. La colonne `lines` reste pour les
anciennes demandes (affichée dans la fiche admin si non vide) ; le bloc
« Produits / quantités » a été retiré du mail équipe par défaut et du mail stocké
s'il n'avait pas été retouché.

Le mode est synchronisé dans l'URL (`#[Url]`). Changer de mode (`setMode()`)
ne touche à aucune donnée saisie. Mode inconnu → `general`.

## Contexte d'origine et URL

Query string : `mode`, `product` (id de variante **ou** slug produit), `ref`,
`collection` (slug), `from` (URL d'origine). Tout est re-résolu côté serveur par
`Support\QuoteContext::fromQuery()` puis stocké dans une propriété `#[Locked]` :
le navigateur ne peut pas réécrire le produit rattaché.

- Variante trouvée et `ref` absent → la référence prend le SKU de la variante.
- `from` : chemin interne uniquement (`/…`, pas `//hote`), ou URL absolue du
  même hôte ramenée à son chemin. Tout le reste est écarté (pas de lien piégé
  dans l'e-mail équipe ni de redirection ouverte).
- Le produit d'origine pré-remplit la première ligne (« REF — désignation »).

Générer un lien (réutilisé par le bouton fiche produit, task B) :

```php
quote_request_url('part_photo');
quote_request_url('general', ['product' => $variant, 'ref' => $variant->sku, 'from' => request()->getRequestUri()]);
```

`product` accepte une `ProductVariant` (→ id), un `Product` (→ slug), un id ou un slug.
Dans un fichier de config (chargé avant les helpers), écrire l'URL en dur :
`/demande-de-devis?mode=part_photo`.

## Données

| Table | Contenu |
|---|---|
| `pko_quote_requests` | mode, statut (`new` / `in_progress` / `done`), coordonnées copiées, message, `lines` (JSON `{label, quantity}`), `product_id`, `product_variant_id`, `reference`, `collection`, `from_url`, `context` (JSON : nom produit, URL produit, nom catégorie figés), `customer_id`, `user_id`, `ip`, `admin_notes` |
| `pko_quote_request_files` | pièces jointes : `disk`, `path`, `original_name`, `mime_type` **détecté**, `size` |

Les coordonnées sont **copiées** même si le client est connecté : la demande
reste lisible si le compte change ou est anonymisé. Numéro affiché :
`DEV-000042` (dérivé de l'id, jamais d'un COUNT).

## Pièces jointes

- Formats : JPG, PNG, WebP, PDF, DWG, CSV, XLSX.
- **Type validé par signature binaire** (`Support\FileSignature`), jamais par
  l'extension ni le MIME du navigateur. La signature doit aussi concorder avec
  l'extension (un PDF renommé `.xlsx` est refusé). XLSX = ZIP contenant
  `[Content_Types].xml` + `xl/workbook.xml` ; CSV = texte sans caractère de
  contrôle.
- Contrôle à l'ajout (fichier par fichier, les refusés sont écartés avec un
  message) **et** à l'envoi.
- Stockage sur disque **privé** (`local` = `storage/app/private`), dossier
  `quote-requests/<id>/`, nom aléatoire. Jamais servi publiquement.
- Téléchargement : route `quote-requests.files.download`
  (`/admin/quote-requests/files/{file}/download`, middleware `auth:staff`),
  toujours en `attachment` + `nosniff`. Autorisation = même règle que la fiche
  admin (cf. « Policies » plus bas).
- Aperçu inline des photos : route `quote-requests.files.preview`
  (`/admin/quote-requests/files/{file}/preview`), mêmes droits que le
  téléchargement, réservée aux formats raster **détectés** (jpg, png, webp —
  jamais DWG, PDF ni SVG), avec `nosniff` + `Content-Security-Policy: default-src
  'none'; sandbox`. Sert les miniatures et la lightbox de la fiche admin.
- Supprimer une demande supprime ses fichiers disque (événements Eloquent, la
  FK cascade seule ne le ferait pas).

**Pourquoi pas `pko/lunar-media-core`** : la médiathèque sert ses fichiers sur
le disque public par URL directe. Une photo ou un plan envoyé par un client ne
doit être lisible que par l'équipe → table de fichiers dédiée, disque privé.

### Configuration (`config/quote-requests.php`)

| Clé | Env | Défaut |
|---|---|---|
| `attachments.max_size_kb` | `QUOTE_REQUEST_MAX_FILE_KB` | `10240` (10 Mo) |
| `attachments.max_files` | `QUOTE_REQUEST_MAX_FILES` | `8` |
| `attachments.disk` | `QUOTE_REQUEST_DISK` | `local` |
| `attachments.mail_max_total_kb` | `QUOTE_REQUEST_MAIL_ATTACHMENTS_KB` | `15360` (15 Mo) |
| `rate_limit.max_attempts` | `QUOTE_REQUEST_RATE_LIMIT` | `5` demandes / heure / IP |
| `max_lines` | — | `30` |

Livewire plafonne lui-même un fichier temporaire à 12 Mo
(`livewire.temporary_file_upload.rules`) : relever aussi ce plafond pour
dépasser 12 Mo.

## Anti-spam

Honeypot `website` (succès simulé, rien n'est stocké ni envoyé, comme
`ContactPage`) + `RateLimiter` par IP (`quote-request:<ip>`), compté seulement
sur une demande valide.

## E-mails

Deux clés `pko/lunar-mail-templates`, en `ShouldQueue`, envoyées via `OnceMailer` :

| Clé | Destinataire | Classe |
|---|---|---|
| `quote_request.received_admin` | e-mail de **contact** de la boutique (`brand_setting('contact.email')` → `CONTACT_EMAIL`), repli `admin_notification_email()` puis `mail.from.address` | `QuoteRequestAdminMail` (Reply-To = demandeur) |
| `quote_request.received` | le demandeur | `QuoteRequestReceivedMail` |

Pièces jointes du mail équipe (`Support\MailAttachmentSelection`) : les fichiers
sont attachés dans l'ordre d'envoi tant que leur cumul reste sous
`attachments.mail_max_total_kb` (15 Mo, ≈ 20 Mo encodés en base64, sous la
limite SMTP courante de 25 Mo). Un fichier qui ferait dépasser le plafond est
sauté (un plus petit derrière peut encore passer), un fichier absent du disque
n'est jamais joint. Le placeholder `:attachments` indique pour chaque nom
« joint à ce mail » ou « à télécharger dans l'admin ». Une demande complète
(8 × 10 Mo) ne tient pas dans un mail : d'où le plafond plutôt que tout joindre.
L'accusé de réception client ne porte jamais de pièce jointe.
Contenus par défaut dans `mail-templates/database/content/fr.php`, éditables en
back-office (Configuration → E-mails).

## Admin

`QuoteRequestResource` (plugin `QuoteRequestsPlugin`, groupe Ventes & Clients via
`lunar-admin-nav`) : liste filtrable par type et statut, badge du nombre de
demandes nouvelles. Fiche en deux colonnes : à gauche le contenu à chiffrer
(message, photos en miniatures ouvrables en lightbox avec navigation clavier,
autres fichiers en cartes téléchargeables, produits et quantités, contexte
d'origine, notes internes), à droite le suivi (statut, type, date) et le contact
en libellés en ligne. Les sections produits et contexte sont masquées quand
elles sont vides. Actions « Changer le statut » (+ notes internes) et
« Répondre par e-mail ». Pas de création depuis l'admin.

### Policies

`app/Policies/QuoteRequestPolicy.php` suit le gabarit Shield (permissions
`*_quote::request`). Comme pour les autres modèles des packages PKO, Laravel ne
découvre pas une policy `App\Policies\…` pour un modèle hors `App\Models` : elle
n'est pas enregistrée, et Filament autorise alors tout staff connecté. Le
téléchargement applique la même règle (policy si enregistrée, sinon tout staff) :
il n'ouvre jamais plus que la page qui affiche le lien.

## Branchement des CTA

`packages/pko/storefront/config/storefront.php` : `nav.quote_url`, footer
« Demander un devis » / « Identifier une pièce », services d'accueil « Devis pour
un projet » (`?mode=project`) et « Identification d'une pièce »
(`?mode=part_photo`). La page `/contact` reste en place (bandeau « Nous contacter »).

Migration de données `2026_11_09_100100_point_legacy_quote_links_to_quote_request_form` :
réécrit dans `pko_storefront_settings` (`nav.quote_url`, `footer.columns`,
`home.services`) **uniquement** les anciennes valeurs par défaut
(`Support\LegacyQuoteLinks`) : `/pages/identifier-une-piece` → mode photo ;
`/contact` seulement sur un lien dont le libellé contient « devis » (« projet »
→ mode projet). Un « Nous contacter » vers `/contact` et toute URL personnalisée
restent intacts. Pas de rollback (valeur migrée indiscernable d'une saisie admin).

`contact` et `demande-de-devis` sont ajoutés aux segments réservés des types de
contenu CMS (`PostType::reservedUrlSegments()`).

## Points d'entrée storefront

Tous générés par `quote_request_url()`, jamais de formulaire dupliqué.

| Où | Quoi | Mode | Contexte |
|---|---|---|---|
| Fiche produit (toutes), sous « Ajouter au panier » | bouton secondaire « Demander un conseil ou un devis », visible sans compte | `general` | variante affichée (`product` = id), `ref` = SKU, `from` = chemin de la fiche |
| Catégories de l'arborescence Pièces détachées (racine + sous-catégories), après la grille | bandeau « Un doute sur la pièce à commander ? » + « Envoyer une photo » | `part_photo` | `collection` = slug courant, `from` = chemin de la catégorie |
| Fiches des produits rattachés à cette arborescence, après documents/compatibilités et avant « Ça peut aussi vous intéresser » | même bandeau | `part_photo` | variante, SKU, chemin de la fiche |

- Bandeau : composant anonyme `<x-pko-quote-requests::part-help-banner :url="…" />`
  (`resources/views/components/part-help-banner.blade.php`), bouton `primary`
  (le lime reste réservé au CTA « Ajouter au panier »).
- Arborescence : `Pko\StorefrontCms\Support\SparePartsCatalog` (réglage
  `catalog.spare_parts_collection_id`, défaut slug `pieces-detachees`), cf.
  [storefront-cms.md](storefront-cms.md).
- URLs calculées dans `App\Livewire\CollectionPage::getPartHelpUrlProperty()` et
  `App\Livewire\ProductPage::getQuoteAdviceUrlProperty()` / `getPartHelpUrlProperty()` ;
  `from` est construit depuis la route (pas `request()->getRequestUri()`, qui vaut
  `/livewire/update` lors d'un re-rendu Livewire, ex. changement de variante).

## Installation

Package path-repository : après merge, `make composer CMD='install'` (symlink
`vendor/pko/lunar-quote-requests` + découverte du provider), puis
`make artisan CMD='migrate'`, `make artisan CMD='pko:mail-templates:sync'`,
`make shield-sync`. Tant que le package n'est pas installé, le panel démarre
quand même (`class_exists` sur le plugin et l'entrée de menu), mais
`/demande-de-devis` répond 404.
