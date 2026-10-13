<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSession;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

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
            ->where('user_id', $user?->id)
            ->with(['deviceType', 'result'])
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'deviceTypes' => $deviceTypes,
            'selectedDevice' => $request->filled('device_id')
                ? Device::query()->find($request->input('device_id'))
                : null,
            'recentAssessments' => $recentAssessments,
            'assessmentCount' => AssessmentSession::query()->where('user_id', $user?->id)->count(),
            'completedCount' => AssessmentSession::query()->where('user_id', $user?->id)->where('status', 'evaluated')->count(),
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $searchTerm = trim($validated['q'] ?? '');

        if (mb_strlen($searchTerm) < 2) {
            return response()->json(['devices' => []]);
        }

        $searchPattern = '%'.$searchTerm.'%';
        $devices = Device::query()
            ->select(['id', 'name', 'manufacturer_name', 'storage'])
            ->where(fn ($query) => $query
                ->where('name', 'like', $searchPattern)
                ->orWhere('manufacturer_name', 'like', $searchPattern))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'manufacturer_name' => $device->manufacturer_name,
                'storage_variants' => $device->storageVariants(),
            ])
            ->values();

        return response()->json(['devices' => $devices]);
    }

    public function history(Request $request): View
    {
        return view('history', [
            'assessments' => AssessmentSession::query()
                ->where('user_id', $request->user()->id)
                ->with(['deviceType', 'result'])
                ->latest()
                ->paginate(12),
        ]);
    }
}
