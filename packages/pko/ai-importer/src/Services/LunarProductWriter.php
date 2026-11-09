<?php

declare(strict_types=1);

namespace Pko\AiImporter\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\Url;
use Pko\AiImporter\Enums\LogLevel;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\RowFilter;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Enums\UpdateMode;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Support\NumberParser;
use Pko\CatalogFeatures\Facades\Features;
use Pko\CatalogFeatures\Models\BrandFamily;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Support\BrandReferencePrefix;
use Pko\CatalogFeatures\Support\ProductReference;
use Pko\LunarMediaCore\Services\MediaLibraryImporter;
use Pko\ProductDocuments\Models\DocumentCategory;
use Pko\ProductDocuments\Services\ProductDocumentManager;
use Pko\ProductVideos\Services\ProductVideoManager;
use Pko\ShippingCommon\Models\Supplier;

/**
 * Writes a single `StagingRecord` into the Lunar data model.
 *
 * Contract — the keys the writer understands in `StagingRecord::data`:
 *
 *  - `reference`            référence source : SKU de repli (cf. « Référence boutique »).
 *  - `sku`                  SKU FORCÉ : prioritaire sur la référence composée.
 *  - `name`                 (required on create) TranslatedText[default_lang].
 *  - `description` / `description_short` / `meta_title` / `meta_description` /
 *    `meta_keywords`        attribute_data, TranslatedText.
 *  - `url_key`              attribute_data.url (Text).
 *  - `ean`                  ProductVariant.ean.
 *  - `stock`                int, ProductVariant.stock.
 *  - `price_cents`          int cents, base Price row.
 *  - `compare_price_cents`  int cents, optional compare-at price.
 *  - `cost_price_cents`     int cents, prix d'achat (coût) → ProductVariant.pko_cost_price
 *                           (assignation directe : colonne non-fillable).
 *  - `ecotax_cents`         int cents HT par unité, éco-participation INCLUSE dans le
 *                           prix (modèle « dont ») → ProductVariant.pko_ecotax_cents
 *                           (assignation directe : colonne non-fillable).
 *  - `weight_value`         float kg.
 *  - `length_value` / `width_value` / `height_value`  floats in cm.
 *  - `brand_name`           Brand::firstOrCreate(['name' => ...]) → Product.brand_id.
 *  - `product_type_handle`  lookup ProductType by handle, fallback to first.
 *  - `tax_class_handle`     lookup TaxClass par nom (la table Lunar n'a pas de colonne
 *                           `handle`), insensible à la casse. Vide → classe par défaut ;
 *                           inconnu → classe par défaut + warning `ImportLog`.
 *                           Jamais la « première » classe (cf. docs/taxes.md).
 *  - `collections`          array|CSV of collection IDs or handles — syncWithoutDetaching.
 *  - `features`             hash `{family_handle => [value_handle, ...]}` — routed through
 *                           notre package `catalog-features` (Features::syncByHandles), NOT Lunar's
 *                           native Attribute/attribute_data system.
 *  - `images`               array|CSV|JSON d'URLs distantes → médiathèque (dossier `products`)
 *                           puis lien `pko_mediables` (groupe `product`). Idempotent : une
 *                           URL déjà importée (ou le même fichier sous une autre URL, ou
 *                           déposé à la main) est réutilisée, jamais dupliquée ; une image
 *                           déjà liée au produit n'est pas liée deux fois.
 *  - `replace_images`       0/1 (alias PS `delete_existing_images`). 1 = la galerie du
 *                           produit est REMPLACÉE par les images de la ligne (liens seuls,
 *                           les fichiers restent en médiathèque). Défaut : ajout.
 *  - `videos`               array|CSV d'URLs OU JSON `[{url,title}]` (format prépa PrestaShop) —
 *                           YouTube/Vimeo/Dailymotion/MP4, routé via `pko/product-videos`
 *                           (addIfNotExists, titre conservé). Idempotent : URL déjà attachée
 *                           au produit = skip, URL non reconnue = warning loggé.
 *  - `documents`            array|CSV|JSON `[{type,url,name}]` — notices/brochures →
 *                           médiathèque (dossier `documents`, PDF) + `pko_product_documents`
 *                           (catégorie = type, ex NOTICE/BROCH).
 *  - `image_alt`            texte alternatif posé sur les médias image importés (si absent).
 *  - `tags`                 CSV|array → Lunar `syncTags` (valeurs normalisées en MAJUSCULES).
 *  - `mpn`                  ProductVariant.mpn.
 *  - `min_quantity`         ProductVariant.min_quantity (défaut 1).
 *  - `supplier`             nom → fournisseur existant (insensible à la casse) ou créé →
 *                           Product.pko_supplier_id (assignation directe : non-fillable).
 *  - `port_mode`            inherit|standard|flat|free|quote → Product.pko_port_mode.
 *                           Vide = inchangé ; valeur inconnue = ignorée + warning.
 *  - `logistics_class`      ancien code A/B/C → standard/inherit/quote (si `port_mode` absent).
 *  - `transport_price_cents` int cents HT, forfait transport du produit (alias PS
 *                           `additional_shipping_cost` en euros) → Product.pko_transport_price_cents.
 *                           > 0 : REMPLACE le forfait existant et passe le produit en port
 *                           `flat` (sauf `port_mode` explicite). 0 ou vide : IGNORÉ, le
 *                           réglage en place est conservé. Jamais de second tarif créé.
 *  - `brand_family_code` / `brand_family_label`  famille fabricant PRIVÉE (famille de
 *                           remise FAB-DIS : FAM1/FAM1L, GAMME/FONCTION… selon la marque).
 *                           Code EXACT (trim seul) → `BrandFamily` (brand_id, code) créée
 *                           au besoin, libellé mis à jour → Product.pko_brand_family_id
 *                           (assignation directe). Code vide = produit inchangé.
 *
 * Anything the writer doesn't recognise is ignored. NON couverts (pas de colonne
 * cible sans migration) : `supplier_reference`, promos
 * (`on_sale`/`reduction_*`), `visibility`, `condition`, `unit_price`, etc.
 *
 * Unresolved handles (`collections` or `features`) — handles that don't match
 * an existing Collection / FeatureFamily / FeatureValue — are NOT a hard error.
 * The record is still written successfully ; an `ImportLog` warning is recorded
 * with the list of skipped handles for diagnostic. Common with LLM-driven imports
 * where the model can hallucinate handles outside the allowed taxonomy.
 *
 * Legacy aliases (PrestaShop FAB-DIS compat — auto-normalised on entry, canonical key wins):
 *   - `ean13` → `ean`, `quantity` → `stock`, `manufacturer` → `brand_name`,
 *     `link_rewrite` → `url_key`, `depth` → `length_value`, `width` → `width_value`,
 *     `height` → `height_value`, `weight` → `weight_value`, `image` → `images`,
 *     `category` → `collections`, `attachments` → `documents`,
 *     `minimal_quantity` → `min_quantity`.
 *   - `price_tex` (euros, float) → `price_cents` (int, ×100 rounded).
 *   - `wholesale_price` (euros, float) → `cost_price_cents` (int, ×100 rounded).
 *   - `ecotax` (euros, float) → `ecotax_cents` (int, ×100 rounded).
 *   - `additional_shipping_cost` (euros, float) → `transport_price_cents`.
 *   - `delete_existing_images` → `replace_images`.
 *
 * Prix de base (devise par défaut, sans groupe client, palier 1) : UNE seule
 * ligne `lunar_prices` par variante. L'import met à jour la ligne existante ;
 * des doublons ou une ligne « fantôme » (morph FQCN) sont fusionnés, jamais
 * complétés par un tarif supplémentaire. Les prix par groupe client et par
 * palier de quantité ne sont pas touchés.
 *
 * Éco-participation (modèle « dont », cf. docs/ecotax.md) : PrestaShop fournit
 * `price_tex` HORS éco-taxe. Quand le prix vient de l'alias `price_tex`, on
 * calcule `price_cents = round((price_tex + ecotax) × 100)`. Quand la source
 * fournit directement `price_cents`, l'éco-taxe est réputée déjà incluse.
 *
 * Champs numériques (prix, stock, min_quantity, dimensions) : parsés via
 * `NumberParser` (virgule décimale FR, espaces de milliers acceptés). Une valeur
 * vide n'est JAMAIS convertie en 0 : la clé est ignorée et la valeur en base
 * conservée. Une valeur illisible est ignorée avec un warning `ImportLog`.
 *
 * Référence boutique (cf. docs/product-reference.md) — SKU à la création :
 *   1. `sku` (forcé) s'il est fourni ;
 *   2. sinon préfixe de la marque + `mpn` brut ({@see ProductReference::compose()}) ;
 *   3. sinon `reference`.
 * Une ligne sans aucune de ces trois sources est rejetée (« reference manquante »).
 * Rapprochement d'un produit existant (join `reference`, défaut) : SKU = forcé,
 * puis composé, puis `reference`, puis marque + MPN exact. Plusieurs variantes
 * pour la même marque + MPN → ligne rejetée (ambiguë), jamais de doublon créé.
 * Un SKU existant n'est JAMAIS réécrit par l'import (cf. `pko:references:sync`).
 * Une marque créée par l'import reçoit son préfixe par défaut s'il est libre.
 *
 * Resolvers cache look-ups per instance — build one writer per job, not per row.
 */
final class LunarProductWriter
{
    /**
     * Clés canoniques reconnues par le writer (contrat ci-dessus). Source de
     * vérité unique, réutilisée par le rapport de compatibilité de
     * `ai-importer:import-ps-config` — toute clé ajoutée au writer doit être
     * ajoutée ici, sinon elle serait annoncée « ignorée » à l'import d'une config.
     *
     * @var array<int, string>
     */
    public const CANONICAL_KEYS = [
        'reference', 'sku', 'name', 'description', 'description_short',
        'meta_title', 'meta_description', 'meta_keywords', 'url_key',
        'ean', 'mpn', 'stock', 'min_quantity',
        'price_cents', 'compare_price_cents', 'cost_price_cents', 'ecotax_cents',
        'weight_value', 'length_value', 'width_value', 'height_value',
        'brand_name', 'supplier', 'brand_family_code', 'brand_family_label',
        'product_type_handle', 'tax_class_handle',
        'collections', 'tags', 'features', 'images', 'image_alt', 'videos', 'documents',
        'port_mode', 'logistics_class', 'transport_price_cents', 'replace_images',
    ];

    /**
     * Alias legacy PrestaShop → clé canonique (copie 1:1, sans conversion).
     *
     * @var array<string, string>
     */
    public const LEGACY_ALIASES = [
        'ean13' => 'ean',
        'quantity' => 'stock',
        'minimal_quantity' => 'min_quantity',
        'manufacturer' => 'brand_name',
        'link_rewrite' => 'url_key',
        'depth' => 'length_value',
        'width' => 'width_value',
        'height' => 'height_value',
        'weight' => 'weight_value',
        'category' => 'collections',
        'image' => 'images',
        'attachments' => 'documents',
        'delete_existing_images' => 'replace_images',
    ];

    /**
     * Alias legacy en euros → clé canonique en cents (×100 arrondi).
     *
     * @var array<string, string>
     */
    public const LEGACY_EURO_ALIASES = [
        'price_tex' => 'price_cents',
        'wholesale_price' => 'cost_price_cents',
        'ecotax' => 'ecotax_cents',
        'additional_shipping_cost' => 'transport_price_cents',
    ];

    /**
     * Champs numériques canoniques et leur type cible.
     * Parsés via NumberParser — vide = ignoré, illisible = ignoré + warning.
     */
    private const NUMERIC_FIELDS = [
        'price_cents' => 'int',
        'compare_price_cents' => 'int',
        'cost_price_cents' => 'int',
        'ecotax_cents' => 'int',
        'transport_price_cents' => 'int',
        'stock' => 'int',
        'min_quantity' => 'int',
        'weight_value' => 'float',
        'length_value' => 'float',
        'width_value' => 'float',
        'height_value' => 'float',
    ];

    /**
     * Clés qui identifient un produit : lues avant `columns_to_import`.
     */
    private const IDENTITY_KEYS = ['sku', 'reference', 'brand_name', 'mpn', 'ean'];

    /** Modes de facturation du port acceptés (`lunar_products.pko_port_mode`). */
    private const PORT_MODES = ['inherit', 'standard', 'flat', 'free', 'quote'];

    /** Ancienne classe logistique A/B/C → mode de port. */
    private const LOGISTICS_CLASS_MODES = ['A' => 'standard', 'B' => 'inherit', 'C' => 'quote'];

    /** @var array<string, Brand> Cache nom → marque (lookup ou création). */
    private array $brandCache = [];

    /** @var array<int, true> Marques sans préfixe déjà signalées dans les logs du job. */
    private array $brandsWithoutPrefixWarned = [];

    /** @var array<string, int> */
    private array $collectionHandleCache = [];

    /** @var array<string, int> */
    private array $productTypeCache = [];

    /** @var array<string, int> */
    private array $taxClassCache = [];

    private ?int $defaultProductTypeId = null;

    private ?int $defaultTaxClassId = null;

    private ?int $defaultCurrencyId = null;

    private ?string $defaultLanguage = null;

    private UpdateMode $updateMode = UpdateMode::All;

    private RowFilter $rowFilter = RowFilter::All;

    private string $joinColumn = 'reference';

    /** @var array<int, string> Sous-ensemble de clés réellement écrites (vide = tout). */
    private array $columnsToImport = [];

    /** @var array<string, int> Cache handle → id des catégories de documents. */
    private array $documentCategoryCache = [];

    /** @var array<string, int> Cache nom → id des fournisseurs. */
    private array $supplierCache = [];

    /** @var array<string, BrandFamily> Cache « brand_id\0code » → famille fabricant. */
    private array $brandFamilyCache = [];

    /** Une instance par import : son mémo évite de retélécharger une URL déjà traitée. */
    private ?MediaLibraryImporter $mediaImporter = null;

    /** ID du job courant — 0 = pas de logging granulaire. */
    private int $jobId = 0;

    /** Numéro de ligne en cours de traitement (renseigné à chaque write()). */
    private int $currentRowNumber = 0;

    /** @var array<int, array<string, mixed>> Logs en attente de flush batch. */
    private array $pendingLogs = [];

    /**
     * Active le logging granulaire ligne par ligne pour ce writer.
     * À appeler une fois par job, avant la boucle d'écriture.
     */
    public function setJobId(int $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    /**
     * Applique les options de job (`pko_ai_importer_jobs.options`) au writer.
     * Idempotent et fluide : appeler une fois par job avant la boucle d'écriture.
     *
     * Clés reconnues : `update_mode`, `row_filter`, `join_column`, `columns_to_import`.
     *
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>|null  $options
     */
    public function configure(array|\ArrayAccess|null $options): self
    {
        $options = $options instanceof \ArrayObject ? $options->getArrayCopy() : (array) ($options ?? []);

        if (isset($options['update_mode'])) {
            $this->updateMode = $options['update_mode'] instanceof UpdateMode
                ? $options['update_mode']
                : (UpdateMode::tryFrom((string) $options['update_mode']) ?? UpdateMode::All);
        }

        if (isset($options['row_filter'])) {
            $this->rowFilter = $options['row_filter'] instanceof RowFilter
                ? $options['row_filter']
                : (RowFilter::tryFrom((string) $options['row_filter']) ?? RowFilter::All);
        }

        if (! empty($options['join_column'])) {
            $this->joinColumn = (string) $options['join_column'];
        }

        if (! empty($options['columns_to_import']) && is_array($options['columns_to_import'])) {
            $this->columnsToImport = array_values(array_map('strval', $options['columns_to_import']));
        }

        return $this;
    }

    public function write(StagingRecord $record): void
    {
        $this->currentRowNumber = $record->row_number ?? 0;

        try {
            // Une ligne = une transaction : un échec en cours d'écriture (SKU en
            // doublon, valeur trop longue…) ne laisse ni produit sans variante ni
            // produit à moitié mis à jour. Les fichiers médias déjà téléchargés
            // restent en médiathèque (réutilisés au prochain passage).
            DB::transaction(fn () => $this->doWrite($record));
        } catch (\Throwable $e) {
            // Les caches peuvent référencer des entités créées puis annulées par le
            // rollback (marque, fournisseur, famille…) : on les oublie.
            $this->forgetCreatedEntities();

            throw $e;
        } finally {
            $this->flushPendingLogs();
        }
    }

    private function forgetCreatedEntities(): void
    {
        $this->brandCache = [];
        $this->supplierCache = [];
        $this->brandFamilyCache = [];
        $this->documentCategoryCache = [];
        $this->mediaImporter = null;
    }

    private function doWrite(StagingRecord $record): void
    {
        $data = $record->data instanceof \ArrayObject
            ? $record->data->getArrayCopy()
            : (array) $record->data;

        $numericWarnings = [];
        $data = self::normalizeLegacyKeys($data, $numericWarnings);
        $data = self::sanitizeNumericFields($data, $numericWarnings);
        // Clés d'identification, lues AVANT columns_to_import : une MAJ restreinte
        // (ex. prix seul) doit retrouver le produit par marque + MPN sans écrire ces clés.
        $identity = array_intersect_key($data, array_flip(self::IDENTITY_KEYS));
        $data = $this->filterImportColumns($data);
        $this->logNumericWarnings($record, $numericWarnings);

        // Modèle « dont » : l'éco-participation est une part du prix, jamais plus.
        if (isset($data['price_cents'], $data['ecotax_cents']) && $data['ecotax_cents'] > $data['price_cents']) {
            $this->logWarning($record, 'Éco-participation supérieure au prix : éco-participation ignorée.', [
                'price_cents' => $data['price_cents'],
                'ecotax_cents' => $data['ecotax_cents'],
            ]);
            unset($data['ecotax_cents']);
        }

        $forcedSku = self::scalarTrim($identity['sku'] ?? null);
        $reference = self::scalarTrim($identity['reference'] ?? null);
        $brandName = self::scalarTrim($identity['brand_name'] ?? null);
        $mpn = self::rawMpn($identity['mpn'] ?? null);

        if ($forcedSku === '' && $reference === '' && ($brandName === '' || $mpn === null)) {
            $this->markError($record, 'reference manquante');

            return;
        }

        $brand = $brandName !== '' ? $this->findBrand($brandName) : null;
        $composed = $forcedSku === '' ? ProductReference::compose($brand, $mpn) : null;
        $sku = $forcedSku !== '' ? $forcedSku : ($composed ?? $reference);

        $this->addLog(LogLevel::Debug, "Début importRow ligne {$this->currentRowNumber}");
        $this->addLog(LogLevel::Debug, "Recherche produit existant (joinColumn: {$this->joinColumn})");
        $this->addLog(LogLevel::Debug, 'valeur clé = '.($sku !== '' ? $sku : "{$brandName} + {$mpn}"));

        [$variant, $ambiguity] = $this->findExistingVariant($data + $identity, [$forcedSku, $composed, $reference], $brand, $mpn);
        if ($ambiguity !== null) {
            $this->markError($record, $ambiguity);

            return;
        }
        $product = $variant?->product;
        $exists = $product !== null;

        if ($exists) {
            $this->addLog(LogLevel::Debug, "Produit trouvé id={$product->id}");
        } else {
            $this->addLog(LogLevel::Debug, 'Création produit');
        }

        // row_filter — n'écrire que les produits absents / existants selon l'option.
        if (! $this->rowFilter->allows($exists)) {
            $record->update([
                'status' => StagingStatus::Skipped,
                'lunar_product_id' => $product?->id,
                'error_message' => null,
            ]);

            return;
        }

        $unresolved = ['collections' => [], 'features' => []];

        if (! $exists) {
            // Création : toujours intégrale (update_mode ne s'applique qu'aux MAJ).
            if (! isset($data['name']) || $data['name'] === '') {
                $this->markError($record, 'name requis à la création');

                return;
            }

            // Une création enregistre toujours l'identité complète du produit
            // (marque, MPN), même si columns_to_import les excluait.
            $data += $identity;
            $brandId = $this->resolveBrand($data);
            $brand = $brandId !== null ? $this->brandCache[$brandName] ?? null : null;
            $sku = $this->creationSku($record, $forcedSku, $reference, $brand, $mpn);
            if ($sku === null) {
                return;
            }

            $product = Product::query()->create([
                'product_type_id' => $this->resolveProductTypeId($data),
                'status' => 'published',
                'brand_id' => $brandId,
                'attribute_data' => $this->buildAttributeData($data),
            ]);
            $this->applySupplier($product, $data);
            $this->applyBrandFamily($record, $product, $data);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'tax_class_id' => $this->resolveTaxClassId($record, $data),
                'sku' => $sku,
                'ean' => $data['ean'] ?? null,
                'mpn' => $mpn,
                'stock' => (int) ($data['stock'] ?? 0),
                'unit_quantity' => 1,
                'min_quantity' => isset($data['min_quantity']) ? max(1, $data['min_quantity']) : 1,
                'quantity_increment' => 1,
                'shippable' => true,
                'purchasable' => 'always',
                ...$this->dimensions($data),
            ]);

            $this->applyPrice($variant, $data);
            $this->applyCostPrice($variant, $data);
            $this->applyEcotax($variant, $data);
            $this->applyShipping($record, $product, $data, true);
            $unresolved = $this->applyRelations($product, $data);
            $wasCreate = true;
        } else {
            // Mise à jour : champs écrits selon update_mode.
            $wasCreate = false;
            $this->addLog(LogLevel::Debug, "Mode update: {$this->updateMode->value}");

            if ($this->updateMode->writesFullRecord()) {
                $product->update(array_filter([
                    'brand_id' => $this->resolveBrand($data),
                    'attribute_data' => $this->buildAttributeData($data, $product->attribute_data),
                ], static fn ($v) => $v !== null));
                $this->applySupplier($product, $data);
                $this->applyBrandFamily($record, $product, $data);
                $this->applyShipping($record, $product, $data, false);

                $variant->update(array_filter([
                    'ean' => $data['ean'] ?? null,
                    'mpn' => $data['mpn'] ?? null,
                    'stock' => $data['stock'] ?? null,
                    'min_quantity' => isset($data['min_quantity']) ? max(1, $data['min_quantity']) : null,
                    ...$this->dimensions($data),
                ], static fn ($v) => $v !== null));

                $this->applyPrice($variant, $data);
                $this->applyCostPrice($variant, $data);
                $this->applyEcotax($variant, $data);
                $unresolved = $this->applyRelations($product, $data);
            } else {
                if ($this->updateMode->writesPrice()) {
                    // Prix et éco-taxe restent cohérents (modèle « dont »).
                    $this->applyPrice($variant, $data);
                    $this->applyCostPrice($variant, $data);
                    $this->applyEcotax($variant, $data);
                }
                if ($this->updateMode->writesStock() && isset($data['stock'])) {
                    $variant->update(['stock' => $data['stock']]);
                }
            }
        }

        $record->update([
            'status' => $wasCreate ? StagingStatus::Created : StagingStatus::Updated,
            'lunar_product_id' => $product->id,
            'imported_at' => now(),
            'error_message' => null,
        ]);

        $this->logUnresolvedHandles($record, $sku, $unresolved);
    }

    /**
     * Restreint les données à `columns_to_import` quand l'option est définie.
     * Les clés essentielles à l'identification (reference, name, colonne de
     * jointure) sont toujours conservées, sinon le produit deviendrait
     * impossible à créer ou résoudre.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterImportColumns(array $data): array
    {
        if ($this->columnsToImport === []) {
            return $data;
        }

        $essential = ['reference', 'name', $this->joinColumn];
        // Le prix importé INCLUT l'éco-participation (modèle « dont ») : écrire l'un
        // sans l'autre laisserait un prix majoré sans sa mention « dont éco ».
        if (in_array('price_cents', $this->columnsToImport, true)) {
            $essential[] = 'ecotax_cents';
        }
        // Le forfait transport n'a de sens qu'avec son mode de port.
        if (in_array('transport_price_cents', $this->columnsToImport, true)) {
            $essential[] = 'port_mode';
        }
        $keep = array_flip(array_unique([...$this->columnsToImport, ...$essential]));

        return array_intersect_key($data, $keep);
    }

    /**
     * Résout le variant existant selon la colonne de jointure (`join_column`).
     *
     * Jointure `reference`/`sku` (défaut) : SKU = forcé, puis composé (préfixe
     * marque + MPN), puis `reference` (produits importés avant le préfixe), puis
     * marque + MPN exact. Ce dernier rapprochement évite un doublon quand le SKU
     * a changé de forme ; s'il désigne plusieurs variantes, la ligne est rejetée
     * plutôt que d'en choisir une au hasard. Jointure `ean`/`ean13` : EAN seul.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string|null>  $skuCandidates  par ordre de priorité
     * @return array{0: ?ProductVariant, 1: ?string} [variant, message d'ambiguïté]
     */
    private function findExistingVariant(array $data, array $skuCandidates, ?Brand $brand, ?string $mpn): array
    {
        if (in_array($this->joinColumn, ['ean', 'ean13'], true)) {
            $ean = trim((string) ($data['ean'] ?? ''));

            return [$ean === '' ? null : ProductVariant::query()->where('ean', $ean)->first(), null];
        }

        foreach (array_unique(array_filter($skuCandidates, static fn ($v) => $v !== null && $v !== '')) as $candidate) {
            $variant = ProductVariant::query()->where('sku', $candidate)->first();
            if ($variant !== null) {
                return [$variant, null];
            }
        }

        if ($brand === null || $mpn === null) {
            return [null, null];
        }

        $matches = ProductVariant::query()
            ->where('mpn', $mpn)
            ->whereHas('product', fn ($q) => $q->where('brand_id', $brand->id))
            ->limit(2)
            ->get();

        if ($matches->count() > 1) {
            return [null, "référence ambiguë : plusieurs variantes « {$brand->name} » ont le MPN « {$mpn} » — aucun produit créé ni modifié"];
        }

        return [$matches->first(), null];
    }

    /**
     * SKU d'un produit créé : forcé, sinon composé avec la marque (éventuellement
     * créée à l'instant, préfixe compris), sinon `reference`. Null = ligne
     * rejetée (aucune source, ou cible déjà portée par une autre variante).
     */
    private function creationSku(StagingRecord $record, string $forcedSku, string $reference, ?Brand $brand, ?string $mpn): ?string
    {
        if ($forcedSku !== '') {
            return $forcedSku;
        }

        $composed = ProductReference::compose($brand, $mpn);
        if ($composed === null && $brand !== null && $mpn !== null) {
            $this->warnBrandWithoutPrefix($brand);
        }

        $sku = $composed ?? $reference;
        if ($sku === '') {
            $this->markError($record, 'reference manquante : marque « '.($brand?->name ?? '?').' » sans préfixe de référence et aucune référence source');

            return null;
        }

        if ($composed !== null && ProductVariant::query()->where('sku', $composed)->exists()) {
            $this->markError($record, "SKU cible « {$composed} » déjà porté par une autre variante — aucun produit créé");

            return null;
        }

        return $sku;
    }

    private function warnBrandWithoutPrefix(Brand $brand): void
    {
        if (isset($this->brandsWithoutPrefixWarned[$brand->id])) {
            return;
        }
        $this->brandsWithoutPrefixWarned[$brand->id] = true;

        $this->addLog(
            LogLevel::Warning,
            "Marque « {$brand->name} » sans préfixe de référence : SKU = référence source. Définir le préfixe dans l'admin (Marques).",
        );
    }

    /**
     * Référence fabricant BRUTE (ni trim ni normalisation) ; null si vide ou
     * uniquement des espaces. Un nombre est converti tel quel en chaîne.
     */
    private static function rawMpn(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = (string) $value;

        return trim($value) === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyPrice(ProductVariant $variant, array $data): void
    {
        if (! isset($data['price_cents'])) {
            return;
        }

        $this->upsertPrice(
            $variant,
            $data['price_cents'],
            $data['compare_price_cents'] ?? null,
            array_key_exists('compare_price_cents', $data),
        );
    }

    /**
     * Pose `pko_cost_price` (prix d'achat, en cents) par assignation DIRECTE : la
     * colonne custom n'est pas fillable sur le modèle Lunar ProductVariant → un
     * mass-assignment la droppe silencieusement (cf. applySupplier). No-op si
     * `cost_price_cents` absent de la source.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyCostPrice(ProductVariant $variant, array $data): void
    {
        if (! isset($data['cost_price_cents'])) {
            return;
        }

        $variant->pko_cost_price = $data['cost_price_cents'];
        $variant->save();
    }

    /**
     * Pose `pko_ecotax_cents` (éco-participation HT unitaire, incluse dans le
     * prix) par assignation DIRECTE (colonne non-fillable, cf. applyCostPrice).
     * No-op si `ecotax_cents` absent (valeur vide jamais convertie en 0).
     *
     * @param  array<string, mixed>  $data
     */
    private function applyEcotax(ProductVariant $variant, array $data): void
    {
        if (! isset($data['ecotax_cents'])) {
            return;
        }

        $variant->pko_ecotax_cents = max(0, (int) $data['ecotax_cents']);
        $variant->save();
    }

    /**
     * Synchronise collections / features / images / vidéos d'un produit.
     *
     * @param  array<string, mixed>  $data
     * @return array{collections: array<int, string>, features: array<int, string>}
     */
    private function applyRelations(Product $product, array $data): array
    {
        $unresolved = ['collections' => [], 'features' => []];

        if (! empty($data['collections'])) {
            $resolution = $this->resolveCollectionIds($data['collections']);
            if ($resolution['ids'] !== []) {
                $product->collections()->syncWithoutDetaching($resolution['ids']);
            }
            $unresolved['collections'] = $resolution['unresolved'];
        }

        if (! empty($data['tags']) && method_exists($product, 'syncTags')) {
            $tags = $this->splitCsv($data['tags']);
            if ($tags !== []) {
                $product->syncTags(collect($tags));
            }
        }

        if (! empty($data['features']) && class_exists(Features::class)) {
            if (is_array($data['features'])) {
                $unresolved['features'] = $this->findUnresolvedFeatures($data['features']);
                Features::syncByHandles($product, $data['features']);
            } else {
                // Sortie IA « Famille:Valeur 1,Valeur 2|Famille 2:Valeur » : noms → handles.
                $parsed = app(FeatureCatalog::class)->parse((string) $data['features']);
                $unresolved['features'] = $parsed['unresolved'];
                Features::syncByHandles($product, $parsed['features']);
            }
        }

        if (! empty($data['images'])) {
            $this->syncProductImages(
                $product,
                $data['images'],
                isset($data['image_alt']) ? (string) $data['image_alt'] : null,
                self::truthy($data['replace_images'] ?? null),
            );
        }

        if (! empty($data['videos'])) {
            $this->syncProductVideos($product, $data['videos']);
        }

        if (! empty($data['documents'])) {
            $this->syncProductDocuments($product, $data['documents']);
        }

        return $unresolved;
    }

    /**
     * Importe les images dans la médiathèque custom (dossier `products`) puis les
     * lie au produit via `pko_mediables` (média-core, groupe `product` — celui que
     * lisent l'éditeur produit unifié ET le storefront).
     *
     * Anti-doublon à trois niveaux : URL répétée dans la ligne (dédupliquée ici),
     * fichier déjà en médiathèque ({@see MediaLibraryImporter} : URL, alias, sha1),
     * média déjà lié au produit ({@see linkMediaToEntity()}).
     *
     * `$replace` : la galerie du produit devient exactement la liste importée
     * (jamais vidée si aucune image n'a pu être importée).
     */
    private function syncProductImages(Product $product, mixed $raw, ?string $alt = null, bool $replace = false): void
    {
        $urls = $this->uniqueUrls($this->normaliseUrlList($raw));
        if ($urls === []) {
            return;
        }

        $alt = $alt !== null ? trim($alt) : '';
        $importer = $this->mediaImporter();
        $mediaIds = [];
        foreach ($urls as $url) {
            $media = $importer->importFromUrl($url, 'products');
            if ($media === null) {
                $this->addLog(LogLevel::Warning, 'Image non importée ('.($importer->lastError() ?? 'erreur inconnue')."): {$url}");

                continue;
            }
            // Texte alternatif : posé si fourni et absent (ne pas écraser un alt
            // déjà saisi sur un média partagé/dédupliqué).
            if ($alt !== '' && ! $media->getCustomProperty('alt')) {
                $media->setCustomProperty('alt', $alt);
                $media->save();
            }
            $mediaIds[] = (int) $media->id;
            $this->addLog(LogLevel::Debug, "Image bibliothèque #{$media->id}: {$url}");
        }

        $mediaIds = array_values(array_unique($mediaIds));
        if ($mediaIds === []) {
            return;
        }

        if ($replace) {
            $this->replaceEntityMedia($product, $mediaIds, 'product');
        } else {
            $this->linkMediaToEntity($product, $mediaIds, 'product');
        }
    }

    private function mediaImporter(): MediaLibraryImporter
    {
        return $this->mediaImporter ??= app(MediaLibraryImporter::class);
    }

    /**
     * Retire les URLs désignant le même fichier (variante http/https, casse du
     * domaine, ancre), en gardant la première occurrence.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function uniqueUrls(array $urls): array
    {
        $seen = [];
        $out = [];
        foreach ($urls as $url) {
            $key = MediaLibraryImporter::urlKey($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $url;
        }

        return $out;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower(self::scalarTrim($value)), ['1', 'true', 'oui', 'yes', 'y', 'o'], true);
    }

    /**
     * Pose `pko_supplier_id` par assignation DIRECTE (la colonne custom n'est pas
     * fillable sur le modèle Lunar Product → un mass-assignment la droppe
     * silencieusement). No-op si pas de fournisseur en source.
     */
    private function applySupplier(Product $product, array $data): void
    {
        $supplierId = $this->resolveSupplierId($data);
        if ($supplierId !== null && (int) $product->pko_supplier_id !== $supplierId) {
            $product->pko_supplier_id = $supplierId;
            $product->save();
        }
    }

    /**
     * Rattache le produit à sa famille fabricant PRIVÉE (famille de remise).
     *
     * Le code est importé EXACT (trim seul, ni casse ni accents modifiés) : c'est
     * lui que les remises cibleront. Il n'a de sens que dans la marque du produit,
     * d'où la résolution sur (brand_id, code). Le libellé, s'il est fourni,
     * remplace celui en base (le fabricant peut le renommer d'un tarif à l'autre).
     *
     * Code vide → famille actuelle conservée (jamais d'effacement sur une cellule
     * vide). Produit sans marque ou code trop long → warning, rien n'est écrit.
     * `pko_brand_family_id` n'est pas fillable : assignation directe.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyBrandFamily(StagingRecord $record, Product $product, array $data): void
    {
        $code = self::scalarTrim($data['brand_family_code'] ?? null);
        $label = self::scalarTrim($data['brand_family_label'] ?? null);

        if ($code === '') {
            if ($label !== '') {
                $this->logWarning($record, 'Famille fabricant ignorée : libellé sans code', ['label' => $label]);
            }

            return;
        }

        if ($product->brand_id === null) {
            $this->logWarning($record, 'Famille fabricant ignorée : produit sans marque', ['code' => $code]);

            return;
        }

        if (mb_strlen($code) > 100) {
            $this->logWarning($record, 'Famille fabricant ignorée : code de plus de 100 caractères', ['code' => $code]);

            return;
        }

        $family = $this->resolveBrandFamily((int) $product->brand_id, $code, $label);

        if ((int) $product->pko_brand_family_id !== $family->id) {
            $product->pko_brand_family_id = $family->id;
            $product->save();
        }
    }

    private function resolveBrandFamily(int $brandId, string $code, string $label): BrandFamily
    {
        $key = $brandId."\0".$code;

        $family = $this->brandFamilyCache[$key] ??= BrandFamily::query()->firstOrCreate(
            ['brand_id' => $brandId, 'code' => $code],
            ['label' => $label !== '' ? $label : null],
        );

        if ($label !== '' && $family->label !== $label) {
            $family->label = $label;
            $family->save();
        }

        return $family;
    }

    private static function scalarTrim(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Règles de frais de port du produit : `pko_port_mode` + forfait
     * `pko_transport_price_cents`. Un produit n'a qu'UN mode et UN forfait :
     * l'import REMPLACE la valeur en place quand la source en fournit une, et
     * n'y TOUCHE PAS quand la source est vide (jamais de second réglage créé).
     *
     * Mode demandé par la source, par priorité :
     *   1. `port_mode` valide (inherit / standard / flat / free / quote) ;
     *   2. sinon `logistics_class` A / B / C → standard / inherit / quote ;
     *   3. sinon `transport_price_cents` > 0 → `flat` (un forfait n'agit que dans ce mode) ;
     *   4. sinon : produit existant inchangé ; création → `inherit` si le produit
     *      a un fournisseur, `standard` sinon.
     * Valeur inconnue → ignorée + warning (le réglage en place est conservé).
     *
     * Forfait : > 0 remplace le forfait existant ; 0 ou vide = ignoré. Un mode
     * explicitement non forfaitaire efface le forfait (même règle que l'éditeur
     * produit), un forfait fourni avec un tel mode est ignoré + warning.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyShipping(StagingRecord $record, Product $product, array $data, bool $isCreate): void
    {
        $mode = null;
        $rawMode = self::scalarTrim($data['port_mode'] ?? null);
        $rawClass = self::scalarTrim($data['logistics_class'] ?? null);

        if ($rawMode !== '') {
            $candidate = mb_strtolower($rawMode);
            if (in_array($candidate, self::PORT_MODES, true)) {
                $mode = $candidate;
            } else {
                $this->logWarning($record, "Facturation du port « {$rawMode} » inconnue, valeur ignorée (attendu : ".implode(', ', self::PORT_MODES).').', ['port_mode' => $rawMode]);
            }
        } elseif ($rawClass !== '') {
            $mode = self::LOGISTICS_CLASS_MODES[mb_strtoupper($rawClass)] ?? null;
            if ($mode === null) {
                $this->logWarning($record, "Classe logistique « {$rawClass} » inconnue, valeur ignorée (attendu : A, B ou C).", ['logistics_class' => $rawClass]);
            }
        }

        $flat = $data['transport_price_cents'] ?? null;
        $flat = is_int($flat) && $flat > 0 ? $flat : null;

        if ($mode === null && $flat !== null) {
            $mode = 'flat';
        }
        if ($mode === null && $isCreate) {
            $mode = $product->pko_supplier_id !== null ? 'inherit' : 'standard';
        }

        $dirty = false;
        if ($mode !== null && $product->pko_port_mode !== $mode) {
            $product->pko_port_mode = $mode;
            $dirty = true;
        }

        $current = $product->pko_transport_price_cents !== null ? (int) $product->pko_transport_price_cents : null;

        if (($mode ?? $product->pko_port_mode) === 'flat') {
            if ($flat !== null && $current !== $flat) {
                $product->pko_transport_price_cents = $flat;
                $dirty = true;
            } elseif ($flat === null && $current === null && $mode === 'flat') {
                $this->logWarning($record, 'Port forfaitaire sans montant : renseigner le forfait transport du produit.', []);
            }
        } elseif ($mode !== null) {
            if ($flat !== null) {
                $this->logWarning($record, "Forfait transport ignoré : la facturation du port demandée est « {$mode} ».", ['transport_price_cents' => $flat]);
            }
            if ($current !== null) {
                $product->pko_transport_price_cents = null;
                $dirty = true;
            }
        }

        if ($dirty) {
            $product->save();
        }
    }

    /**
     * Résout (ou crée) un fournisseur par son nom → `pko_supplier_id`. Le
     * fournisseur porte la règle de port héritée par ses produits
     * (`port_inclus`) : on rapproche donc l'existant sans tenir compte de la
     * casse ni des espaces multiples, pour ne jamais créer un second
     * fournisseur « SOMFY » / « Somfy » sans règle. Cache par instance.
     */
    private function resolveSupplierId(array $data): ?int
    {
        $name = (string) preg_replace('/\s+/u', ' ', self::scalarTrim($data['supplier'] ?? null));
        if ($name === '' || ! class_exists(Supplier::class)) {
            return null;
        }
        $key = mb_strtolower($name);
        if (isset($this->supplierCache[$key])) {
            return $this->supplierCache[$key];
        }

        $supplier = Supplier::query()->whereRaw('LOWER(name) = ?', [$key])->orderBy('id')->first()
            ?? Supplier::query()->create(['name' => $name]);

        return $this->supplierCache[$key] = (int) $supplier->id;
    }

    /**
     * Découpe une valeur CSV (ou array) en liste de strings non vides.
     *
     * @return array<int, string>
     */
    private function splitCsv(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', array_map('strval', $raw)),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * Importe les documents (notices, brochures) dans la médiathèque (dossier
     * `documents`, PDF) puis crée les liens `pko_product_documents` avec la
     * catégorie déduite du type (NOTICE/BROCH → DocumentCategory).
     *
     * Idempotent : un document déjà en médiathèque est réutilisé, un document
     * déjà lié au produit n'est ni dupliqué ni réordonné.
     */
    private function syncProductDocuments(Product $product, mixed $raw): void
    {
        $items = $this->decodeDocuments($raw);
        if ($items === [] || ! class_exists(ProductDocumentManager::class)) {
            return;
        }

        $importer = $this->mediaImporter();
        $manager = app(ProductDocumentManager::class);
        $seen = [];
        foreach ($items as $item) {
            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $key = MediaLibraryImporter::urlKey($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $name = trim((string) ($item['name'] ?? ''));
            $type = trim((string) ($item['type'] ?? ''));

            $media = $importer->importFromUrl($url, 'documents', $name !== '' ? $name : null, MediaLibraryImporter::DOCUMENT_EXTENSIONS);
            if ($media === null) {
                $this->addLog(LogLevel::Warning, 'Document non importé ('.($importer->lastError() ?? 'erreur inconnue')."): {$url}");

                continue;
            }

            $created = $manager->attachIfMissing($product, (int) $media->id, $this->resolveDocumentCategoryId($type));
            $this->addLog(LogLevel::Debug, "Document #{$media->id} ({$type})".($created ? '' : ' déjà lié').": {$url}");
        }
    }

    /**
     * Attache les vidéos d'un produit via `pko/product-videos`, en conservant le
     * titre. Accepte un CSV d'URLs OU le format JSON `[{url,title}]` produit par la
     * prépa PrestaShop (multiline_aggregate json_array). Idempotent : une URL déjà
     * attachée au produit est skippée ; une URL non reconnue est loggée en warning.
     */
    private function syncProductVideos(Product $product, mixed $raw): void
    {
        $items = $this->decodeVideos($raw);
        if ($items === [] || ! class_exists(ProductVideoManager::class)) {
            return;
        }

        $manager = app(ProductVideoManager::class);
        foreach ($items as $item) {
            try {
                $created = $manager->addIfNotExists($product, $item['url'], $item['title']);
                if ($created !== null) {
                    $this->addLog(LogLevel::Debug, "Vidéo #{$created->id}: {$item['url']}");
                }
            } catch (\Throwable) {
                $this->addLog(LogLevel::Warning, "Vidéo non importée: {$item['url']}");
            }
        }
    }

    /**
     * Décode la valeur `videos` en liste d'items `{url, title}`. Accepte : string
     * JSON `[{"url","title"}]` (format prépa PrestaShop), string JSON `["url", ...]`,
     * CSV d'URLs, ou array déjà décodé (strings ou objets). Le titre est optionnel.
     *
     * @return array<int, array{url: string, title: ?string}>
     */
    private function decodeVideos(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                $raw = is_array($decoded) ? $decoded : array_map('trim', explode(',', $trimmed));
            } else {
                $raw = array_map('trim', explode(',', $trimmed));
            }
        }
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (is_array($entry)) {
                $url = trim((string) ($entry['url'] ?? ''));
                $title = isset($entry['title']) && $entry['title'] !== '' ? (string) $entry['title'] : null;
            } else {
                $url = trim((string) $entry);
                $title = null;
            }
            if ($url !== '') {
                $items[] = ['url' => $url, 'title' => $title];
            }
        }

        return $items;
    }

    /**
     * Lie une liste de médias (bibliothèque) à une entité via `pko_mediables`,
     * en append idempotent (contrainte UNIQUE media_id+type+id+group).
     *
     * @param  array<int, int>  $mediaIds
     */
    private function linkMediaToEntity(Product $product, array $mediaIds, string $group): void
    {
        $type = $product::class;
        $base = DB::table('pko_mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id', $product->id)
            ->where('mediagroup', $group);

        $existing = (clone $base)->pluck('media_id')->map(fn ($v): int => (int) $v)->all();
        $position = (int) (clone $base)->max('position');
        $now = now();

        foreach ($mediaIds as $mediaId) {
            if (in_array($mediaId, $existing, true)) {
                continue;
            }
            DB::table('pko_mediables')->insertOrIgnore([
                'media_id' => $mediaId,
                'mediable_type' => $type,
                'mediable_id' => $product->id,
                'mediagroup' => $group,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $existing[] = $mediaId;
        }
    }

    /**
     * Remplace les médias d'un groupe par la liste fournie, dans l'ordre : les
     * liens absents de la liste sont retirés (les fichiers restent en
     * médiathèque), les autres créés ou repositionnés.
     *
     * @param  array<int, int>  $mediaIds
     */
    private function replaceEntityMedia(Product $product, array $mediaIds, string $group): void
    {
        $type = $product::class;
        $base = DB::table('pko_mediables')
            ->where('mediable_type', $type)
            ->where('mediable_id', $product->id)
            ->where('mediagroup', $group);

        $removed = (clone $base)->whereNotIn('media_id', $mediaIds)->delete();
        if ($removed > 0) {
            $this->addLog(LogLevel::Info, "{$removed} image(s) existante(s) retirée(s) de la galerie (remplacement demandé)");
        }

        $existing = (clone $base)->pluck('media_id')->map(fn ($v): int => (int) $v)->all();
        $now = now();

        foreach ($mediaIds as $index => $mediaId) {
            if (in_array($mediaId, $existing, true)) {
                (clone $base)->where('media_id', $mediaId)->update(['position' => $index + 1, 'updated_at' => $now]);

                continue;
            }
            DB::table('pko_mediables')->insertOrIgnore([
                'media_id' => $mediaId,
                'mediable_type' => $type,
                'mediable_id' => $product->id,
                'mediagroup' => $group,
                'position' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Décode la valeur `documents` (JSON string issu de multiline_aggregate
     * json_array, ou array déjà décodé) en liste d'items `{type,url,name}`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function decodeDocuments(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            $raw,
            static fn ($i): bool => is_array($i) && ! empty($i['url']),
        ));
    }

    /**
     * Résout (ou crée) une catégorie de document depuis son type (ex. NOTICE),
     * mis en cache par instance. Retourne null si le package n'est pas dispo.
     */
    private function resolveDocumentCategoryId(string $type): ?int
    {
        $handle = Str::slug($type);
        if ($handle === '' || ! class_exists(DocumentCategory::class)) {
            return null;
        }
        if (isset($this->documentCategoryCache[$handle])) {
            return $this->documentCategoryCache[$handle];
        }

        $category = DocumentCategory::query()->firstOrCreate(
            ['handle' => $handle],
            ['label' => $type !== '' ? $type : $handle, 'sort_order' => 0],
        );

        return $this->documentCategoryCache[$handle] = (int) $category->id;
    }

    /**
     * Normalise une valeur images (string CSV, JSON, array) en liste d'URLs http(s).
     *
     * @return array<int, string>
     */
    private function normaliseUrlList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if (str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true);
                $raw = is_array($decoded) ? $decoded : explode(',', $trimmed);
            } else {
                $raw = explode(',', $trimmed);
            }
        }
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', array_map('strval', $raw)),
            static fn (string $u): bool => $u !== '' && filter_var($u, FILTER_VALIDATE_URL) !== false,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildAttributeData(array $data, Collection|\ArrayAccess|null $existing = null): ?Collection
    {
        $lang = $this->defaultLanguage();

        $existingArr = $existing instanceof Collection
            ? $existing->all()
            : ($existing instanceof \ArrayAccess ? iterator_to_array($existing) : []);

        $fields = collect($existingArr);

        $translateIf = function (string $key, Collection $fields) use ($data, $lang): Collection {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                return $fields;
            }
            /** @var TranslatedText|null $current */
            $current = $fields->get($key);
            $values = $current?->getValue() ?? [];
            $values[$lang] = (string) $data[$key];

            return $fields->put($key, new TranslatedText(collect($values)));
        };

        foreach (['name', 'description', 'description_short', 'meta_title', 'meta_description', 'meta_keywords'] as $k) {
            $fields = $translateIf($k, $fields);
        }

        if (isset($data['url_key']) && $data['url_key'] !== '') {
            $fields = $fields->put('url', new Text((string) $data['url_key']));
        }

        return $fields->isEmpty() ? null : $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dimensions(array $data): array
    {
        $out = [];
        if (isset($data['weight_value'])) {
            $out['weight_value'] = $data['weight_value'];
            $out['weight_unit'] = 'kg';
        }
        foreach (['length', 'width', 'height'] as $axis) {
            if (isset($data[$axis.'_value'])) {
                $out[$axis.'_value'] = $data[$axis.'_value'];
                $out[$axis.'_unit'] = 'cm';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBrand(array $data): ?int
    {
        $name = isset($data['brand_name']) ? trim((string) $data['brand_name']) : '';
        if ($name === '') {
            return null;
        }
        if (isset($this->brandCache[$name])) {
            return (int) $this->brandCache[$name]->id;
        }
        $brand = Brand::query()->firstOrCreate(['name' => $name]);

        // Marque créée par l'import : préfixe par défaut s'il est libre, sinon
        // laissé vide (collision à arbitrer dans l'admin).
        if ($brand->wasRecentlyCreated && blank($brand->pko_ref_prefix)) {
            $prefix = BrandReferencePrefix::freeSuggestion($name, (int) $brand->id);
            if ($prefix !== null) {
                $brand->pko_ref_prefix = $prefix;
                $brand->save();
                $this->addLog(LogLevel::Info, "Marque « {$name} » créée avec le préfixe de référence {$prefix}");
            }
        }

        $this->brandCache[$name] = $brand;

        return (int) $brand->id;
    }

    /** Marque existante par nom, SANS la créer (rapprochement). */
    private function findBrand(string $name): ?Brand
    {
        if (isset($this->brandCache[$name])) {
            return $this->brandCache[$name];
        }

        $brand = Brand::query()->where('name', $name)->first();
        if ($brand !== null) {
            $this->brandCache[$name] = $brand;
        }

        return $brand;
    }

    /**
     * `lunar_collections` n'a pas de colonne `handle` : le handle d'une collection
     * est le slug de son URL. Un chemin de catégorie (« Accueil>Motorisations>Moteurs »,
     * format de sortie de l'IA) est résolu sur les noms réels de l'arbre.
     */
    private function findCollectionIdByHandle(string $handle): ?int
    {
        if (str_contains($handle, '>')) {
            return app(CollectionCatalog::class)->findIdByPath($handle);
        }

        $url = Url::query()
            ->where('element_type', (new \Lunar\Models\Collection)->getMorphClass())
            ->where('slug', $handle)
            ->first();

        return $url !== null
            ? (int) $url->element_id
            : app(CollectionCatalog::class)->findIdByPath($handle);
    }

    /**
     * Resolve a list of collection IDs or handles to int IDs. Unresolved handles
     * (no Collection with that handle in DB) are returned separately for warning
     * surfacing — common when an LLM emits a handle that doesn't exist yet.
     *
     * @return array{ids: array<int, int>, unresolved: array<int, string>}
     */
    private function resolveCollectionIds(mixed $value): array
    {
        $raw = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
        $ids = [];
        $unresolved = [];

        foreach ($raw as $token) {
            if (is_numeric($token)) {
                $ids[] = (int) $token;

                continue;
            }
            $handle = (string) $token;
            if ($handle === '') {
                continue;
            }
            if (isset($this->collectionHandleCache[$handle])) {
                $ids[] = $this->collectionHandleCache[$handle];

                continue;
            }
            $collectionId = $this->findCollectionIdByHandle($handle);
            if ($collectionId !== null) {
                $ids[] = $this->collectionHandleCache[$handle] = $collectionId;
            } else {
                $unresolved[] = $handle;
            }
        }

        return [
            'ids' => array_values(array_unique($ids)),
            'unresolved' => array_values(array_unique($unresolved)),
        ];
    }

    /**
     * Identify family or value handles from a `features` hash that don't match
     * any FeatureFamily / FeatureValue in DB. Returned as a flat list like
     * `["family:unknown_family", "value:unknown_family.unknown_value"]` for
     * easy filter/sort in logs.
     *
     * @param  array<string, array<int, string>>  $features
     * @return array<int, string>
     */
    private function findUnresolvedFeatures(array $features): array
    {
        if ($features === [] || ! class_exists(FeatureFamily::class)) {
            return [];
        }

        $families = FeatureFamily::query()
            ->whereIn('handle', array_keys($features))
            ->with('values:id,feature_family_id,handle')
            ->get()
            ->keyBy('handle');

        $unresolved = [];

        foreach ($features as $familyHandle => $valueHandles) {
            $family = $families->get($familyHandle);
            if ($family === null) {
                $unresolved[] = "family:{$familyHandle}";

                continue;
            }
            $known = $family->values->pluck('handle')->all();
            foreach ((array) $valueHandles as $value) {
                $value = (string) $value;
                if ($value !== '' && ! in_array($value, $known, true)) {
                    $unresolved[] = "value:{$familyHandle}.{$value}";
                }
            }
        }

        return $unresolved;
    }

    /**
     * @param  array{collections: array<int, string>, features: array<int, string>}  $unresolved
     */
    private function logUnresolvedHandles(StagingRecord $record, string $sku, array $unresolved): void
    {
        if ($unresolved['collections'] === [] && $unresolved['features'] === []) {
            return;
        }

        $parts = [];
        if ($unresolved['collections'] !== []) {
            $parts[] = count($unresolved['collections']).' collection(s) : '.implode(' ; ', $unresolved['collections']);
        }
        if ($unresolved['features'] !== []) {
            $parts[] = count($unresolved['features']).' feature(s) : '.implode(' ; ', array_map(fn (string $f): string => mb_strimwidth($f, 0, 80, '…'), $unresolved['features']));
        }

        $message = "[{$sku}] introuvable(s) sur le site, ignoré(s) — ".implode(' — ', $parts);
        $context = array_filter($unresolved, static fn (array $v) => $v !== []);

        $this->logWarning($record, $message, $context);
    }

    /**
     * @param  array<string, string>  $warnings  clé source → valeur brute non parsable
     */
    private function logNumericWarnings(StagingRecord $record, array $warnings): void
    {
        if ($warnings === []) {
            return;
        }

        $fields = implode(', ', array_map(
            static fn (string $key, string $raw): string => "{$key} « {$raw} »",
            array_keys($warnings),
            $warnings,
        ));

        $this->logWarning($record, "Valeur(s) numérique(s) illisible(s), champ(s) ignoré(s) : {$fields}.", $warnings);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWarning(StagingRecord $record, string $message, array $context): void
    {
        if ($this->jobId !== 0) {
            $this->addLog(LogLevel::Warning, $message, $context);
        } else {
            ImportLog::query()->create([
                'import_job_id' => $record->import_job_id,
                'phase' => LogPhase::Import,
                'row_number' => $record->row_number,
                'level' => LogLevel::Warning,
                'message' => $message,
                'context' => $context,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function addLog(LogLevel $level, string $message, ?array $context = null): void
    {
        if ($this->jobId === 0) {
            return;
        }

        $this->pendingLogs[] = ImportLog::row(
            $this->jobId,
            LogPhase::Import,
            $level,
            $message,
            $this->currentRowNumber ?: null,
            $context,
        );
    }

    private function flushPendingLogs(): void
    {
        if ($this->pendingLogs === []) {
            return;
        }

        DB::table('pko_ai_importer_logs')->insert($this->pendingLogs);
        $this->pendingLogs = [];
    }

    /**
     * Écrit LE prix de base de la variante : devise par défaut, sans groupe
     * client, palier de quantité 1. Il n'en existe qu'un : la ligne en place est
     * mise à jour, jamais doublée.
     *
     * Robustesse sur l'existant — sont reconnues comme prix de base, puis
     * fusionnées en une seule ligne :
     *  - une ligne au palier 0 ou 1 (anciennes données) ;
     *  - une ligne « fantôme » dont `priceable_type` est le FQCN au lieu de
     *    l'alias morph de Lunar (invisible de l'admin et du storefront) ;
     *  - des doublons stricts (seeders rejoués, anciens imports).
     * Sans cela, `updateOrCreate` mettait à jour une des lignes et laissait
     * l'ancien tarif à côté, ou créait un tarif supplémentaire.
     *
     * Les prix par groupe client et par palier > 1 ne sont jamais touchés.
     *
     * `$writeCompare` = false : le prix barré existant est conservé (colonne
     * absente ou vide dans la source → rien n'est écrit, cf. NumberParser).
     */
    private function upsertPrice(ProductVariant $variant, int $priceCents, ?int $comparePriceCents, bool $writeCompare = true): void
    {
        $morph = $variant->getMorphClass();

        $rows = Price::query()
            ->whereIn('priceable_type', array_values(array_unique([$morph, ProductVariant::class, $variant::class])))
            ->where('priceable_id', $variant->id)
            ->where('currency_id', $this->defaultCurrencyId())
            ->whereNull('customer_group_id')
            ->where('min_quantity', '<=', 1)
            ->orderBy('id')
            ->get()
            // La ligne correctement aliasée d'abord : c'est elle que l'app lit.
            ->sortBy(fn (Price $price): int => $price->getRawOriginal('priceable_type') === $morph ? 0 : 1)
            ->values();

        $values = array_filter([
            'price' => $priceCents,
            'compare_price' => $comparePriceCents,
        ], static fn (string $column): bool => $column === 'price' || $writeCompare, ARRAY_FILTER_USE_KEY);

        /** @var Price|null $keep */
        $keep = $rows->shift();

        if ($keep === null) {
            // Via la relation morphMany : elle pose l'ALIAS morph (`product_variant`).
            $variant->prices()->create([
                'currency_id' => $this->defaultCurrencyId(),
                'customer_group_id' => null,
                'min_quantity' => 1,
                ...$values,
            ]);

            return;
        }

        $keep->update([...$values, 'priceable_type' => $morph, 'min_quantity' => 1]);

        if ($rows->isNotEmpty()) {
            Price::query()->whereKey($rows->modelKeys())->delete();
            $this->addLog(LogLevel::Warning, $rows->count().' tarif(s) de base en doublon supprimé(s) — un seul prix de base conservé.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProductTypeId(array $data): int
    {
        $handle = isset($data['product_type_handle']) ? (string) $data['product_type_handle'] : '';
        if ($handle === '') {
            return $this->defaultProductTypeId();
        }
        if (isset($this->productTypeCache[$handle])) {
            return $this->productTypeCache[$handle];
        }
        $id = (int) (ProductType::query()->where('handle', $handle)->value('id') ?? $this->defaultProductTypeId());

        return $this->productTypeCache[$handle] = $id;
    }

    /**
     * Le nom est le seul identifiant lisible d'une TaxClass (pas de colonne
     * `handle` côté Lunar). Une valeur inconnue retombe sur la classe par
     * défaut — jamais sur la « première » classe : c'est ce repli qui avait
     * rattaché des variantes à une classe sans taux, donc vendues sans TVA.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveTaxClassId(StagingRecord $record, array $data): int
    {
        $handle = isset($data['tax_class_handle']) ? trim((string) $data['tax_class_handle']) : '';
        if ($handle === '') {
            return $this->defaultTaxClassId();
        }
        $key = mb_strtolower($handle);
        if (isset($this->taxClassCache[$key])) {
            return $this->taxClassCache[$key];
        }

        $id = TaxClass::query()->whereRaw('LOWER(name) = ?', [$key])->value('id');
        if ($id === null) {
            $this->logWarning(
                $record,
                "Classe de taxe « {$handle} » introuvable, classe par défaut appliquée.",
                ['tax_class_handle' => $handle],
            );
        }

        return $this->taxClassCache[$key] = $id !== null ? (int) $id : $this->defaultTaxClassId();
    }

    private function defaultProductTypeId(): int
    {
        return $this->defaultProductTypeId ??= (int) (ProductType::query()->orderBy('id')->value('id') ?? throw new \RuntimeException('No ProductType exists — seed at least one.'));
    }

    private function defaultTaxClassId(): int
    {
        return $this->defaultTaxClassId ??= (int) (TaxClass::query()->where('default', true)->value('id')
            ?? throw new \RuntimeException('Aucune TaxClass par défaut — en marquer une `default` (TVA 20 %).'));
    }

    private function defaultCurrencyId(): int
    {
        return $this->defaultCurrencyId ??= (int) (Currency::query()->where('default', true)->value('id')
            ?? Currency::query()->orderBy('id')->value('id')
            ?? throw new \RuntimeException('No Currency exists.'));
    }

    private function defaultLanguage(): string
    {
        return $this->defaultLanguage ??= (string) (Language::query()->where('default', true)->value('code')
            ?? Language::query()->orderBy('id')->value('code')
            ?? 'fr');
    }

    private function markError(StagingRecord $record, string $message): void
    {
        $record->update([
            'status' => StagingStatus::Error,
            'error_message' => $message,
        ]);
    }

    /**
     * Tolerance layer for PrestaShop-flavoured config JSON.
     *
     * Maps legacy keys to the canonical writer contract. The canonical key always
     * wins when both are present. Conversions (€→cents, dimension axes) are
     * applied only when the canonical target is empty.
     *
     * Une valeur legacy vide ou illisible ne produit AUCUNE clé canonique (elle
     * n'est jamais convertie en 0) ; les illisibles sont reportées dans `$warnings`.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $warnings  clé source → valeur brute non parsable
     * @return array<string, mixed>
     */
    public static function normalizeLegacyKeys(array $data, array &$warnings = []): array
    {
        foreach (self::LEGACY_ALIASES as $legacy => $canonical) {
            if (! array_key_exists($legacy, $data)) {
                continue;
            }
            if (! array_key_exists($canonical, $data) || $data[$canonical] === null || $data[$canonical] === '') {
                $data[$canonical] = $data[$legacy];
            }
            unset($data[$legacy]);
        }

        // Modèle « dont » : price_tex PrestaShop est HORS éco-taxe. On ne l'ajoute
        // que si le prix provient de cet alias (price_cents canonique absent) : un
        // price_cents fourni directement est réputé inclure déjà l'éco-taxe.
        $priceFromPriceTex = array_key_exists('price_tex', $data)
            && NumberParser::isBlank($data['price_cents'] ?? null)
            && ! NumberParser::isBlank($data['price_tex']);
        // Valeurs euros brutes, avant conversion : quand prix ET éco-taxe arrivent
        // en euros, on arrondit la somme (round((price_tex + ecotax) × 100)) plutôt
        // que d'additionner deux montants déjà arrondis.
        $priceTexEuros = $priceFromPriceTex ? NumberParser::toFloat($data['price_tex']) : null;
        $ecotaxEuros = $priceFromPriceTex
            && array_key_exists('ecotax', $data)
            && NumberParser::isBlank($data['ecotax_cents'] ?? null)
                ? NumberParser::toFloat($data['ecotax'])
                : null;

        // price_tex (prix HT en euros) → price_cents ; wholesale_price (prix d'achat
        // en euros) → cost_price_cents ; ecotax (euros) → ecotax_cents.
        // Vide → rien ; illisible → warning, rien.
        foreach (self::LEGACY_EURO_ALIASES as $legacy => $canonical) {
            if (! array_key_exists($legacy, $data)) {
                continue;
            }
            if (NumberParser::isBlank($data[$canonical] ?? null) && ! NumberParser::isBlank($data[$legacy])) {
                $cents = NumberParser::eurosToCents($data[$legacy]);
                if ($cents !== null) {
                    $data[$canonical] = $cents;
                } else {
                    $warnings[$legacy] = self::stringify($data[$legacy]);
                }
            }
            unset($data[$legacy]);
        }

        if ($priceFromPriceTex && is_int($data['price_cents'] ?? null)) {
            if ($priceTexEuros !== null && $ecotaxEuros !== null) {
                if ($ecotaxEuros > 0) {
                    $data['price_cents'] = (int) round(($priceTexEuros + $ecotaxEuros) * 100);
                }

                return $data;
            }
            $ecotaxCents = NumberParser::isBlank($data['ecotax_cents'] ?? null)
                ? null
                : NumberParser::toInt($data['ecotax_cents']);
            if ($ecotaxCents !== null && $ecotaxCents > 0) {
                $data['price_cents'] += $ecotaxCents;
            }
        }

        return $data;
    }

    /**
     * Normalise les champs numériques canoniques en int/float. Une valeur vide
     * est retirée (le champ n'est pas écrit, la valeur en base est conservée) ;
     * une valeur illisible est retirée ET reportée dans `$warnings`.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $warnings
     * @return array<string, mixed>
     */
    public static function sanitizeNumericFields(array $data, array &$warnings = []): array
    {
        foreach (self::NUMERIC_FIELDS as $key => $type) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $raw = $data[$key];
            unset($data[$key]);

            if (NumberParser::isBlank($raw)) {
                continue;
            }

            $value = $type === 'int' ? NumberParser::toInt($raw) : NumberParser::toFloat($raw);
            if ($value === null) {
                $warnings[$key] = self::stringify($raw);

                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : (string) json_encode($value);
    }
}
