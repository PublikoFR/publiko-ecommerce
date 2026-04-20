<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pko_pages', 'pko_posts'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'content')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                // JSON column stores the page-builder tree. Legacy `body` column
                // (raw HTML) is kept for fallback when `content` is null.
                $t->json('content')->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        foreach (['pko_pages', 'pko_posts'] as $table) {
            if (Schema::hasColumn($table, 'content')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('content'));
            }
        }
    }
};
