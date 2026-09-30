<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('platform', 30);
            $table->decimal('confidence_threshold', 5, 4)->default(0.6000);
            $table->decimal('completeness_threshold', 5, 4)->default(0.7000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inspection_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('facts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_conclusion')->default(false);
            $table->string('classification')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->decimal('risk_weight', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('prompt');
            $table->string('input_type', 30)->default('single_choice');
            $table->boolean('is_required')->default(true);
            $table->decimal('completeness_weight', 6, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('value')->nullable();
            $table->decimal('certainty_factor', 5, 4)->default(1.0000);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['question_id', 'code']);
        });

        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conclusion_fact_id')->constrained('facts')->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('certainty_factor', 5, 4)->default(1.0000);
            $table->string('classification')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fact_id')->constrained()->restrictOnDelete();
            $table->string('operator', 20)->default('PRESENT');
            $table->string('expected_value')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('experts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('affiliation')->nullable();
            $table->string('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rule_experts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expert_id')->constrained()->cascadeOnDelete();
            $table->decimal('certainty_factor', 5, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique(['rule_id', 'expert_id']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspection_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('rule_experts');
        Schema::dropIfExists('experts');
        Schema::dropIfExists('rule_conditions');
        Schema::dropIfExists('rules');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('facts');
        Schema::dropIfExists('inspection_categories');
        Schema::dropIfExists('device_types');
        Schema::dropIfExists('device_categories');
    }
};
