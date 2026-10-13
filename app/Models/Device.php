<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'mobile_api_id',
        'name',
        'manufacturer_name',
        'device_type',
        'model_numbers',
        'colors',
        'storage',
        'screen_resolution',
        'weight',
        'thickness',
        'release_date',
        'camera',
        'battery_capacity',
        'hardware',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'model_numbers' => 'json',
            'colors' => 'json',
            'storage' => 'json',
            'screen_resolution' => 'json',
            'weight' => 'json',
            'thickness' => 'json',
            'release_date' => 'json',
            'camera' => 'json',
            'battery_capacity' => 'json',
            'hardware' => 'json',
        ];
    }

    public function marketValues(): HasMany
    {
        return $this->hasMany(MarketValue::class);
    }

    /**
     * @return list<string>
     */
    public function storageVariants(): array
    {
        $storageValues = is_array($this->storage)
            ? collect($this->storage)->flatten()->all()
            : (is_string($this->storage) ? [$this->storage] : []);

        return collect($storageValues)
            ->filter(fn ($value): bool => is_string($value) || is_numeric($value))
            ->flatMap(fn ($value): array => explode(',', (string) $value))
            ->map(fn (string $variant): string => trim($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
