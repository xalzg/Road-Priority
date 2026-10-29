<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Criterion\StoreDecisionCriterionRequest;
use App\Models\DecisionCriterion;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DecisionCriterionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DecisionCriterion::class);

        $criteria = DecisionCriterion::query()->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'message' => 'Decision criteria retrieved successfully.',
            'data' => $criteria,
        ], 200);
    }

    public function store(StoreDecisionCriterionRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $criterion = DB::transaction(function () use ($actor, $validated): DecisionCriterion {
            $created = DecisionCriterion::query()->create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'data_type' => $validated['data_type'],
                'unit' => $validated['unit'] ?? null,
                'criterion_type' => $validated['criterion_type'],
                'minimum_value' => $validated['minimum_value'],
                'maximum_value' => $validated['maximum_value'],
                'aggregation_rule' => $validated['aggregation_rule'] ?? null,
                'is_required' => (bool) ($validated['is_required'] ?? true),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            $this->auditLogService->record(
                actor: $actor,
                action: 'criterion.created',
                entityType: 'decision_criterion',
                entityId: $created->id,
                changesSummary: [
                    'code' => $created->code,
                    'name' => $created->name,
                    'criterion_type' => $created->criterion_type,
                ]
            );

            return $created;
        });

        return response()->json([
            'success' => true,
            'message' => 'Decision criterion created successfully.',
            'data' => $criterion,
        ], 201);
    }
}
