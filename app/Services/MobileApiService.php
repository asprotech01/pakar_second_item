<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class MobileApiService
{
    private const DEVICE_FIELDS = [
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

    /**
     * @return list<array{
     *     mobile_api_id: ?string,
     *     match_certainty: mixed,
     *     name: mixed,
     *     manufacturer_name: mixed,
     *     device_type: mixed,
     *     model_numbers: mixed,
     *     colors: mixed,
     *     storage: mixed,
     *     screen_resolution: mixed,
     *     weight: mixed,
     *     thickness: mixed,
     *     release_date: mixed,
     *     camera: mixed,
     *     battery_capacity: mixed,
     *     hardware: mixed,
     *     image_url: mixed
     * }>
     */
    public function searchDevices(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Device name must not be empty.');
        }

        $baseUrl = config('services.mobile_api.base_url');
        $apiKey = config('services.mobile_api.key');

        if (! is_string($baseUrl) || trim($baseUrl) === '' || ! is_string($apiKey) || trim($apiKey) === '') {
            throw new LogicException('MobileAPI base URL and API key must be configured.');
        }

        $response = Http::withToken($apiKey)
            ->connectTimeout(3)
            ->timeout(10)
            ->get(rtrim($baseUrl, '/').'/devices/search/', ['name' => $name])
            ->throw();

        $devices = $response->json('devices');

        if (! is_array($devices) || ! array_is_list($devices)) {
            throw new UnexpectedValueException('MobileAPI returned an invalid device search response.');
        }

        return array_map(function (mixed $device) use ($baseUrl): array {
            if (! is_array($device)) {
                throw new UnexpectedValueException('MobileAPI returned an invalid device record.');
            }

            return $this->mapApiDevice($device, rtrim($baseUrl, '/'));
        }, $devices);
    }

    /** @param array<string, mixed> $deviceData */
    public function saveDevice(array $deviceData): Device
    {
        $mobileApiId = $deviceData['mobile_api_id'] ?? null;
        $name = $deviceData['name'] ?? null;

        if ((! is_string($mobileApiId) && ! is_int($mobileApiId)) || trim((string) $mobileApiId) === '') {
            throw new InvalidArgumentException('A MobileAPI device ID is required.');
        }

        if (! is_string($name) || trim($name) === '') {
            throw new InvalidArgumentException('A device name is required.');
        }

        $attributes = array_intersect_key($deviceData, array_flip(self::DEVICE_FIELDS));
        $attributes['mobile_api_id'] = (string) $mobileApiId;

        return Device::updateOrCreate(
            ['mobile_api_id' => (string) $mobileApiId],
            $attributes
        );
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array{
     *     mobile_api_id: ?string,
     *     match_certainty: mixed,
     *     name: mixed,
     *     manufacturer_name: mixed,
     *     device_type: mixed,
     *     model_numbers: mixed,
     *     colors: mixed,
     *     storage: mixed,
     *     screen_resolution: mixed,
     *     weight: mixed,
     *     thickness: mixed,
     *     release_date: mixed,
     *     camera: mixed,
     *     battery_capacity: mixed,
     *     hardware: mixed,
     *     image_url: mixed
     * }
     */
    private function mapApiDevice(array $device, string $baseUrl): array
    {
        $storage = $device['storage'] ?? null;

        if (! $this->hasStorage($storage)) {
            $memory = $device['memory'] ?? $this->memorySpecifications(
                $baseUrl,
                $device['id'] ?? null
            );
            $storage = $this->storageFromMemoryInternal(
                is_array($memory) ? ($memory['internal'] ?? null) : null
            );
        }

        $mappedDevice = [
            'mobile_api_id' => isset($device['id']) && (is_string($device['id']) || is_int($device['id']))
                ? (string) $device['id']
                : null,
            'match_certainty' => $device['match_certainty'] ?? null,
        ];

        foreach (self::DEVICE_FIELDS as $field) {
            $mappedDevice[$field] = $field === 'storage'
                ? $storage
                : ($device[$field] ?? null);
        }

        return $mappedDevice;
    }

    private function hasStorage(mixed $storage): bool
    {
        if (is_string($storage)) {
            return trim($storage) !== '';
        }

        if (! is_array($storage)) {
            return false;
        }

        return collect($storage)
            ->flatten()
            ->contains(fn ($value): bool => (is_string($value) && trim($value) !== '') || is_numeric($value));
    }

    /**
     * @return array<string, mixed>
     */
    private function memorySpecifications(string $baseUrl, mixed $mobileApiId): array
    {
        if (! is_string($mobileApiId) && ! is_int($mobileApiId)) {
            return [];
        }

        $response = Http::withToken(config('services.mobile_api.key'))
            ->connectTimeout(3)
            ->timeout(10)
            ->get($baseUrl.'/devices/'.$mobileApiId.'/memory/')
            ->throw();
        $memory = $response->json();

        if (! is_array($memory)) {
            throw new UnexpectedValueException('MobileAPI returned an invalid device memory response.');
        }

        return $memory;
    }

    /**
     * @return list<string>
     */
    private function storageFromMemoryInternal(mixed $internal): array
    {
        if (! is_string($internal) || trim($internal) === '') {
            return [];
        }

        $withoutRamCapacities = preg_replace(
            '/\b\d+(?:\.\d+)?\s*(?:GB|TB)\s*RAM\b/i',
            '',
            $internal
        );

        if (! is_string($withoutRamCapacities)) {
            throw new UnexpectedValueException('MobileAPI returned an invalid memory specification.');
        }

        preg_match_all(
            '/(?<![\pL\d])(\d+(?:\.\d+)?)\s*(GB|TB)(?![\pL\d])/iu',
            $withoutRamCapacities,
            $matches,
            PREG_SET_ORDER
        );

        $storageVariants = [];

        foreach ($matches as $match) {
            $storageVariant = $match[1].strtoupper($match[2]);
            $storageVariants[$storageVariant] = $storageVariant;
        }

        return array_values($storageVariants);
    }
}
