<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('storage_variant')->nullable();
            $table->string('condition')->nullable();
            $table->decimal('market_price_min', 15, 2);
            $table->decimal('market_price_max', 15, 2);
            $table->string('source')->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'storage_variant', 'condition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_values');
    }
};
