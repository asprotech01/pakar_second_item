<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepairCost extends Model
{
    protected $fillable = [
        'diagnosis_code',
        'issue_name',
        'description',
        'minimum_cost',
        'maximum_cost',
    ];

    protected function casts(): array
    {
        return [
            'minimum_cost' => 'decimal:2',
            'maximum_cost' => 'decimal:2',
        ];
    }
}
