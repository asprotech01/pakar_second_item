<?php

namespace Database\Seeders;

use App\Models\RepairCost;
use Illuminate\Database\Seeder;

class RepairCostSeeder extends Seeder
{
    public function run(): void
    {
        $repairCosts = [
            ['physical_condition_issue', 'Komponen teknis', 'Pemeriksaan lanjutan diperlukan', 150000, 500000],
            ['display_condition_issue', 'Layar', 'Kerusakan visual atau sentuh', 550000, 1600000],
            ['battery_health_issue', 'Baterai', 'Risiko kesehatan baterai dan pengisian', 350000, 700000],
            ['battery_swelling_issue', 'Baterai', 'Risiko kesehatan baterai dan pengisian', 350000, 700000],
            ['charging_condition_issue', 'Port Pengisian', 'Port / charging tidak stabil', 200000, 450000],
            ['camera_condition_issue', 'Kamera', 'Kamera depan atau belakang bermasalah', 350000, 950000],
            ['audio_condition_issue', 'Audio', 'Speaker atau mikrofon tidak normal', 150000, 450000],
            ['biometric_condition_issue', 'Komponen teknis', 'Pemeriksaan lanjutan diperlukan', 150000, 500000],
            ['activation_lock_issue', 'Komponen teknis', 'Pemeriksaan lanjutan diperlukan', 150000, 500000],
            ['network_condition_issue', 'Konektivitas', 'Jaringan atau koneksi nirkabel bermasalah', 180000, 600000],
            ['liquid_damage_issue', 'Komponen teknis', 'Pemeriksaan lanjutan diperlukan', 150000, 500000],
            ['button_condition_issue', 'Tombol', 'Kontrol fisik tidak responsif', 120000, 350000],
        ];

        foreach ($repairCosts as [$diagnosisCode, $issueName, $description, $minimumCost, $maximumCost]) {
            RepairCost::query()->updateOrCreate(
                ['diagnosis_code' => $diagnosisCode],
                [
                    'issue_name' => $issueName,
                    'description' => $description,
                    'minimum_cost' => $minimumCost,
                    'maximum_cost' => $maximumCost,
                ]
            );
        }
    }
}
