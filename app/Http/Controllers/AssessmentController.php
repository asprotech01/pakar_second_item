<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Question;
use App\Services\ExpertSystem\ExpertEvaluationService;
use App\Services\MarketValueService;
use App\Services\PurchaseEvaluationService;
use App\Services\PurchaseRecommendationService;
use App\Services\RepairCostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_type_id' => ['required', 'integer', 'exists:device_types,id'],
            'workspace' => ['sometimes', 'accepted'],
            'device_id' => ['required_if:workspace,1', 'nullable', 'integer', 'exists:devices,id'],
            'device_label' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
        ]);

        $deviceType = DeviceType::query()
            ->where('is_active', true)
            ->findOrFail($validated['device_type_id']);
        $device = isset($validated['device_id'])
            ? Device::query()->findOrFail($validated['device_id'])
            : null;
        $storageVariants = $device?->storageVariants() ?? [];
        $storageRules = $device !== null && $storageVariants !== []
            ? ['required', 'string', Rule::in($storageVariants)]
            : ['nullable', 'prohibited'];
        $storageVariant = $request->validate([
            'storage_variant' => $storageRules,
        ])['storage_variant'] ?? null;

        $session = AssessmentSession::create([
            'user_id' => $request->user()?->id,
            'device_type_id' => $deviceType->id,
            'device_id' => $device?->id,
            'storage_variant' => $storageVariant,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'status' => 'in_progress',
            'device_details' => ['label' => $device?->name ?? ($validated['device_label'] ?? null)],
            'started_at' => now(),
        ]);

        return redirect()->route('assessments.show', $session);
    }

    public function show(Request $request, AssessmentSession $assessmentSession): View|RedirectResponse
    {
        abort_unless($request->user()?->id === $assessmentSession->user_id || $request->user()?->isAdmin(), 403);

        if ($assessmentSession->status === 'evaluated' && $assessmentSession->result()->exists()) {
            return redirect()->route('assessments.result', $assessmentSession);
        }

        $assessmentSession->load(['deviceType', 'device']);
        $questions = $this->questionsFor($assessmentSession);
        $answers = $assessmentSession->answers()->with('option')->get()->keyBy('question_id');

        return view('assessments.inspect', [
            'assessmentSession' => $assessmentSession,
            'questions' => $questions,
            'answers' => $answers,
            'categories' => $questions->groupBy(fn ($question) => $question->inspectionCategory->name),
        ]);
    }

    public function evaluate(
        Request $request,
        AssessmentSession $assessmentSession,
        ExpertEvaluationService $evaluationService
    ): RedirectResponse {
        abort_unless($request->user()?->id === $assessmentSession->user_id || $request->user()?->isAdmin(), 403);

        $questions = $this->questionsFor($assessmentSession);
        $selectionValidator = Validator::make($request->all(), [
            'answers' => ['required', 'array'],
            'answers.*.selection' => ['nullable', 'string', 'max:40'],
            'answers.*.source' => ['nullable', 'string', 'max:30'],
            'answers.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($selectionValidator->fails()) {
            throw new ValidationException($selectionValidator);
        }

        $inputAnswers = $selectionValidator->validated()['answers'];
        $preparedAnswers = [];

        foreach ($questions as $question) {
            $input = $inputAnswers[$question->id] ?? [];
            $selection = $input['selection'] ?? null;

            if ($selection === null || $selection === '') {
                if ($question->is_required) {
                    throw ValidationException::withMessages([
                        'answers.'.$question->id.'.selection' => 'Pilih kondisi atau tandai tidak diketahui untuk setiap pemeriksaan.',
                    ]);
                }

                continue;
            }

            $answerStates = [
                'unknown' => AssessmentAnswer::STATE_UNKNOWN,
                'not_applicable' => AssessmentAnswer::STATE_NOT_APPLICABLE,
                'not_tested' => AssessmentAnswer::STATE_NOT_TESTED,
            ];

            if (isset($answerStates[$selection])) {
                $preparedAnswers[] = [
                    'question_id' => $question->id,
                    'question_option_id' => null,
                    'answer_state' => $answerStates[$selection],
                    'evidence_source' => 'UNKNOWN',
                    'observed_value' => null,
                    'certainty_factor' => 0,
                    'notes' => $input['notes'] ?? null,
                ];

                continue;
            }

            $inputType = strtoupper((string) ($question->input_type ?? 'single_choice'));
            if (in_array($inputType, ['NUMBER', 'NUMERIC'], true) || is_numeric((string) $selection)) {
                $normalizedValue = trim((string) $selection);
                if (! is_numeric($normalizedValue)) {
                    throw ValidationException::withMessages([
                        'answers.'.$question->id.'.selection' => 'Nilai numerik tidak valid.',
                    ]);
                }

                $source = $input['source'] ?? 'UNKNOWN';
                if (! in_array($source, AssessmentAnswer::EVIDENCE_SOURCES, true)) {
                    throw ValidationException::withMessages([
                        'answers.'.$question->id.'.source' => 'Sumber bukti tidak valid.',
                    ]);
                }

                $preparedAnswers[] = [
                    'question_id' => $question->id,
                    'question_option_id' => null,
                    'answer_state' => AssessmentAnswer::STATE_KNOWN,
                    'evidence_source' => $source,
                    'observed_value' => $normalizedValue,
                    'certainty_factor' => 1,
                    'notes' => $input['notes'] ?? null,
                ];

                continue;
            }

            if (! str_starts_with($selection, 'option:') || ! ctype_digit(substr($selection, 7))) {
                throw ValidationException::withMessages([
                    'answers.'.$question->id.'.selection' => 'Pilihan kondisi tidak valid.',
                ]);
            }

            $optionId = (int) substr($selection, 7);
            $option = $question->options->first(fn ($option) => $option->id === $optionId);

            if ($option === null) {
                throw ValidationException::withMessages([
                    'answers.'.$question->id.'.selection' => 'Pilihan kondisi tidak cocok dengan pertanyaan ini.',
                ]);
            }

            $source = $input['source'] ?? 'UNKNOWN';
            if (! in_array($source, AssessmentAnswer::EVIDENCE_SOURCES, true)) {
                throw ValidationException::withMessages([
                    'answers.'.$question->id.'.source' => 'Sumber bukti tidak valid.',
                ]);
            }

            $preparedAnswers[] = [
                'question_id' => $question->id,
                'question_option_id' => $option->id,
                'answer_state' => AssessmentAnswer::STATE_KNOWN,
                'evidence_source' => $source,
                'observed_value' => $option->value,
                'certainty_factor' => 1,
                'notes' => $input['notes'] ?? null,
            ];
        }

        DB::transaction(function () use ($assessmentSession, $preparedAnswers, $evaluationService) {
            foreach ($preparedAnswers as $answer) {
                $assessmentSession->answers()->updateOrCreate(
                    ['question_id' => $answer['question_id']],
                    $answer + ['answered_at' => now()]
                );
            }

            $evaluationService->evaluate($assessmentSession);
        });

        return redirect()->route('assessments.result', $assessmentSession);
    }

    public function result(
        Request $request,
        AssessmentSession $assessmentSession,
        RepairCostService $repairCostService,
        PurchaseEvaluationService $purchaseEvaluationService,
        MarketValueService $marketValueService,
        PurchaseRecommendationService $purchaseRecommendationService
    ): View|RedirectResponse {
        abort_unless($request->user()?->id === $assessmentSession->user_id || $request->user()?->isAdmin(), 403);

        $assessmentSession->load(['deviceType', 'device.marketValues', 'result.primaryConclusion']);

        if ($assessmentSession->result === null) {
            return redirect()->route('assessments.show', $assessmentSession);
        }

        $repairCostEstimates = $repairCostService->estimatesFor($assessmentSession->result);
        $marketValue = $assessmentSession->device === null
            ? null
            : $marketValueService->forDevice(
                $assessmentSession->device,
                $assessmentSession->storage_variant
            );
        $purchaseEvaluation = $purchaseEvaluationService->calculate(
            $assessmentSession->purchase_price,
            $repairCostEstimates
        );

        return view('assessments.result', [
            'assessmentSession' => $assessmentSession,
            'repairCostEstimates' => $repairCostEstimates,
            'marketValue' => $marketValue,
            'purchaseEvaluation' => $purchaseEvaluation,
            'purchaseRecommendation' => $purchaseRecommendationService->evaluate(
                $assessmentSession->result,
                $purchaseEvaluation,
                $marketValue
            ),
        ]);
    }

    private function questionsFor(AssessmentSession $assessmentSession)
    {
        return Question::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('device_type_id')
                ->orWhere('device_type_id', $assessmentSession->device_type_id))
            ->with([
                'inspectionCategory',
                'options' => fn ($query) => $query->where('is_active', true)->with('fact'),
            ])
            ->orderBy('inspection_category_id')
            ->orderBy('sort_order')
            ->get();
    }
}
