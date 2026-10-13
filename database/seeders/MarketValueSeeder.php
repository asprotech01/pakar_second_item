<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\MarketValue;
use Illuminate\Database\Seeder;

class MarketValueSeeder extends Seeder
{
    public function run(): void
    {
        $marketValues = [
            '10774' => [
                [
                    'name' => 'Galaxy S25+',
                    'storage_variant' => '256GB',
                    'market_price_min' => 7000000,
                    'market_price_max' => 9000000,
                    'source' => 'Estimasi indikatif, belum diverifikasi terhadap listing terkini',
                ],
                [
                    'name' => 'Galaxy S25+',
                    'storage_variant' => '512GB',
                    'market_price_min' => 8000000,
                    'market_price_max' => 11000000,
                    'source' => 'Estimasi indikatif, belum diverifikasi terhadap listing terkini',
                ],
            ],
            '1748' => [
                [
                    'name' => '17 Pro Max',
                    'storage_variant' => '512GB',
                    'market_price_min' => 20000000,
                    'market_price_max' => 24000000,
                    'source' => 'Estimasi indikatif, belum diverifikasi terhadap listing terkini',
                ],
                [
                    'name' => '17 Pro Max',
                    'storage_variant' => '1TB',
                    'market_price_min' => 23000000,
                    'market_price_max' => 27000000,
                    'source' => 'Estimasi indikatif, belum diverifikasi terhadap listing terkini',
                ],
            ],
            '753' => [
                'name' => 'iPhone 13',
                'storage_variant' => '128GB',
                'market_price_min' => 6000000,
                'market_price_max' => 8100000,
                'source' => 'OLX Indonesia; GalaxyITE',
            ],
            '754' => [
                'name' => 'iPhone 13 Pro Max',
                'storage_variant' => '128GB',
                'market_price_min' => 8400000,
                'market_price_max' => 10690000,
                'source' => 'Shopee Indonesia',
            ],
            '756' => [
                'name' => 'iPhone 14',
                'storage_variant' => '128GB',
                'market_price_min' => 5500000,
                'market_price_max' => 7800000,
                'source' => 'GalaxyITE; iSecondphone',
            ],
            '757' => [
                'name' => 'iPhone 15',
                'storage_variant' => '128GB',
                'market_price_min' => 7400000,
                'market_price_max' => 10250000,
                'source' => 'GalaxyITE; OLX Indonesia',
            ],
            '38' => [
                'name' => 'iPhone 15 Pro Max',
                'storage_variant' => '256GB',
                'market_price_min' => 14300000,
                'market_price_max' => 19000000,
                'source' => 'Shopee Indonesia; Kumparan',
            ],
            '11743' => [
                'name' => 'Galaxy S23',
                'storage_variant' => '256GB',
                'market_price_min' => 6000000,
                'market_price_max' => 6500000,
                'source' => 'OLX Indonesia',
            ],
            '576' => [
                'name' => 'Galaxy S24',
                'storage_variant' => '256GB',
                'market_price_min' => 7575000,
                'market_price_max' => 8800000,
                'source' => 'Shopee Indonesia; OLX Indonesia',
            ],
            '493' => [
                'name' => 'Redmi Note 13',
                'storage_variant' => '256GB',
                'market_price_min' => 2389500,
                'market_price_max' => 2950000,
                'source' => 'Shopee Indonesia; OLX Indonesia',
            ],
            '448' => [
                'name' => 'Reno11',
                'storage_variant' => '256GB',
                'market_price_min' => 3250000,
                'market_price_max' => 4500000,
                'source' => 'OLX Indonesia',
            ],
        ];
        $legacyStorageVariants = [
            '11743' => ['8/256GB'],
            '576' => ['8/256GB'],
            '493' => ['8/256GB'],
            '448' => ['8/256GB'],
        ];
        $missingDevices = [];

        foreach ($marketValues as $mobileApiId => $deviceMarketValues) {
            $device = Device::query()->where('mobile_api_id', $mobileApiId)->first();

            if ($device === null) {
                $missingDevices[] = $deviceMarketValues[0]['name'] ?? $deviceMarketValues['name'];

                continue;
            }

            foreach (array_is_list($deviceMarketValues) ? $deviceMarketValues : [$deviceMarketValues] as $marketValue) {
                MarketValue::query()->updateOrCreate(
                    [
                        'device_id' => $device->id,
                        'storage_variant' => $marketValue['storage_variant'],
                        'condition' => 'baik',
                    ],
                    [
                        'market_price_min' => $marketValue['market_price_min'],
                        'market_price_max' => $marketValue['market_price_max'],
                        'source' => 'Estimasi pasar; bukan harga transaksi pasti. Sumber: '.$marketValue['source'],
                        'observed_at' => '2026-10-08',
                    ]
                );

            }

            if (isset($legacyStorageVariants[(string) $mobileApiId])) {
                MarketValue::query()
                    ->where('device_id', $device->id)
                    ->whereIn('storage_variant', $legacyStorageVariants[(string) $mobileApiId])
                    ->where('condition', 'baik')
                    ->delete();
            }
        }

        if ($missingDevices !== [] && $this->command !== null) {
            $this->command->warn(
                'Market values skipped; add catalog devices first: '.implode(', ', $missingDevices)
            );
        }
    }
}
