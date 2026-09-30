<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSession;
use App\Models\DeviceType;
use App\Models\Question;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $deviceTypes = DeviceType::query()
            ->where('is_active', true)
            ->orderBy('platform')
            ->get();

        foreach ($deviceTypes as $deviceType) {
            $deviceType->setAttribute('questions_count', Question::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->whereNull('device_type_id')
                    ->orWhere('device_type_id', $deviceType->id))
                ->count());
        }

        $recentAssessments = AssessmentSession::query()
            ->with(['deviceType', 'result'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'deviceTypes' => $deviceTypes,
            'recentAssessments' => $recentAssessments,
            'assessmentCount' => AssessmentSession::count(),
            'completedCount' => AssessmentSession::where('status', 'evaluated')->count(),
        ]);
    }

    public function history(): View
    {
        return view('history', [
            'assessments' => AssessmentSession::query()
                ->with(['deviceType', 'result'])
                ->latest()
                ->paginate(12),
        ]);
    }
}
