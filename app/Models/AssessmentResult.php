<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResult extends Model
{
    protected $fillable = [
        'assessment_session_id', 'primary_conclusion_fact_id', 'classification',
        'confidence_score', 'data_completeness', 'risk_level', 'threshold_met',
        'confidence_threshold', 'completeness_threshold', 'active_rules', 'contributing_factors',
        'unverified_data', 'recommendations', 'evaluated_at', 'validated_by_expert_id', 'expert_classification',
        'expert_confidence_score', 'expert_validation_notes', 'expert_agrees', 'expert_validated_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'data_completeness' => 'float',
            'confidence_threshold' => 'float',
            'completeness_threshold' => 'float',
            'expert_confidence_score' => 'float',
            'threshold_met' => 'boolean',
            'expert_agrees' => 'boolean',
            'active_rules' => 'array',
            'contributing_factors' => 'array',
            'unverified_data' => 'array',
            'recommendations' => 'array',
            'evaluated_at' => 'datetime',
            'expert_validated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssessmentSession::class, 'assessment_session_id');
    }

    public function primaryConclusion(): BelongsTo
    {
        return $this->belongsTo(Fact::class, 'primary_conclusion_fact_id');
    }

    public function validatedByExpert(): BelongsTo
    {
        return $this->belongsTo(Expert::class, 'validated_by_expert_id');
    }
}
