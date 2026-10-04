<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rule_conditions', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_number')->default(0);
            $table->string('logical_operator', 3)->default('AND');
        });
    }

    public function down(): void
    {
        Schema::table('rule_conditions', function (Blueprint $table) {
            $table->dropColumn(['group_number', 'logical_operator']);
        });
    }
};
