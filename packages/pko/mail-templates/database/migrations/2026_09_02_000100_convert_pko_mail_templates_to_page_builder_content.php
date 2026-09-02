<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pko\MailTemplates\Support\LegacyBlocksConverter;

/**
 * Bascule `pko_mail_templates` de la liste plate `blocks` vers l'arborescence
 * page-builder `content` ({heading, sections:[{layout, columns:[{blocks:[]}]}]}).
 *
 * Bascule franche (pas de rétrocompat) : rien n'est en prod. Les lignes déjà
 * en base (seedées ou retouchées en back-office) sont converties via
 * `LegacyBlocksConverter` avant que la colonne `blocks` ne disparaisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pko_mail_templates', function (Blueprint $table) {
            $table->json('content')->nullable()->after('subject');
        });

        DB::table('pko_mail_templates')->orderBy('id')->each(function (object $row): void {
            $blocks = json_decode((string) $row->blocks, true) ?: [];

            DB::table('pko_mail_templates')
                ->where('id', $row->id)
                ->update(['content' => json_encode(LegacyBlocksConverter::convert($blocks))]);
        });

        Schema::table('pko_mail_templates', function (Blueprint $table) {
            $table->json('content')->nullable(false)->change();
            $table->dropColumn('blocks');
        });
    }

    public function down(): void
    {
        Schema::table('pko_mail_templates', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('subject');
        });

        Schema::table('pko_mail_templates', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
