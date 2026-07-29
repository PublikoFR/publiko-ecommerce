<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lunar_products', 'pko_port_mode')) {
            Schema::table('lunar_products', function (Blueprint $table): void {
                $table->enum('pko_port_mode', ['inherit', 'standard', 'flat', 'free', 'quote'])
                    ->default('inherit')
                    ->after('pko_supplier_id')
                    ->nullable(false);
            });
        }

        if (! Schema::hasColumn('pko_suppliers', 'port_inclus')) {
            Schema::table('pko_suppliers', function (Blueprint $table): void {
                $table->enum('port_inclus', ['oui', 'non', 'cas_par_cas'])
                    ->default('cas_par_cas')
                    ->after('notes')
                    ->nullable(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('lunar_products', function (Blueprint $table): void {
            $table->dropColumn('pko_port_mode');
        });

        Schema::table('pko_suppliers', function (Blueprint $table): void {
            $table->dropColumn('port_inclus');
        });
    }
};
