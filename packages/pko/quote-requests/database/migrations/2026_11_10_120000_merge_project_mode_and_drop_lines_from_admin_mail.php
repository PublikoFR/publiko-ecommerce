<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le mode « projet » est fusionné dans la demande générale et le formulaire
 * n'a plus de lignes produit / quantité :
 *  - les demandes existantes en mode `project` passent en `general` ;
 *  - le bloc « Produits / quantités » est retiré du mail équipe stocké, s'il
 *    est encore identique au contenu par défaut (un texte retouché en admin
 *    n'est jamais modifié). La colonne `lines` reste : les anciennes demandes
 *    gardent leurs lignes, toujours visibles dans la fiche admin.
 */
return new class extends Migration
{
    private const LINES_BLOCK_HTML = '<p><strong>Produits / quantités</strong> : :lines</p>';

    public function up(): void
    {
        if (Schema::hasTable('pko_quote_requests')) {
            DB::table('pko_quote_requests')->where('mode', 'project')->update(['mode' => 'general']);
        }

        if (! Schema::hasTable('pko_mail_templates')) {
            return;
        }

        DB::table('pko_mail_templates')
            ->where('key', 'quote_request.received_admin')
            ->get(['id', 'content'])
            ->each(function (object $row): void {
                $content = json_decode((string) $row->content, true);
                if (! is_array($content) || ! isset($content['sections']) || ! is_array($content['sections'])) {
                    return;
                }

                $changed = false;
                foreach ($content['sections'] as &$section) {
                    foreach ($section['columns'] ?? [] as $c => $column) {
                        $blocks = $column['blocks'] ?? [];
                        $kept = array_values(array_filter(
                            $blocks,
                            fn ($block): bool => ! (is_array($block)
                                && ($block['type'] ?? null) === 'text'
                                && ($block['html'] ?? null) === self::LINES_BLOCK_HTML),
                        ));
                        if (count($kept) !== count($blocks)) {
                            $section['columns'][$c]['blocks'] = $kept;
                            $changed = true;
                        }
                    }
                }
                unset($section);

                if ($changed) {
                    DB::table('pko_mail_templates')->where('id', $row->id)
                        ->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        // Fusion de données : rien à défaire.
    }
};
