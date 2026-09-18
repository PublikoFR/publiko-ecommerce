<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note libre saisie par le client au checkout (consignes, précisions sur la
 * commande). Colonne dédiée plutôt que `notes` de Lunar : `notes` est reprise
 * telle quelle comme description de la facture Pennylane, où un message client
 * n'a rien à faire.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lunar_orders', 'pko_customer_notes')) {
            return;
        }

        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->text('pko_customer_notes')->nullable()->after('pko_site_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lunar_orders', 'pko_customer_notes')) {
            return;
        }

        Schema::table('lunar_orders', function (Blueprint $table): void {
            $table->dropColumn('pko_customer_notes');
        });
    }
};
