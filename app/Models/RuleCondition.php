<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleCondition extends Model
{
    protected $fillable = [
        'rule_id', 'fact_id', 'operator', 'expected_value', 'sequence', 'group_number', 'logical_operator',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'group_number' => 'integer',
        ];
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
