<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Decision\CalculateWeightVersionRequest;
use App\Http\Requests\Decision\StoreWeightVersionRequest;
use App\Models\CriterionWeightVersion;
use App\Models\User;
use App\Services\DecisionEngine\CriterionWeightVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CriterionWeightVersionController extends Controller
{
    public function __construct(
        private CriterionWeightVersionService $weightVersionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CriterionWeightVersion::class);

        $versions = CriterionWeightVersion::query()
            ->with(['weightItems.criterion', 'creator:id,name,email,role', 'approver:id,name,email,role'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Criterion weight versions retrieved successfully.',
            'data' => $versions,
        ], 200);
    }

    public function show(Request $request, CriterionWeightVersion $criterionWeightVersion): JsonResponse
    {
        $this->authorize('view', $criterionWeightVersion);

        $criterionWeightVersion->load([
            'weightItems.criterion',
            'ahpComparisons.criterionA:id,code,name',
            'ahpComparisons.criterionB:id,code,name',
            'ahpComparisons.expert:id,name,role',
            'creator:id,name,email,role',
            'approver:id,name,email,role',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Criterion weight version detail retrieved successfully.',
            'data' => $criterionWeightVersion,
        ], 200);
    }

    public function store(StoreWeightVersionRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $version = $this->weightVersionService->createDraft($actor, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Criterion weight version draft created successfully.',
            'data' => $version,
        ], 201);
    }

    public function calculate(
        CalculateWeightVersionRequest $request,
        CriterionWeightVersion $criterionWeightVersion
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $version = $this->weightVersionService->calculateAhp(
            $actor,
            $criterionWeightVersion,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => $version->is_consistent
                ? 'AHP weights calculated and consistency ratio verified (CR <= 0.10).'
                : 'AHP weights calculated, but consistency ratio exceeds 0.10. Please review pairwise comparisons before activating.',
            'data' => $version,
        ], 200);
    }

    public function activate(Request $request, CriterionWeightVersion $criterionWeightVersion): JsonResponse
    {
        $this->authorize('approve', $criterionWeightVersion);

        /** @var User $actor */
        $actor = $request->user();

        $version = $this->weightVersionService->activateVersion($actor, $criterionWeightVersion);

        return response()->json([
            'success' => true,
            'message' => 'Criterion weight version approved and activated successfully.',
            'data' => $version,
        ], 200);
    }
}
