<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Pko\QuoteRequests\Support\LegacyQuoteLinks;
use Pko\StorefrontCms\Models\Setting;

/**
 * Migration de DONNÉES : les réglages storefront surchargés en base gardaient
 * les anciennes URLs par défaut des CTA devis (`/contact`,
 * `/pages/identifier-une-piece`). Elles pointent désormais vers le formulaire
 * unique `/demande-de-devis`. Seules ces anciennes valeurs par défaut sont
 * remplacées (cf. LegacyQuoteLinks) ; une URL personnalisée reste intacte.
 *
 * Pas de down() inverse : impossible de distinguer une valeur migrée d'une
 * valeur saisie ensuite par l'admin, on ne réécrit rien au rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pko_storefront_settings') || ! class_exists(Setting::class)) {
            return;
        }

        $changed = false;

        $changed = $this->rewrite('nav.quote_url', fn (mixed $v): mixed => LegacyQuoteLinks::navQuoteUrl($v)) || $changed;
        $changed = $this->rewrite('footer.columns', fn (mixed $v): mixed => is_array($v) ? LegacyQuoteLinks::footerColumns($v) : $v) || $changed;
        $changed = $this->rewrite('home.services', fn (mixed $v): mixed => is_array($v) ? LegacyQuoteLinks::links($v, 'title') : $v) || $changed;

        if ($changed) {
            Setting::forget();
        }
    }

    public function down(): void {}

    private function rewrite(string $key, callable $transform): bool
    {
        $row = Setting::query()->where('key', $key)->first();
        if ($row === null) {
            return false;
        }

        $new = $transform($row->value);
        if ($new === $row->value) {
            return false;
        }

        $row->value = $new;
        $row->save();

        return true;
    }
};
