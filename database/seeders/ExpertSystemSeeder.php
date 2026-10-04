<?php

namespace Database\Seeders;

use App\Models\DeviceCategory;
use App\Models\DeviceType;
use App\Models\Expert;
use App\Models\Fact;
use App\Models\InspectionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\RuleCondition;
use Illuminate\Database\Seeder;

class ExpertSystemSeeder extends Seeder
{
    public function run(): void
    {
        $deviceCategory = DeviceCategory::firstOrCreate(
            ['slug' => 'smartphone'],
            ['name' => 'Smartphone', 'description' => 'Ponsel pintar bekas.']
        );

        DeviceType::firstOrCreate(
            ['slug' => 'android-smartphone'],
            ['device_category_id' => $deviceCategory->id, 'name' => 'Smartphone Android', 'platform' => 'android']
        );
        DeviceType::firstOrCreate(
            ['slug' => 'iphone'],
            ['device_category_id' => $deviceCategory->id, 'name' => 'iPhone', 'platform' => 'ios']
        );

        $categoryDefinitions = [
            ['physical', 'Fisik', 'Bodi, rangka, dan indikasi kerusakan cairan.'],
            ['display', 'Display', 'Layar dan fungsi sentuh.'],
            ['battery', 'Baterai', 'Kondisi dan keamanan baterai.'],
            ['charging', 'Charging', 'Port dan proses pengisian daya.'],
            ['camera', 'Kamera', 'Kamera depan dan belakang.'],
            ['audio', 'Audio', 'Speaker dan mikrofon.'],
            ['security', 'Security dan lock', 'Biometrik serta status kunci akun.'],
            ['connectivity', 'Konektivitas', 'Jaringan seluler dan koneksi nirkabel.'],
            ['controls', 'Tombol dan kontrol', 'Tombol fisik dan kontrol utama perangkat.'],
        ];

        $categories = [];
        foreach ($categoryDefinitions as [$slug, $name, $description]) {
            $categories[$slug] = InspectionCategory::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description]
            );
        }

        $questionDefinitions = [
            ['physical_condition', 'physical', 'Apakah bodi dan rangka bebas dari kerusakan berat?', 'Kondisi fisik wajar', 'Penyok, retak, atau bekas perbaikan berat'],
            ['display_condition', 'display', 'Apakah layar dan fungsi sentuh bekerja normal?', 'Layar berfungsi normal', 'Ada garis, bercak, atau sentuhan bermasalah'],
            ['battery_health', 'battery', 'Apakah daya tahan baterai masih wajar untuk pemakaian?', 'Daya tahan masih wajar', 'Baterai cepat habis atau perlu diganti'],
            ['battery_swelling', 'battery', 'Apakah ada tanda baterai menggembung atau casing terangkat?', 'Tidak ada tanda menggembung', 'Baterai menggembung atau casing terangkat'],
            ['charging_condition', 'charging', 'Apakah perangkat mengisi daya stabil melalui port?', 'Pengisian daya stabil', 'Pengisian daya bermasalah'],
            ['camera_condition', 'camera', 'Apakah kamera depan dan belakang dapat digunakan?', 'Kamera berfungsi', 'Salah satu kamera bermasalah'],
            ['audio_condition', 'audio', 'Apakah speaker dan mikrofon berfungsi dengan baik?', 'Audio berfungsi', 'Speaker atau mikrofon bermasalah'],
            ['biometric_condition', 'security', 'Apakah fingerprint atau Face ID dapat digunakan?', 'Biometrik berfungsi', 'Biometrik tidak berfungsi'],
            ['activation_lock', 'security', 'Apakah perangkat bebas dari Activation Lock, FRP, dan akun pemilik lama?', 'Bebas akun dan siap diatur', 'Masih terkunci akun atau FRP'],
            ['network_condition', 'connectivity', 'Apakah SIM, Wi-Fi, dan Bluetooth dapat terhubung?', 'Koneksi berfungsi', 'Ada masalah jaringan atau koneksi'],
            ['liquid_damage', 'physical', 'Apakah ada indikasi bekas cairan atau korosi?', 'Tidak ada indikasi cairan', 'Ada indikasi cairan atau korosi'],
            ['button_condition', 'controls', 'Apakah tombol daya, volume, dan kontrol utama berfungsi?', 'Tombol berfungsi', 'Ada tombol yang tidak berfungsi'],
        ];

        $facts = [];
        $questions = [];
        foreach ($questionDefinitions as [$code, $categorySlug, $prompt, $normalLabel, $issueLabel]) {
            $normalCode = $code.'_normal';
            $issueCode = $code.'_issue';
            $facts[$normalCode] = $this->fact($normalCode, $normalLabel);
            $facts[$issueCode] = $this->fact($issueCode, $issueLabel);

            $question = Question::firstOrCreate(
                ['code' => $code],
                [
                    'inspection_category_id' => $categories[$categorySlug]->id,
                    'prompt' => $prompt,
                    'is_required' => true,
                ]
            );
            $questions[$code] = $question;

            QuestionOption::firstOrCreate(
                ['question_id' => $question->id, 'code' => 'normal'],
                ['fact_id' => $facts[$normalCode]->id, 'label' => $normalLabel, 'value' => 'normal']
            );
            QuestionOption::firstOrCreate(
                ['question_id' => $question->id, 'code' => 'issue'],
                ['fact_id' => $facts[$issueCode]->id, 'label' => $issueLabel, 'value' => 'issue']
            );

            Recommendation::firstOrCreate(
                ['question_id' => $question->id, 'title' => 'Verifikasi: '.$prompt],
                [
                    'inspection_category_id' => $categories[$categorySlug]->id,
                    'body' => 'Lakukan pemeriksaan langsung dan catat sumber bukti sebelum menetapkan kondisi perangkat.',
                    'priority' => 10,
                ]
            );
        }

        $facts['device_viable'] = $this->fact(
            'device_viable', 'Layak secara teknis', true, 'layak', 'low'
        );
        $facts['device_needs_inspection'] = $this->fact(
            'device_needs_inspection', 'Perlu pemeriksaan atau perbaikan', true, 'bersyarat', 'medium'
        );
        $facts['device_not_recommended'] = $this->fact(
            'device_not_recommended', 'Tidak direkomendasikan', true, 'tidak_direkomendasikan', 'high'
        );

        $expert = Expert::firstOrCreate(
            ['email' => 'ahli.pemeriksa@tilik.local'],
            [
                'name' => 'Panel Ahli Pemeriksaan Perangkat',
                'affiliation' => 'Tilik',
                'credentials' => 'Validasi kondisi perangkat bekas',
            ]
        );

        $experts = [
            'battery_swelling' => ['avoid_swollen_battery', 'Baterai menggembung berisiko keselamatan.', 'critical', 0.99],
            'activation_lock' => ['avoid_locked_device', 'Perangkat terkunci akun tidak siap digunakan.', 'high', 0.98],
            'liquid_damage' => ['review_liquid_damage', 'Bekas cairan dapat menimbulkan kerusakan lanjutan.', 'high', 0.95],
        ];
        foreach ($experts as $questionCode => [$ruleCode, $description, $risk, $certainty]) {
            $issueFact = $facts[$questionCode.'_issue'];
            $this->rule($ruleCode, $description, $facts['device_not_recommended'], $issueFact, $certainty, 'tidak_direkomendasikan', $risk, $expert);
            $this->recommendation($issueFact, null, 'Perlu perhatian khusus', $description, 1);
        }

        $repairableIssues = [
            'physical_condition', 'display_condition', 'battery_health', 'charging_condition',
            'camera_condition', 'audio_condition', 'biometric_condition', 'network_condition',
            'button_condition',
        ];
        foreach ($repairableIssues as $questionCode) {
            $issueFact = $facts[$questionCode.'_issue'];
            $this->rule(
                'inspect_'.$questionCode,
                'Temuan pada '.$questions[$questionCode]->prompt,
                $facts['device_needs_inspection'],
                $issueFact,
                0.85,
                'bersyarat',
                'medium',
                $expert
            );
            $this->recommendation(
                $issueFact,
                null,
                'Periksa '.$questions[$questionCode]->inspectionCategory->name,
                'Minta pemeriksaan langsung dan estimasi biaya perbaikan sebelum transaksi.',
                5
            );
        }

        $viableRule = Rule::firstOrCreate(
            ['code' => 'all_required_checks_normal'],
            [
                'conclusion_fact_id' => $facts['device_viable']->id,
                'name' => 'Seluruh pemeriksaan wajib normal',
                'description' => 'Kesimpulan layak hanya aktif jika semua pemeriksaan wajib diketahui dan normal.',
                'certainty_factor' => 0.90,
                'classification' => 'layak',
                'risk_level' => 'low',
                'priority' => 100,
            ]
        );
        foreach ($questionDefinitions as [$code]) {
            RuleCondition::firstOrCreate(
                ['rule_id' => $viableRule->id, 'fact_id' => $facts[$code.'_normal']->id],
                ['operator' => 'PRESENT']
            );
        }
        $viableRule->experts()->syncWithoutDetaching([
            $expert->id => [
                'certainty_factor' => 0.90,
                'notes' => 'CF ahli untuk rule seluruh pemeriksaan normal.',
                'validated_at' => now(),
            ],
        ]);

    }

    private function fact(
        string $code,
        string $name,
        bool $isConclusion = false,
        ?string $classification = null,
        ?string $riskLevel = null
    ): Fact {
        return Fact::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'is_conclusion' => $isConclusion,
                'classification' => $classification,
                'risk_level' => $riskLevel,
            ]
        );
    }

    private function rule(
        string $code,
        string $name,
        Fact $conclusion,
        Fact $condition,
        float $certainty,
        string $classification,
        string $riskLevel,
        Expert $expert
    ): Rule {
        $rule = Rule::firstOrCreate(
            ['code' => $code],
            [
                'conclusion_fact_id' => $conclusion->id,
                'name' => $name,
                'description' => $name,
                'certainty_factor' => $certainty,
                'classification' => $classification,
                'risk_level' => $riskLevel,
            ]
        );

        RuleCondition::firstOrCreate(
            ['rule_id' => $rule->id, 'fact_id' => $condition->id],
            ['operator' => 'PRESENT']
        );
        $rule->experts()->syncWithoutDetaching([
            $expert->id => [
                'certainty_factor' => $certainty,
                'notes' => 'CF ahli untuk rule: '.$name,
                'validated_at' => now(),
            ],
        ]);

        return $rule;
    }

    private function recommendation(Fact $fact, ?Rule $rule, string $title, string $body, int $priority): void
    {
        Recommendation::firstOrCreate(
            ['fact_id' => $fact->id, 'title' => $title],
            ['rule_id' => $rule?->id, 'body' => $body, 'priority' => $priority]
        );
    }
}
