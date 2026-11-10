<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Type de cadeau par palier (bon d'achat | franco abaissé | BFA) et lien entre
 * un déblocage et le bon d'achat Lunar émis pour lui.
 *
 * Le cadeau libre (intitulé, description, photo) disparaît : un palier porte
 * désormais un avantage chiffré, en HT et en cents.
 *
 * Reprise des données : un intitulé du type « Bon d'achat 50€ » devient un bon
 * d'achat de 5 000 cents HT. Les autres paliers passent en bon d'achat sans
 * montant : aucun bon n'est émis tant que l'admin ne l'a pas renseigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lunarPrefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::table('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->string('gift_type', 20)->default('voucher')->after('points_required');
            $table->unsignedInteger('voucher_amount')->nullable()->after('gift_type');
            $table->unsignedInteger('franco_threshold')->nullable()->after('voucher_amount');
            $table->unsignedInteger('bfa_fixed_amount')->nullable()->after('franco_threshold');
            $table->decimal('bfa_percent', 5, 2)->nullable()->after('bfa_fixed_amount');
        });

        DB::table('pko_loyalty_tiers')->orderBy('id')->each(function (object $row): void {
            DB::table('pko_loyalty_tiers')->where('id', $row->id)->update([
                'gift_type' => 'voucher',
                'voucher_amount' => self::voucherAmountFromTitle((string) ($row->gift_title ?? '')),
            ]);
        });

        Schema::table('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->dropColumn(['gift_title', 'gift_description']);
        });

        // Photos du cadeau libre : rattachements orphelins une fois le cadeau supprimé.
        if (Schema::hasTable('pko_mediables')) {
            DB::table('pko_mediables')
                ->where('mediagroup', 'gift_image')
                ->whereIn('mediable_type', ['Pko\\Loyalty\\Models\\LoyaltyTier', 'loyalty_tier'])
                ->delete();
        }

        Schema::table('pko_loyalty_gift_history', function (Blueprint $table) use ($lunarPrefix): void {
            // Unique : un seul bon d'achat par déblocage (idempotence).
            $table->foreignId('discount_id')
                ->nullable()
                ->after('order_id')
                ->unique()
                ->constrained($lunarPrefix.'discounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pko_loyalty_gift_history', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_id');
        });

        Schema::table('pko_loyalty_tiers', function (Blueprint $table): void {
            $table->string('gift_title')->default('')->after('points_required');
            $table->text('gift_description')->nullable()->after('gift_title');
            $table->dropColumn(['gift_type', 'voucher_amount', 'franco_threshold', 'bfa_fixed_amount', 'bfa_percent']);
        });
    }

    /** « Bon d'achat 50€ », « bon d’achat de 75,50 € » → cents ; sinon null. */
    private static function voucherAmountFromTitle(string $title): ?int
    {
        if (! preg_match('/bon\s+d.achat\D*?(\d+)(?:[.,](\d{1,2}))?/iu', $title, $m)) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
};
