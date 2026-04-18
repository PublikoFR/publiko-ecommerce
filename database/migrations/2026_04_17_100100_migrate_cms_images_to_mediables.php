<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mde\StorefrontCms\Models\HomeOffer;
use Mde\StorefrontCms\Models\HomeSlide;
use Mde\StorefrontCms\Models\HomeTile;
use Mde\StorefrontCms\Models\Post;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

return new class extends Migration
{
    public function up(): void
    {
        // Map: [table => [urlColumn, modelClass, mediagroup]]
        $targets = [
            'mde_posts' => ['cover_url', Post::class, 'cover'],
            'mde_home_slides' => ['image_url', HomeSlide::class, 'image'],
            'mde_home_tiles' => ['image_url', HomeTile::class, 'image'],
            'mde_home_offers' => ['image_url', HomeOffer::class, 'image'],
        ];

        foreach ($targets as $table => [$col, $modelClass, $group]) {
            if (! Schema::hasColumn($table, $col)) {
                continue;
            }

            $rows = DB::table($table)->whereNotNull($col)->where($col, '<>', '')->get(['id', $col]);

            foreach ($rows as $row) {
                $url = (string) $row->{$col};
                $media = $this->findMediaByUrl($url);

                if ($media === null) {
                    Log::info('[mde_mediables] No media match for URL', [
                        'table' => $table,
                        'id' => $row->id,
                        'url' => $url,
                    ]);

                    continue;
                }

                DB::table('mde_mediables')->insertOrIgnore([
                    'media_id' => $media->id,
                    'mediable_type' => $modelClass,
                    'mediable_id' => $row->id,
                    'mediagroup' => $group,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Drop legacy *_url columns.
        foreach ($targets as $table => [$col]) {
            if (Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $t) use ($col) {
                    $t->dropColumn($col);
                });
            }
        }
    }

    public function down(): void
    {
        // Restore columns (empty) — content is lost.
        $cols = [
            'mde_posts' => 'cover_url',
            'mde_home_slides' => 'image_url',
            'mde_home_tiles' => 'image_url',
            'mde_home_offers' => 'image_url',
        ];
        foreach ($cols as $table => $col) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $t) use ($col) {
                    $t->string($col, 1024)->nullable();
                });
            }
        }

        // Purge our rows for the migrated models (best-effort).
        DB::table('mde_mediables')->whereIn('mediable_type', [
            Post::class, HomeSlide::class, HomeTile::class, HomeOffer::class,
        ])->delete();
    }

    private function findMediaByUrl(string $url): ?Media
    {
        $fileName = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        if ($fileName === '' || $fileName === '/') {
            return null;
        }

        return Media::query()->where('file_name', $fileName)->orderByDesc('id')->first();
    }
};
