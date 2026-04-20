<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unification Post + Page → table unique `pko_posts` avec `post_type_id`.
 *
 * Étapes :
 *   1. Création `pko_post_types` + seed {article, page}
 *   2. ALTER `pko_posts` : ajout post_type_id, content (JSON), seo_title, seo_description
 *   3. UPDATE posts existants → post_type = article
 *   4. INSERT pages existantes dans pko_posts → post_type = page (slugs dédupliqués si collision)
 *   5. DROP `pko_pages`
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. pko_post_types ---
        Schema::create('pko_post_types', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('handle', 64)->unique();
            $table->string('url_segment', 64)->unique();
            $table->string('layout')->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('pko_post_types')->insert([
            [
                'label' => 'Article',
                'handle' => 'article',
                'url_segment' => 'article',
                'layout' => null,
                'icon' => 'heroicon-o-newspaper',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Page',
                'handle' => 'page',
                'url_segment' => 'page',
                'layout' => null,
                'icon' => 'heroicon-o-document-text',
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $articleTypeId = (int) DB::table('pko_post_types')->where('handle', 'article')->value('id');
        $pageTypeId = (int) DB::table('pko_post_types')->where('handle', 'page')->value('id');

        // --- 2. ALTER pko_posts : ajout colonnes ---
        Schema::table('pko_posts', function (Blueprint $table): void {
            $table->foreignId('post_type_id')
                ->after('id')
                ->nullable()
                ->constrained('pko_post_types')
                ->restrictOnDelete();
            $table->json('content')->nullable()->after('body');
            $table->string('seo_title')->nullable()->after('content');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
        });

        // --- 3. Posts existants → type article ---
        DB::table('pko_posts')->update(['post_type_id' => $articleTypeId]);

        // Rendre post_type_id NOT NULL maintenant que tout est rempli (raw SQL pour éviter
        // la dépendance à doctrine/dbal sur change())
        DB::statement('ALTER TABLE pko_posts MODIFY post_type_id BIGINT UNSIGNED NOT NULL');

        // Slug unique relatif au post_type (pas global)
        Schema::table('pko_posts', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['post_type_id', 'slug']);
        });

        // --- 4. Migrer pko_pages → pko_posts (post_type = page) ---
        $pages = DB::table('pko_pages')->get();
        foreach ($pages as $page) {
            // Si un slug existe déjà dans pko_posts pour un autre type, on conserve.
            // La contrainte unique porte sur (post_type_id, slug) donc pas de collision inter-types.
            DB::table('pko_posts')->insert([
                'post_type_id' => $pageTypeId,
                'slug' => $page->slug,
                'title' => $page->title,
                'cover_url' => null,
                'excerpt' => null,
                'body' => $page->body,
                'content' => null,
                'status' => $page->status,
                'published_at' => null,
                'seo_title' => null,
                'seo_description' => null,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);
        }

        // --- 5. Drop pko_pages ---
        Schema::dropIfExists('pko_pages');
    }

    public function down(): void
    {
        // Recréer pko_pages
        Schema::create('pko_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('status', 16)->default('published')->index();
            $table->timestamps();
        });

        // Récupérer le post_type 'page' (s'il existe encore)
        $pageTypeId = DB::table('pko_post_types')->where('handle', 'page')->value('id');
        if ($pageTypeId !== null) {
            $pagePosts = DB::table('pko_posts')->where('post_type_id', $pageTypeId)->get();
            foreach ($pagePosts as $p) {
                DB::table('pko_pages')->insert([
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'body' => $p->body,
                    'status' => $p->status,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ]);
            }
            DB::table('pko_posts')->where('post_type_id', $pageTypeId)->delete();
        }

        Schema::table('pko_posts', function (Blueprint $table): void {
            $table->dropUnique(['post_type_id', 'slug']);
            $table->unique(['slug']);
            $table->dropForeign(['post_type_id']);
            $table->dropColumn(['post_type_id', 'content', 'seo_title', 'seo_description']);
        });

        Schema::dropIfExists('pko_post_types');
    }
};
