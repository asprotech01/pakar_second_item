<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $fillable = [
        'device_type_id', 'inspection_category_id', 'question_id', 'rule_id', 'fact_id',
        'title', 'body', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'is_active' => 'boolean'];
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function inspectionCategory(): BelongsTo
    {
        return $this->belongsTo(InspectionCategory::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function fact(): BelongsTo
    {
        return $this->belongsTo(Fact::class);
    }
}
