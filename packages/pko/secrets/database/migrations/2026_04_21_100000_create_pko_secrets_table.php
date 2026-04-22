<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pko_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64);
            $table->string('key', 128);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['module', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pko_secrets');
    }
};
