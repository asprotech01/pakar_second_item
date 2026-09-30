<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expert extends Model
{
    protected $fillable = ['name', 'email', 'affiliation', 'credentials', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'rule_experts')
            ->withPivot(['certainty_factor', 'notes', 'validated_at'])
            ->withTimestamps();
    }

    public function validatedResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class, 'validated_by_expert_id');
    }
}
