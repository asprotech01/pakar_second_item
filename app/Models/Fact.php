<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fact extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_conclusion', 'classification', 'risk_level',
        'risk_weight', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_conclusion' => 'boolean',
            'risk_weight' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function questionOptions(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function ruleConditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class);
    }

    public function conclusionRules(): HasMany
    {
        return $this->hasMany(Rule::class, 'conclusion_fact_id');
    }
}
