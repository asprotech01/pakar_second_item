<?php

namespace Tests\Feature;

use App\Models\DeviceCategory;
use App\Models\DeviceType;
use App\Models\Expert;
use App\Models\Fact;
use App\Models\InspectionCategory;
use App\Models\Question;
use App\Models\Rule;
use App\Models\RuleCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeBaseManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function knowledge_base_index_page_is_available_and_lists_existing_entries(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        Fact::query()->create([
            'code' => 'battery_ok',
            'name' => 'Baterai normal',
            'is_active' => true,
        ]);

        $conclusionFact = Fact::query()->create([
            'code' => 'device_viable',
            'name' => 'Layak secara teknis',
            'is_conclusion' => true,
            'classification' => 'layak',
            'risk_level' => 'low',
            'is_active' => true,
        ]);

        $rule = Rule::query()->create([
            'code' => 'rule_battery_ok',
            'name' => 'Baterai normal',
            'conclusion_fact_id' => $conclusionFact->id,
            'certainty_factor' => 0.85,
            'classification' => 'layak',
            'risk_level' => 'low',
            'priority' => 10,
            'is_active' => true,
        ]);

        RuleCondition::query()->create([
            'rule_id' => $rule->id,
            'fact_id' => Fact::query()->first()->id,
            'operator' => 'PRESENT',
            'sequence' => 1,
        ]);

        $this->get(route('admin.knowledge-base.index'))
            ->assertOk()
            ->assertSee('Basis Pengetahuan')
            ->assertSee('battery_ok')
            ->assertSee('rule_battery_ok');
    }

    #[Test]
    public function admin_can_store_new_fact_and_rule_entries(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $conclusionFact = Fact::query()->create([
            'code' => 'device_needs_inspection',
            'name' => 'Perlu pemeriksaan lanjutan',
            'is_conclusion' => true,
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);

        $this->post(route('admin.knowledge-base.facts.store'), [
            'code' => 'display_issue',
            'name' => 'Terdapat masalah layar',
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'is_conclusion' => 0,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->post(route('admin.knowledge-base.rules.store'), [
            'code' => 'rule_display_issue',
            'name' => 'Layar bermasalah',
            'description' => 'Layar tidak berfungsi normal',
            'conclusion_fact_id' => $conclusionFact->id,
            'condition_fact_id' => Fact::query()->where('code', 'display_issue')->firstOrFail()->id,
            'certainty_factor' => 0.9,
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'priority' => 5,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseHas('facts', ['code' => 'display_issue', 'name' => 'Terdapat masalah layar']);
        $this->assertDatabaseHas('rules', ['code' => 'rule_display_issue', 'name' => 'Layar bermasalah']);
        $this->assertDatabaseHas('rule_conditions', [
            'rule_id' => Rule::query()->where('code', 'rule_display_issue')->value('id'),
            'fact_id' => Fact::query()->where('code', 'display_issue')->value('id'),
            'operator' => 'PRESENT',
        ]);
    }

    #[Test]
    public function admin_can_manage_device_catalog_and_question_templates(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $deviceCategory = $this->post(route('admin.knowledge-base.device-categories.store'), [
            'name' => 'Smartphone',
            'slug' => 'smartphone',
            'description' => 'Kategori perangkat mobile',
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $inspectionCategory = $this->post(route('admin.knowledge-base.inspection-categories.store'), [
            'name' => 'Kondisi Fisik',
            'slug' => 'kondisi-fisik',
            'description' => 'Pemeriksaan kondisi fisik perangkat',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->post(route('admin.knowledge-base.device-types.store'), [
            'device_category_id' => DeviceCategory::query()->value('id'),
            'name' => 'Samsung A52',
            'slug' => 'samsung-a52',
            'platform' => 'android',
            'confidence_threshold' => 0.68,
            'completeness_threshold' => 0.75,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->post(route('admin.knowledge-base.questions.store'), [
            'inspection_category_id' => InspectionCategory::query()->value('id'),
            'device_type_id' => DeviceType::query()->value('id'),
            'code' => 'phone_display_condition',
            'prompt' => 'Bagaimana kondisi layar ponsel?',
            'input_type' => 'single_choice',
            'is_required' => 1,
            'completeness_weight' => 1.5,
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseHas('device_categories', ['slug' => 'smartphone', 'name' => 'Smartphone']);
        $this->assertDatabaseHas('inspection_categories', ['slug' => 'kondisi-fisik', 'name' => 'Kondisi Fisik']);
        $this->assertDatabaseHas('device_types', ['slug' => 'samsung-a52', 'platform' => 'android']);
        $this->assertDatabaseHas('questions', ['code' => 'phone_display_condition', 'prompt' => 'Bagaimana kondisi layar ponsel?']);
    }

    #[Test]
    public function admin_can_update_and_delete_catalog_and_question_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $deviceCategory = DeviceCategory::query()->create([
            'name' => 'Tablet',
            'slug' => 'tablet',
            'description' => 'Kategori tablet umum',
            'is_active' => true,
        ]);
        $inspectionCategory = InspectionCategory::query()->create([
            'name' => 'Kondisi Layar',
            'slug' => 'kondisi-layar',
            'description' => 'Pemeriksaan layar',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $deviceType = DeviceType::query()->create([
            'device_category_id' => $deviceCategory->id,
            'name' => 'iPad Mini',
            'slug' => 'ipad-mini',
            'platform' => 'ios',
            'confidence_threshold' => 0.62,
            'completeness_threshold' => 0.78,
            'is_active' => true,
        ]);
        $question = Question::query()->create([
            'inspection_category_id' => $inspectionCategory->id,
            'device_type_id' => $deviceType->id,
            'code' => 'tablet_screen_damage',
            'prompt' => 'Apakah layar tablet mengalami retak?',
            'input_type' => 'boolean',
            'is_required' => true,
            'completeness_weight' => 1.0,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->put(route('admin.knowledge-base.device-categories.update', ['deviceCategory' => $deviceCategory->id]), [
            'name' => 'Tablet Pro',
            'slug' => 'tablet-pro',
            'description' => 'Kategori tablet premium',
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->put(route('admin.knowledge-base.inspection-categories.update', ['inspectionCategory' => $inspectionCategory->id]), [
            'name' => 'Kondisi Layar & Body',
            'slug' => 'kondisi-layar-body',
            'description' => 'Pemeriksaan layar dan body',
            'sort_order' => 3,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->put(route('admin.knowledge-base.device-types.update', ['deviceType' => $deviceType->id]), [
            'device_category_id' => $deviceCategory->id,
            'name' => 'iPad Mini 6',
            'slug' => 'ipad-mini-6',
            'platform' => 'ios',
            'confidence_threshold' => 0.7,
            'completeness_threshold' => 0.8,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->put(route('admin.knowledge-base.questions.update', ['question' => $question->id]), [
            'inspection_category_id' => $inspectionCategory->id,
            'device_type_id' => $deviceType->id,
            'code' => 'tablet_screen_damage_updated',
            'prompt' => 'Apakah layar tablet mengalami retak atau mati piksel?',
            'input_type' => 'boolean',
            'is_required' => 1,
            'completeness_weight' => 1.4,
            'sort_order' => 3,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseHas('device_categories', ['id' => $deviceCategory->id, 'slug' => 'tablet-pro']);
        $this->assertDatabaseHas('inspection_categories', ['id' => $inspectionCategory->id, 'slug' => 'kondisi-layar-body']);
        $this->assertDatabaseHas('device_types', ['id' => $deviceType->id, 'slug' => 'ipad-mini-6']);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'code' => 'tablet_screen_damage_updated']);

        $this->delete(route('admin.knowledge-base.questions.destroy', ['question' => $question->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));
        $this->delete(route('admin.knowledge-base.device-types.destroy', ['deviceType' => $deviceType->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));
        $this->delete(route('admin.knowledge-base.device-categories.destroy', ['deviceCategory' => $deviceCategory->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));
        $this->delete(route('admin.knowledge-base.inspection-categories.destroy', ['inspectionCategory' => $inspectionCategory->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseMissing('device_categories', ['id' => $deviceCategory->id]);
        $this->assertDatabaseMissing('inspection_categories', ['id' => $inspectionCategory->id]);
        $this->assertDatabaseMissing('device_types', ['id' => $deviceType->id]);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    #[Test]
    public function admin_can_store_expert_profiles_and_rule_validations(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $conclusionFact = Fact::query()->create([
            'code' => 'device_check_recommended',
            'name' => 'Perlu pengecekan lanjutan',
            'is_conclusion' => true,
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $conditionFact = Fact::query()->create([
            'code' => 'speaker_issue',
            'name' => 'Masalah speaker',
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $rule = Rule::query()->create([
            'code' => 'rule_speaker_issue',
            'name' => 'Masalah speaker',
            'conclusion_fact_id' => $conclusionFact->id,
            'certainty_factor' => 0.82,
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'priority' => 8,
            'is_active' => true,
        ]);
        RuleCondition::query()->create([
            'rule_id' => $rule->id,
            'fact_id' => $conditionFact->id,
            'operator' => 'PRESENT',
            'sequence' => 1,
        ]);

        $this->post(route('admin.knowledge-base.experts.store'), [
            'name' => 'Dr. Hendra Teknik',
            'email' => 'hendra@tilik.test',
            'affiliation' => 'Lab Teknisi',
            'credentials' => 'Ahli perangkat bekas',
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $expert = Expert::query()->where('email', 'hendra@tilik.test')->firstOrFail();

        $this->post(route('admin.knowledge-base.rules.validate', ['rule' => $rule->id]), [
            'expert_id' => $expert->id,
            'certainty_factor' => 0.9,
            'notes' => 'Kondisi speaker berpotensi memerlukan perbaikan tambahan sebelum transaksi.',
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseHas('experts', ['email' => 'hendra@tilik.test', 'name' => 'Dr. Hendra Teknik']);
        $this->assertDatabaseHas('rule_experts', [
            'rule_id' => $rule->id,
            'expert_id' => $expert->id,
            'certainty_factor' => '0.9000',
        ]);
    }

    #[Test]
    public function admin_can_update_and_delete_knowledge_base_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $conclusionFact = Fact::query()->create([
            'code' => 'camera_summary',
            'name' => 'Ringkasan kamera',
            'is_conclusion' => true,
            'classification' => 'bersyarat',
            'is_active' => true,
        ]);
        $fact = Fact::query()->create([
            'code' => 'camera_issue',
            'name' => 'Masalah kamera',
            'risk_level' => 'medium',
            'is_active' => true,
        ]);
        $expert = Expert::query()->create([
            'name' => 'Ahli Kamera',
            'email' => 'kamera@tilik.test',
            'is_active' => true,
        ]);
        $rule = Rule::query()->create([
            'code' => 'rule_camera_issue',
            'name' => 'Kamera bermasalah',
            'conclusion_fact_id' => $conclusionFact->id,
            'certainty_factor' => 0.7,
            'priority' => 7,
            'is_active' => true,
        ]);
        RuleCondition::query()->create([
            'rule_id' => $rule->id,
            'fact_id' => $fact->id,
            'operator' => 'PRESENT',
            'sequence' => 1,
        ]);

        $this->put(route('admin.knowledge-base.facts.update', ['fact' => $fact->id]), [
            'code' => 'camera_issue_updated',
            'name' => 'Masalah kamera terupdate',
            'classification' => 'bersyarat',
            'risk_level' => 'high',
            'is_conclusion' => 0,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->put(route('admin.knowledge-base.experts.update', ['expert' => $expert->id]), [
            'name' => 'Ahli Kamera Updated',
            'email' => 'kamera.updated@tilik.test',
            'affiliation' => 'QC Lab',
            'credentials' => 'Ahli inspeksi kamera',
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->put(route('admin.knowledge-base.rules.update', ['rule' => $rule->id]), [
            'code' => 'rule_camera_issue_updated',
            'name' => 'Kamera bermasalah terupdate',
            'description' => 'Kamera tidak fokus',
            'conclusion_fact_id' => $conclusionFact->id,
            'condition_fact_id' => $fact->id,
            'certainty_factor' => 0.8,
            'classification' => 'bersyarat',
            'risk_level' => 'medium',
            'priority' => 9,
            'is_active' => 1,
        ])->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseHas('facts', ['code' => 'camera_issue_updated', 'name' => 'Masalah kamera terupdate']);
        $this->assertDatabaseHas('experts', ['email' => 'kamera.updated@tilik.test', 'name' => 'Ahli Kamera Updated']);
        $this->assertDatabaseHas('rules', ['code' => 'rule_camera_issue_updated', 'name' => 'Kamera bermasalah terupdate']);

        $this->delete(route('admin.knowledge-base.rules.destroy', ['rule' => $rule->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));
        $this->delete(route('admin.knowledge-base.experts.destroy', ['expert' => $expert->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));
        $this->delete(route('admin.knowledge-base.facts.destroy', ['fact' => $fact->id]))
            ->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseMissing('rules', ['id' => $rule->id]);
        $this->assertDatabaseMissing('experts', ['id' => $expert->id]);
        $this->assertDatabaseMissing('facts', ['id' => $fact->id]);
    }
}
