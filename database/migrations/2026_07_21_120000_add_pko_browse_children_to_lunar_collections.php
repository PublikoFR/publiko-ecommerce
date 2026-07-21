<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Page de listing de catégories » : la catégorie n'affiche pas de produits
 * mais ses sous-catégories sous forme de cartes, et le menu latéral la rend
 * comme un lien simple (pas de sous-menu déroulant).
 *
 * Le drapeau cascade sur toute la branche (cf. TreeManager::toggleCollectionBrowseChildren) :
 * on l'active sur la racine, chaque descendant ayant des enfants aiguille à son
 * tour, et une feuille retombe naturellement sur son listing produits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_collections', function (Blueprint $table): void {
            $table->boolean('pko_browse_children')->default(false)->after('pko_enabled');
            $table->index('pko_browse_children');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_collections', function (Blueprint $table): void {
            $table->dropIndex(['pko_browse_children']);
            $table->dropColumn('pko_browse_children');
        });
    }
};
