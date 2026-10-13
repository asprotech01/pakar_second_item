<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_sessions', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_sessions', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
