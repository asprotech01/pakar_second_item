<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    protected $fillable = [
        'conclusion_fact_id', 'code', 'name', 'description', 'certainty_factor',
        'classification', 'risk_level', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'certainty_factor' => 'float',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function conclusion(): BelongsTo
    {
        return $this->belongsTo(Fact::class, 'conclusion_fact_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class)->orderBy('sequence');
    }

    public function experts(): BelongsToMany
    {
        return $this->belongsToMany(Expert::class, 'rule_experts')
            ->withPivot(['certainty_factor', 'notes', 'validated_at'])
            ->withTimestamps();
    }
}
