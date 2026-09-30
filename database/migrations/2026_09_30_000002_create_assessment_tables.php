<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->json('device_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('answer_state', 20)->default('UNKNOWN');
            $table->string('evidence_source', 30)->default('UNKNOWN');
            $table->string('observed_value')->nullable();
            $table->decimal('certainty_factor', 5, 4)->default(1.0000);
            $table->text('notes')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['assessment_session_id', 'question_id']);
            $table->index(['answer_state', 'evidence_source']);
        });

        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('primary_conclusion_fact_id')->nullable()->constrained('facts')->nullOnDelete();
            $table->string('classification')->nullable();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->decimal('data_completeness', 5, 2)->default(0);
            $table->string('risk_level', 20)->default('unknown');
            $table->boolean('threshold_met')->default(false);
            $table->decimal('confidence_threshold', 5, 4)->nullable();
            $table->decimal('completeness_threshold', 5, 4)->nullable();
            $table->json('active_rules')->nullable();
            $table->json('contributing_factors')->nullable();
            $table->json('unverified_data')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('validated_by_expert_id')->nullable()->constrained('experts')->nullOnDelete();
            $table->string('expert_classification')->nullable();
            $table->decimal('expert_confidence_score', 5, 2)->nullable();
            $table->text('expert_validation_notes')->nullable();
            $table->boolean('expert_agrees')->nullable();
            $table->timestamp('expert_validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessment_sessions');
    }
};
