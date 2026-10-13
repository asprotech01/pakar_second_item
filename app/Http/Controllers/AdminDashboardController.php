<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSession;
use App\Models\Fact;
use App\Models\Rule;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $totalAssessments = AssessmentSession::query()->count();
        $completedAssessments = AssessmentSession::query()->where('status', 'evaluated')->count();

        return view('admin.dashboard', [
            'stats' => [
                'assessments_total' => $totalAssessments,
                'assessments_completed' => $completedAssessments,
                'facts_total' => Fact::query()->count(),
                'rules_total' => Rule::query()->count(),
                'rules_active' => Rule::query()->where('is_active', true)->count(),
            ],
            'recentAssessments' => AssessmentSession::query()
                ->with(['deviceType', 'result'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
