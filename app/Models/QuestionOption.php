<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id', 'fact_id', 'code', 'label', 'value', 'certainty_factor',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'certainty_factor' => 'float',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(Fact::class);
    }

    public function assessmentAnswers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }
}
