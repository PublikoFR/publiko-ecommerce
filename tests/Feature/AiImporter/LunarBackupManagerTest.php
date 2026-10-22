<?php

declare(strict_types=1);

namespace Tests\Feature\AiImporter;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Jobs\MakeSearchable;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Lunar\Models\TaxClass;
use Pko\AiImporter\Enums\ImportStatus;
use Pko\AiImporter\Enums\LogPhase;
use Pko\AiImporter\Enums\StagingStatus;
use Pko\AiImporter\Jobs\ImportStagingToLunarJob;
use Pko\AiImporter\Models\ImportJob;
use Pko\AiImporter\Models\ImportLog;
use Pko\AiImporter\Models\StagingRecord;
use Pko\AiImporter\Services\LunarBackupManager;
use Pko\AiImporter\Services\LunarProductWriter;
use Pko\CatalogFeatures\Models\FeatureFamily;
use Pko\CatalogFeatures\Models\FeatureValue;
use Pko\LunarMediaCore\Models\Mediable;
use Pko\ProductDocuments\Models\ProductDocument;
use Pko\ProductVideos\Models\ProductVideo;
use Tests\TestCase;

/**
 * Cycle complet snapshot → import (création + mise à jour) → restore.
 */
class LunarBackupManagerTest extends TestCase
{
    use RefreshDatabase;

    private Collection $colA;

    private Collection $colB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();

        ProductType::firstOrCreate(['name' => 'Test Type']);
        TaxClass::firstOrCreate(['name' => 'Standard'], ['default' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'exchange_rate' => 1.0, 'default' => true, 'enabled' => true]);
        Language::firstOrCreate(['code' => 'fr'], ['name' => 'Français', 'default' => true]);

        $group = CollectionGroup::create(['name' => 'Catalogue', 'handle' => 'catalogue']);
        $this->colA = Collection::create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'A']))])]);
        $this->colB = Collection::create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'B']))])]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function job(array $options = []): ImportJob
    {
        return ImportJob::create([
            'input_file_path' => 'n/a',
            'status' => 'parsed',
            'import_status' => 'pending',
            'error_policy' => 'ignore',
            'options' => $options,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stage(ImportJob $job, array $data, int $row): StagingRecord
    {
        return StagingRecord::create([
            'import_job_id' => $job->id,
            'row_number' => $row,
            'data' => $data,
            'status' => StagingStatus::Pending,
        ]);
    }

    /** Produit existant avant import, écrit par le writer comme en vrai. */
    private function seedExisting(): ProductVariant
    {
        $seedJob = $this->job();
        (new LunarProductWriter)->write($this->stage($seedJob, [
            'reference' => 'EXIST-1',
            'name' => 'Nom d\'origine',
            'ean' => '3000000000017',
            'mpn' => 'MPN-OLD',
            'price_cents' => 1000,
            'cost_price_cents' => 400,
            'stock' => 10,
            'min_quantity' => 2,
            'width_value' => 5,
        ], 1));

        $variant = ProductVariant::where('sku', 'EXIST-1')->firstOrFail();
        $variant->product->collections()->sync([$this->colA->id => ['position' => 3]]);

        return $variant;
    }

    private function nameOf(Product $product): ?string
    {
        $name = $product->fresh()->attribute_data->get('name');

        return $name?->getValue()->get('fr')?->getValue();
    }

    public function test_rollback_restores_updated_and_removes_created_products(): void
    {
        $variant = $this->seedExisting();
        $product = $variant->product;

        $job = $this->job();
        $this->stage($job, [
            'reference' => 'EXIST-1',
            'name' => 'Nom importé',
            'mpn' => 'MPN-NEW',
            'price_cents' => 9999,
            'cost_price_cents' => 777,
            'stock' => 99,
            'min_quantity' => 5,
            'width_value' => 42,
            'collections' => (string) $this->colB->id,
        ], 1);
        $created = $this->stage($job, [
            'reference' => 'NEW-1',
            'name' => 'Produit créé',
            'price_cents' => 500,
            'collections' => (string) $this->colA->id,
        ], 2);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));

        $job->refresh();
        $this->assertSame(ImportStatus::Imported, $job->import_status);
        $this->assertSame('Nom importé', $this->nameOf($product));
        $newProductId = $created->refresh()->lunar_product_id;
        $this->assertNotNull($newProductId);

        app(LunarBackupManager::class)->restore($job);

        // attribute_data lisible par le cast Lunar (régression toArray()).
        $this->assertSame('Nom d\'origine', $this->nameOf($product));

        $variant->refresh();
        $this->assertSame(1000, (int) $variant->prices()->first()->price->value);
        $this->assertSame(1, $variant->prices()->count());
        $this->assertSame(10, $variant->stock);
        $this->assertSame(2, $variant->min_quantity);
        $this->assertSame('MPN-OLD', $variant->mpn);
        $this->assertSame(400, (int) $variant->pko_cost_price);
        $this->assertEquals(5, $variant->width_value->value ?? $variant->width_value);

        // Pivot : exactement l'état initial (colB ajoutée par l'import retirée).
        $pivot = $product->fresh()->collections()->get()->mapWithKeys(fn ($c) => [$c->id => (int) $c->pivot->position])->all();
        $this->assertSame([$this->colA->id => 3], $pivot);

        // Produit créé : supprimé définitivement, variante comprise.
        $this->assertNull(Product::withTrashed()->find($newProductId));
        $this->assertFalse(ProductVariant::withTrashed()->where('sku', 'NEW-1')->exists());

        // Staging remis en état importable.
        $this->assertSame(
            [StagingStatus::Pending->value],
            StagingRecord::where('import_job_id', $job->id)->pluck('status')->map(fn ($s) => $s->value)->unique()->values()->all(),
        );
        $this->assertNull($created->refresh()->lunar_product_id);
        $this->assertTrue((bool) $job->fresh()->rollback_completed);
    }

    public function test_snapshot_covers_ean_join_column(): void
    {
        $variant = $this->seedExisting();

        $job = $this->job(['join_column' => 'ean']);
        // Référence différente du SKU : seule l'EAN relie la ligne au produit.
        $this->stage($job, [
            'reference' => 'REF-FOURNISSEUR',
            'ean13' => '3000000000017',
            'price_cents' => 2500,
            'stock' => 1,
        ], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));
        $this->assertSame(2500, (int) $variant->fresh()->prices()->first()->price->value);

        app(LunarBackupManager::class)->restore($job->fresh());

        $variant->refresh();
        $this->assertSame(1000, (int) $variant->prices()->first()->price->value);
        $this->assertSame(10, $variant->stock);
    }

    public function test_price_created_by_import_on_existing_variant_is_removed(): void
    {
        $variant = $this->seedExisting();
        $variant->prices()->delete();

        $job = $this->job();
        $this->stage($job, ['reference' => 'EXIST-1', 'price_cents' => 1234], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));
        $this->assertSame(1, $variant->prices()->count());

        app(LunarBackupManager::class)->restore($job->fresh());

        $this->assertSame(0, $variant->prices()->count());
    }

    public function test_import_writes_one_import_phase_log_per_row(): void
    {
        $this->seedExisting();

        $job = $this->job();
        $this->stage($job, ['reference' => 'EXIST-1', 'price_cents' => 1234], 1);
        // Rejetée par le writer sans exception : doit compter comme une erreur.
        $this->stage($job, ['name' => 'Sans référence'], 2);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));

        $job->refresh();
        $this->assertSame(1, $job->imported_count);
        $this->assertSame(1, $job->error_count);

        $logs = ImportLog::query()->where('import_job_id', $job->id)->get();
        $this->assertTrue($logs->every(fn (ImportLog $log): bool => $log->phase === LogPhase::Import));
        $this->assertTrue($logs->contains(fn (ImportLog $log): bool => $log->row_number === 1
            && str_starts_with($log->message, 'Ligne 1/') && str_contains($log->message, 'Mis à jour')));
        $this->assertTrue($logs->contains(fn (ImportLog $log): bool => $log->row_number === 2
            && $log->level->value === 'error' && str_contains($log->message, 'reference manquante')));
    }

    public function test_collections_are_resolved_by_name_path_and_unknown_ones_only_warn(): void
    {

        $root = Collection::create(['collection_group_id' => $this->colA->collection_group_id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'Catalogue racine']))])]);
        $child = Collection::create(['collection_group_id' => $this->colA->collection_group_id, 'attribute_data' => collect(['name' => new TranslatedText(collect(['fr' => 'Éléments']))])]);
        $child->appendToNode($root)->save();

        $job = $this->job();
        $this->stage($job, [
            'reference' => 'NEW-1',
            'name' => 'Produit',
            'price_cents' => 1000,
            // Chemin de noms (sortie IA) résolu sur l'arbre réel + catégorie inconnue.
            'collections' => 'Accueil>Catalogue racine>Éléments,Accueil>Inexistante',
        ], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));

        $product = ProductVariant::where('sku', 'NEW-1')->firstOrFail()->product;
        $this->assertSame([$child->id], $product->collections()->pluck('lunar_collections.id')->all());
        $this->assertSame(0, $job->fresh()->error_count);
        $this->assertTrue(ImportLog::where('import_job_id', $job->id)->where('level', 'warning')->where('message', 'like', '%collection%')->exists());
    }

    public function test_ai_features_string_is_resolved_to_feature_values(): void
    {
        $family = FeatureFamily::create(['handle' => 'couleur-x', 'name' => 'Couleur X']);
        $value = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'rouge-x', 'name' => 'Rouge X']);

        $job = $this->job();
        $this->stage($job, [
            'reference' => 'FEAT-1',
            'name' => 'Produit',
            'price_cents' => 1000,
            'features' => 'Couleur X:Rouge X|Famille inconnue:Peu importe',
        ], 1);

        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));

        $product = ProductVariant::where('sku', 'FEAT-1')->firstOrFail()->product;
        $this->assertSame([$value->id], $product->featureValues()->pluck('pko_feature_values.id')->all());
        $this->assertSame(0, $job->fresh()->error_count);
        $this->assertTrue(ImportLog::where('import_job_id', $job->id)->where('level', 'warning')->where('message', 'like', '%feature%')->exists());
    }

    private function media(Product $owner, string $name): int
    {
        return (int) $owner->addMediaFromString("contenu {$name}")
            ->usingFileName("{$name}.pdf")
            ->toMediaCollection('test-library', 'local')
            ->id;
    }

    private function runImport(ImportJob $job): void
    {
        (new ImportStagingToLunarJob($job->id))->handle(app(LunarProductWriter::class), app(LunarBackupManager::class));
    }

    public function test_rollback_restores_tags_features_media_documents_and_videos_exactly(): void
    {
        $product = $this->seedExisting()->product;

        $tagOld = Tag::create(['value' => 'ANCIEN']);
        $tagNew = Tag::create(['value' => 'NOUVEAU']);
        $family = FeatureFamily::create(['handle' => 'couleur', 'name' => 'Couleur']);
        $fvOld = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'rouge', 'name' => 'Rouge']);
        $fvNew = FeatureValue::create(['feature_family_id' => $family->id, 'handle' => 'bleu', 'name' => 'Bleu']);
        [$mediaA, $mediaB, $mediaC] = [$this->media($product, 'a'), $this->media($product, 'b'), $this->media($product, 'c')];

        $product->tags()->sync([$tagOld->id]);
        $product->featureValues()->sync([$fvOld->id]);
        Mediable::create(['media_id' => $mediaA, 'mediable_type' => Product::modelClass(), 'mediable_id' => $product->id, 'mediagroup' => 'product', 'position' => 0]);
        $doc = ProductDocument::create(['product_id' => $product->id, 'media_id' => $mediaB, 'sort_order' => 0]);
        $video = ProductVideo::create(['product_id' => $product->id, 'url' => 'https://youtu.be/ancienne', 'provider' => 'youtube', 'title' => 'Ancienne']);

        $job = $this->job();
        $this->stage($job, ['reference' => 'EXIST-1'], 1);
        app(LunarBackupManager::class)->snapshot($job);

        // Effets d'un import : remplacement (tags, caractéristiques), ajouts,
        // modification d'un lien existant, et un lien disparu entre-temps.
        $product->tags()->sync([$tagNew->id]);
        $product->featureValues()->sync([$fvNew->id]);
        Mediable::create(['media_id' => $mediaC, 'mediable_type' => Product::modelClass(), 'mediable_id' => $product->id, 'mediagroup' => 'product', 'position' => 1]);
        $doc->update(['sort_order' => 5]);
        ProductDocument::create(['product_id' => $product->id, 'media_id' => $mediaC, 'sort_order' => 1]);
        $video->delete();
        ProductVideo::create(['product_id' => $product->id, 'url' => 'https://youtu.be/nouvelle', 'provider' => 'youtube', 'title' => 'Nouvelle']);

        app(LunarBackupManager::class)->restore($job->fresh());

        $product = $product->fresh();
        $this->assertSame([$tagOld->id], $product->tags()->pluck('lunar_tags.id')->all());
        $this->assertSame([$fvOld->id], $product->featureValues()->pluck('pko_feature_values.id')->all());
        $this->assertSame(
            [$mediaA],
            Mediable::where('mediable_type', Product::modelClass())->where('mediable_id', $product->id)->pluck('media_id')->map(fn ($id): int => (int) $id)->all(),
        );
        $docs = ProductDocument::where('product_id', $product->id)->get();
        $this->assertSame([$mediaB], $docs->pluck('media_id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, (int) $docs->first()->sort_order);
        $videos = ProductVideo::where('product_id', $product->id)->get();
        $this->assertSame([$video->id], $videos->pluck('id')->all());
        $this->assertSame('Ancienne', $videos->first()->title);
    }

    public function test_created_product_already_in_a_cart_is_set_to_draft_not_deleted(): void
    {
        $job = $this->job();
        $created = $this->stage($job, ['reference' => 'NEW-CART', 'name' => 'Déjà en panier', 'price_cents' => 500], 1);
        $this->runImport($job);

        $variant = ProductVariant::where('sku', 'NEW-CART')->firstOrFail();
        $channel = Channel::firstOrCreate(['handle' => 'webstore'], ['name' => 'Web', 'default' => true, 'url' => 'http://localhost']);
        $cart = Cart::create(['currency_id' => Currency::where('code', 'EUR')->value('id'), 'channel_id' => $channel->id]);
        CartLine::create(['cart_id' => $cart->id, 'purchasable_type' => $variant->getMorphClass(), 'purchasable_id' => $variant->id, 'quantity' => 1]);

        app(LunarBackupManager::class)->restore($job->fresh());

        $product = Product::find($variant->product_id);
        $this->assertNotNull($product);
        $this->assertSame('draft', $product->status);
        $created->refresh();
        $this->assertSame(StagingStatus::Pending, $created->status);
        $this->assertSame($product->id, $created->lunar_product_id);
        $this->assertStringContainsString('brouillon', (string) $created->error_message);
    }

    public function test_restored_products_are_reindexed(): void
    {
        $product = $this->seedExisting()->product;

        $job = $this->job();
        $this->stage($job, ['reference' => 'EXIST-1', 'name' => 'Nom importé'], 1);
        $this->runImport($job);

        config(['scout.queue' => true]);
        Queue::fake();

        app(LunarBackupManager::class)->restore($job->fresh());

        Queue::assertPushed(MakeSearchable::class, fn (MakeSearchable $job): bool => $job->models->contains('id', $product->id));
    }
}
