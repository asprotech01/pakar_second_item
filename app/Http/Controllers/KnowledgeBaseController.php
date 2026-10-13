<?php

namespace App\Http\Controllers;

use App\Models\DeviceCategory;
use App\Models\DeviceType;
use App\Models\Expert;
use App\Models\Fact;
use App\Models\InspectionCategory;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Rule;
use App\Models\RuleCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    public function index(): View
    {
        $facts = Fact::query()->orderBy('code')->get();
        $rules = Rule::query()
            ->with(['conclusion', 'conditions.fact', 'experts'])
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('code')
            ->get();
        $deviceCategories = DeviceCategory::query()->orderBy('name')->get();
        $inspectionCategories = InspectionCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        $deviceTypes = DeviceType::query()->with('category')->orderBy('platform')->orderBy('name')->get();
        $questions = Question::query()
            ->with(['inspectionCategory', 'deviceType'])
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
        $questionOptions = QuestionOption::query()
            ->with(['question', 'fact'])
            ->orderBy('question_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('admin.knowledge-base.index', [
            'facts' => $facts,
            'rules' => $rules,
            'experts' => Expert::query()->orderBy('name')->get(),
            'deviceCategories' => $deviceCategories,
            'inspectionCategories' => $inspectionCategories,
            'deviceTypes' => $deviceTypes,
            'questions' => $questions,
            'questionOptions' => $questionOptions,
            'stats' => [
                'facts_total' => $facts->count(),
                'rules_total' => $rules->count(),
                'experts_total' => Expert::query()->count(),
                'device_categories_total' => $deviceCategories->count(),
                'inspection_categories_total' => $inspectionCategories->count(),
                'device_types_total' => $deviceTypes->count(),
                'questions_total' => $questions->count(),
                'question_options_total' => $questionOptions->count(),
                'rules_validated' => $rules->filter(fn (Rule $rule) => $rule->experts->isNotEmpty())->count(),
                'rules_pending_validation' => $rules->filter(fn (Rule $rule) => $rule->experts->isEmpty())->count(),
            ],
        ]);
    }

    public function storeDeviceCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:device_categories,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DeviceCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori perangkat berhasil disimpan.');
    }

    public function updateDeviceCategory(Request $request, DeviceCategory $deviceCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:device_categories,slug,'.$deviceCategory->id],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $deviceCategory->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori perangkat berhasil diperbarui.');
    }

    public function destroyDeviceCategory(DeviceCategory $deviceCategory): RedirectResponse
    {
        $deviceCategory->deviceTypes()->delete();
        $deviceCategory->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori perangkat berhasil dihapus.');
    }

    public function storeInspectionCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:inspection_categories,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        InspectionCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori inspeksi berhasil disimpan.');
    }

    public function updateInspectionCategory(Request $request, InspectionCategory $inspectionCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:inspection_categories,slug,'.$inspectionCategory->id],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $inspectionCategory->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori inspeksi berhasil diperbarui.');
    }

    public function destroyInspectionCategory(InspectionCategory $inspectionCategory): RedirectResponse
    {
        $inspectionCategory->questions()->delete();
        $inspectionCategory->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Kategori inspeksi berhasil dihapus.');
    }

    public function storeDeviceType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_category_id' => ['required', 'exists:device_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:device_types,slug'],
            'platform' => ['required', 'string', 'max:30'],
            'confidence_threshold' => ['required', 'numeric', 'between:0,1'],
            'completeness_threshold' => ['required', 'numeric', 'between:0,1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DeviceType::query()->create([
            'device_category_id' => $validated['device_category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'platform' => $validated['platform'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'completeness_threshold' => (float) $validated['completeness_threshold'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Tipe perangkat berhasil disimpan.');
    }

    public function updateDeviceType(Request $request, DeviceType $deviceType): RedirectResponse
    {
        $validated = $request->validate([
            'device_category_id' => ['required', 'exists:device_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', 'unique:device_types,slug,'.$deviceType->id],
            'platform' => ['required', 'string', 'max:30'],
            'confidence_threshold' => ['required', 'numeric', 'between:0,1'],
            'completeness_threshold' => ['required', 'numeric', 'between:0,1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $deviceType->update([
            'device_category_id' => $validated['device_category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'platform' => $validated['platform'],
            'confidence_threshold' => (float) $validated['confidence_threshold'],
            'completeness_threshold' => (float) $validated['completeness_threshold'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Tipe perangkat berhasil diperbarui.');
    }

    public function destroyDeviceType(DeviceType $deviceType): RedirectResponse
    {
        $deviceType->questions()->delete();
        $deviceType->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Tipe perangkat berhasil dihapus.');
    }

    public function storeQuestion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inspection_category_id' => ['required', 'exists:inspection_categories,id'],
            'device_type_id' => ['nullable', 'exists:device_types,id'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:questions,code'],
            'prompt' => ['required', 'string', 'max:255'],
            'input_type' => ['required', 'string', 'in:single_choice,multiple_choice,boolean,text,number'],
            'is_required' => ['nullable', 'boolean'],
            'completeness_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Question::query()->create([
            'inspection_category_id' => $validated['inspection_category_id'],
            'device_type_id' => $validated['device_type_id'] ?? null,
            'code' => $validated['code'],
            'prompt' => $validated['prompt'],
            'input_type' => $validated['input_type'],
            'is_required' => (bool) ($validated['is_required'] ?? true),
            'completeness_weight' => (float) $validated['completeness_weight'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pertanyaan berhasil disimpan.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $validated = $request->validate([
            'inspection_category_id' => ['required', 'exists:inspection_categories,id'],
            'device_type_id' => ['nullable', 'exists:device_types,id'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:questions,code,'.$question->id],
            'prompt' => ['required', 'string', 'max:255'],
            'input_type' => ['required', 'string', 'in:single_choice,multiple_choice,boolean,text,number'],
            'is_required' => ['nullable', 'boolean'],
            'completeness_weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $question->update([
            'inspection_category_id' => $validated['inspection_category_id'],
            'device_type_id' => $validated['device_type_id'] ?? null,
            'code' => $validated['code'],
            'prompt' => $validated['prompt'],
            'input_type' => $validated['input_type'],
            'is_required' => (bool) ($validated['is_required'] ?? true),
            'completeness_weight' => (float) $validated['completeness_weight'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pertanyaan berhasil diperbarui.');
    }

    public function destroyQuestion(Question $question): RedirectResponse
    {
        $question->options()->delete();
        $question->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pertanyaan berhasil dihapus.');
    }

    public function storeQuestionOption(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'fact_id' => ['nullable', 'exists:facts,id'],
            'code' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:180'],
            'value' => ['nullable', 'string', 'max:120'],
            'certainty_factor' => ['required', 'numeric', 'between:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        QuestionOption::query()->create([
            'question_id' => $validated['question_id'],
            'fact_id' => $validated['fact_id'] ?? null,
            'code' => $validated['code'],
            'label' => $validated['label'],
            'value' => $validated['value'] ?? null,
            'certainty_factor' => (float) $validated['certainty_factor'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pilihan pertanyaan berhasil disimpan.');
    }

    public function updateQuestionOption(Request $request, QuestionOption $questionOption): RedirectResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'fact_id' => ['nullable', 'exists:facts,id'],
            'code' => ['required', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:180'],
            'value' => ['nullable', 'string', 'max:120'],
            'certainty_factor' => ['required', 'numeric', 'between:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $questionOption->update([
            'question_id' => $validated['question_id'],
            'fact_id' => $validated['fact_id'] ?? null,
            'code' => $validated['code'],
            'label' => $validated['label'],
            'value' => $validated['value'] ?? null,
            'certainty_factor' => (float) $validated['certainty_factor'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pilihan pertanyaan berhasil diperbarui.');
    }

    public function destroyQuestionOption(QuestionOption $questionOption): RedirectResponse
    {
        $questionOption->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Pilihan pertanyaan berhasil dihapus.');
    }

    public function storeFact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:facts,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'classification' => ['nullable', 'string', 'in:layak,bersyarat,tidak_direkomendasikan,unverified'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high,critical,unknown'],
            'is_conclusion' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            Fact::query()->firstOrCreate(
                ['code' => $validated['code']],
                [
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'is_conclusion' => (bool) ($validated['is_conclusion'] ?? false),
                    'classification' => $validated['classification'] ?? null,
                    'risk_level' => $validated['risk_level'] ?? null,
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]
            );
        });

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Fakta berhasil disimpan.');
    }

    public function updateFact(Request $request, Fact $fact): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:facts,code,'.$fact->id],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'classification' => ['nullable', 'string', 'in:layak,bersyarat,tidak_direkomendasikan,unverified'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high,critical,unknown'],
            'is_conclusion' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $fact->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_conclusion' => (bool) ($validated['is_conclusion'] ?? false),
            'classification' => $validated['classification'] ?? null,
            'risk_level' => $validated['risk_level'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Fakta berhasil diperbarui.');
    }

    public function destroyFact(Fact $fact): RedirectResponse
    {
        $fact->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Fakta berhasil dihapus.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:rules,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'conclusion_fact_id' => ['required', 'exists:facts,id'],
            'condition_fact_id' => ['required', 'exists:facts,id'],
            'certainty_factor' => ['required', 'numeric', 'between:0,1'],
            'classification' => ['nullable', 'string', 'in:layak,bersyarat,tidak_direkomendasikan,unverified'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high,critical,unknown'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            $rule = Rule::query()->firstOrCreate(
                ['code' => $validated['code']],
                [
                    'conclusion_fact_id' => $validated['conclusion_fact_id'],
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'certainty_factor' => (float) $validated['certainty_factor'],
                    'classification' => $validated['classification'] ?? null,
                    'risk_level' => $validated['risk_level'] ?? null,
                    'priority' => (int) ($validated['priority'] ?? 0),
                    'is_active' => (bool) ($validated['is_active'] ?? true),
                ]
            );

            RuleCondition::query()->updateOrCreate(
                [
                    'rule_id' => $rule->id,
                    'fact_id' => $validated['condition_fact_id'],
                ],
                [
                    'operator' => 'PRESENT',
                    'sequence' => 1,
                ]
            );
        });

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Rule berhasil disimpan.');
    }

    public function updateRule(Request $request, Rule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:rules,code,'.$rule->id],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'conclusion_fact_id' => ['required', 'exists:facts,id'],
            'condition_fact_id' => ['required', 'exists:facts,id'],
            'certainty_factor' => ['required', 'numeric', 'between:0,1'],
            'classification' => ['nullable', 'string', 'in:layak,bersyarat,tidak_direkomendasikan,unverified'],
            'risk_level' => ['nullable', 'string', 'in:low,medium,high,critical,unknown'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'conclusion_fact_id' => $validated['conclusion_fact_id'],
            'certainty_factor' => (float) $validated['certainty_factor'],
            'classification' => $validated['classification'] ?? null,
            'risk_level' => $validated['risk_level'] ?? null,
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $rule->conditions()->delete();
        $rule->conditions()->create([
            'fact_id' => $validated['condition_fact_id'],
            'operator' => 'PRESENT',
            'sequence' => 1,
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Rule berhasil diperbarui.');
    }

    public function destroyRule(Rule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Rule berhasil dihapus.');
    }

    public function storeExpert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120', 'unique:experts,email'],
            'affiliation' => ['nullable', 'string', 'max:120'],
            'credentials' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            Expert::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'affiliation' => $validated['affiliation'] ?? null,
                'credentials' => $validated['credentials'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Data ahli berhasil disimpan.');
    }

    public function updateExpert(Request $request, Expert $expert): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120', 'unique:experts,email,'.$expert->id],
            'affiliation' => ['nullable', 'string', 'max:120'],
            'credentials' => ['nullable', 'string', 'max:180'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $expert->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'affiliation' => $validated['affiliation'] ?? null,
            'credentials' => $validated['credentials'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Data ahli berhasil diperbarui.');
    }

    public function destroyExpert(Expert $expert): RedirectResponse
    {
        $expert->delete();

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Data ahli berhasil dihapus.');
    }

    public function storeExpertValidation(Request $request, Rule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'expert_id' => ['required', 'exists:experts,id'],
            'certainty_factor' => ['required', 'numeric', 'between:0,1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($rule, $validated) {
            $rule->experts()->syncWithoutDetaching([
                $validated['expert_id'] => [
                    'certainty_factor' => (float) $validated['certainty_factor'],
                    'notes' => $validated['notes'] ?? null,
                    'validated_at' => now(),
                ],
            ]);
        });

        return redirect()->route('admin.knowledge-base.index')->with('status', 'Validasi ahli untuk rule berhasil disimpan.');
    }
}
