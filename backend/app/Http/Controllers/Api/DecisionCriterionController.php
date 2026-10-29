<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Criterion\StoreDecisionCriterionRequest;
use App\Http\Requests\Criterion\UpdateDecisionCriterionRequest;
use App\Models\AhpPairwiseComparison;
use App\Models\CriterionWeightItem;
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

        $criteria = DecisionCriterion::query()
            ->withCount('weightItems')
            ->orderBy('code')
            ->get();

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

    public function update(
        UpdateDecisionCriterionRequest $request,
        DecisionCriterion $decisionCriterion
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $usedInWeights = CriterionWeightItem::query()
            ->where('decision_criterion_id', $decisionCriterion->id)
            ->exists();
        $usedInComparisons = AhpPairwiseComparison::query()
            ->where('criterion_a_id', $decisionCriterion->id)
            ->orWhere('criterion_b_id', $decisionCriterion->id)
            ->exists();
        $isHistoricallyUsed = $usedInWeights || $usedInComparisons;

        // PRD 05 §4.2: Kriteria yang telah digunakan dalam perhitungan historis tidak boleh diubah maknanya secara diam-diam.
        $immutableSemanticFields = ['code', 'criterion_type', 'data_type', 'minimum_value', 'maximum_value'];
        if ($isHistoricallyUsed) {
            foreach ($immutableSemanticFields as $field) {
                if (array_key_exists($field, $validated) && (string) $validated[$field] !== (string) $decisionCriterion->{$field}) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'Kriteria %s telah digunakan dalam versi bobot/perhitungan historis; atribut semantik (%s) tidak boleh diubah secara diam-diam. Silakan buat kriteria baru untuk versi konfigurasi baru.',
                            $decisionCriterion->code,
                            $field
                        ),
                        'error_code' => 'HISTORICAL_CRITERION_IMMUTABLE',
                    ], 409);
                }
            }
        }

        $minVal = array_key_exists('minimum_value', $validated)
            ? (float) $validated['minimum_value']
            : (float) $decisionCriterion->minimum_value;
        $maxVal = array_key_exists('maximum_value', $validated)
            ? (float) $validated['maximum_value']
            : (float) $decisionCriterion->maximum_value;

        if ($maxVal < $minVal) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => [
                    'maximum_value' => ['The maximum value must be greater than or equal to the minimum value.'],
                ],
            ], 422);
        }

        $updated = DB::transaction(function () use ($actor, $decisionCriterion, $validated): DecisionCriterion {
            $before = $decisionCriterion->only(array_keys($validated));
            $decisionCriterion->fill($validated);
            $decisionCriterion->save();

            $this->auditLogService->record(
                actor: $actor,
                action: 'criterion.updated',
                entityType: 'decision_criterion',
                entityId: $decisionCriterion->id,
                changesSummary: [
                    'code' => $decisionCriterion->code,
                    'before' => $before,
                    'after' => $decisionCriterion->only(array_keys($validated)),
                ]
            );

            return $decisionCriterion->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Decision criterion updated successfully.',
            'data' => $updated,
        ], 200);
    }
}
