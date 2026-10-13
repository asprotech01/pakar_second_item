<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Seed the phone catalog used by the application.
     */
    public function run(): void
    {
        $devices = [
            ['10774', 'Galaxy S25+', 'Samsung', ['256GB', '512GB']],
            ['1748', '17 Pro Max', 'Apple', ['512GB', '1TB']],
            ['753', 'iPhone 13', 'Apple', ['128GB']],
            ['754', 'iPhone 13 Pro Max', 'Apple', ['128GB']],
            ['756', 'iPhone 14', 'Apple', ['128GB']],
            ['757', 'iPhone 15', 'Apple', ['128GB']],
            ['38', 'iPhone 15 Pro Max', 'Apple', ['256GB']],
            ['11743', 'Galaxy S23', 'Samsung', ['256GB']],
            ['576', 'Galaxy S24', 'Samsung', ['256GB']],
            ['493', 'Redmi Note 13', 'Xiaomi', ['256GB']],
            ['448', 'Reno11', 'OPPO', ['256GB']],
        ];

        foreach ($devices as [$mobileApiId, $name, $manufacturerName, $storage]) {
            Device::query()->updateOrCreate(
                ['mobile_api_id' => $mobileApiId],
                [
                    'name' => $name,
                    'manufacturer_name' => $manufacturerName,
                    'device_type' => 'Smartphone',
                    'storage' => $storage,
                ]
            );
        }
    }
}
