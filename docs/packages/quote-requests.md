# pko/lunar-quote-requests — formulaire de devis unique

Un seul parcours pour tous les CTA « Demander un devis », « Devis pour un projet »
et « Identification d'une pièce ». Page publique Livewire `/demande-de-devis`
(route `quote-request`), demandes persistées et suivies en back-office
(Ventes & Clients → Demandes de devis). Aucun prix n'est affiché dans ce parcours.

## Modes

`Pko\QuoteRequests\Enums\QuoteRequestMode` — un formulaire, trois jeux de textes :

| Mode | Valeur | Spécificités |
|---|---|---|
| Demande générale | `general` (défaut) | lignes produit dépliées |
| Projet | `project` | téléphone obligatoire, lignes dépliées |
| Identification de pièce | `part_photo` | **au moins une photo** (JPG/PNG/WebP) obligatoire, lignes repliées |

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
`/demande-de-devis?mode=project`.

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

Les pièces jointes ne sont **pas** attachées au mail équipe (jusqu'à 80 Mo, et
elles restent privées) : le mail liste leurs noms et renvoie vers la fiche admin.
Contenus par défaut dans `mail-templates/database/content/fr.php`, éditables en
back-office (Configuration → E-mails).

## Admin

`QuoteRequestResource` (plugin `QuoteRequestsPlugin`, groupe Ventes & Clients via
`lunar-admin-nav`) : liste filtrable par type et statut, badge du nombre de
demandes nouvelles, fiche avec contexte (lien produit admin), lignes, pièces
jointes téléchargeables, action « Changer le statut » (+ notes internes),
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

## Installation

Package path-repository : après merge, `make composer CMD='install'` (symlink
`vendor/pko/lunar-quote-requests` + découverte du provider), puis
`make artisan CMD='migrate'`, `make artisan CMD='pko:mail-templates:sync'`,
`make shield-sync`. Tant que le package n'est pas installé, le panel démarre
quand même (`class_exists` sur le plugin et l'entrée de menu), mais
`/demande-de-devis` répond 404.
