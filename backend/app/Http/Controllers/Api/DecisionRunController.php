<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Decision\ExecuteDecisionRunRequest;
use App\Models\DecisionRun;
use App\Models\User;
use App\Services\DecisionEngine\DecisionDataProvider;
use App\Services\DecisionEngine\DecisionRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecisionRunController extends Controller
{
    public function __construct(
        private DecisionDataProvider $dataProvider,
        private DecisionRunService $runService
    ) {
    }

    public function readiness(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DecisionRun::class);

        $includeDemo = $request->boolean('include_demo', false);
        $report = $this->dataProvider->buildReadinessReport($includeDemo);

        return response()->json([
            'success' => true,
            'message' => 'Decision data readiness report generated successfully.',
            'data' => $report,
        ], 200);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DecisionRun::class);

        $runs = DecisionRun::query()
            ->with(['weightVersion:id,version_name,status,consistency_ratio,is_consistent', 'executor:id,name,email,role'])
            ->withCount([
                'items as ranked_count' => static fn ($q) => $q->where('is_included', true),
                'items as excluded_count' => static fn ($q) => $q->where('is_included', false),
            ])
            ->orderByDesc('executed_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Decision runs retrieved successfully.',
            'data' => $runs,
        ], 200);
    }

    public function store(ExecuteDecisionRunRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $run = $this->runService->executeRun($actor, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'AHP-TOPSIS decision run executed and snapshot saved successfully.',
            'data' => $this->runService->formatRunDetail($run),
        ], 201);
    }

    public function show(Request $request, DecisionRun $decisionRun): JsonResponse
    {
        $this->authorize('view', $decisionRun);

        $decisionRun->load(['weightVersion', 'items.roadSegment', 'executor:id,name,email,role']);

        return response()->json([
            'success' => true,
            'message' => 'Decision run detail retrieved and reproducibility verified.',
            'data' => $this->runService->formatRunDetail($decisionRun),
        ], 200);
    }
}
