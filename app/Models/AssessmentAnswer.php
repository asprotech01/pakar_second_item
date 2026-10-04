<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    public const STATE_KNOWN = 'KNOWN';

    public const STATE_UNKNOWN = 'UNKNOWN';

    public const STATE_NOT_APPLICABLE = 'NOT_APPLICABLE';

    public const STATE_NOT_TESTED = 'NOT_TESTED';

    public const EVIDENCE_SOURCES = [
        'SELLER',
        'DIRECT_INSPECTION',
        'SYSTEM_INFORMATION',
        'DOCUMENT',
        'USER_ESTIMATION',
        'UNKNOWN',
    ];

    protected $fillable = [
        'assessment_session_id', 'question_id', 'question_option_id', 'answered_by',
        'answer_state', 'evidence_source', 'observed_value', 'certainty_factor', 'notes', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'certainty_factor' => 'float',
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssessmentSession::class, 'assessment_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
