<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DamageAssessment;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DamageAssessmentController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = DamageAssessment::query()
            ->with([
                'roadSegment:id,road_reference_code,name,road_function',
                'damageReport:id,report_code,title,status',
                'assessor:id,name,role',
            ])
            ->orderByDesc('assessment_date')
            ->orderByDesc('id');

        if ($request->filled('road_segment_id')) {
            $query->where('road_segment_id', (int) $request->query('road_segment_id'));
        }

        if (! $request->boolean('include_demo', false)) {
            $query->where('is_demo', false);
        }

        return response()->json([
            'success' => true,
            'message' => 'Damage assessments retrieved successfully.',
            'data' => $query->get(),
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'road_segment_id' => ['required', 'integer', 'exists:road_segments,id'],
            'damage_report_id' => ['sometimes', 'nullable', 'integer', 'exists:damage_reports,id'],
            'assessment_date' => ['sometimes', 'nullable', 'date'],
            'protocol_version' => ['sometimes', 'string', 'max:64'],
            'severity_level' => ['required', 'numeric', 'min:1', 'max:5'],
            'safety_risk_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'road_function_score' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'impact_score' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'measurements' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'string', Rule::in([DamageAssessment::STATUS_DRAFT, DamageAssessment::STATUS_FINAL])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assessment_source' => ['sometimes', 'string', Rule::in(['field_inspection', 'official_secondary', 'demo_fixture'])],
            'is_demo' => ['sometimes', 'boolean'],
        ]);

        $assessment = DB::transaction(function () use ($actor, $validated): DamageAssessment {
            $created = DamageAssessment::query()->create([
                'road_segment_id' => (int) $validated['road_segment_id'],
                'damage_report_id' => $validated['damage_report_id'] ?? null,
                'assessed_by' => $actor->id,
                'assessment_date' => $validated['assessment_date'] ?? now(),
                'protocol_version' => $validated['protocol_version'] ?? 'D-04-Ordinal-v1.0',
                'severity_level' => (float) $validated['severity_level'],
                'safety_risk_score' => (float) $validated['safety_risk_score'],
                'road_function_score' => isset($validated['road_function_score']) ? (float) $validated['road_function_score'] : null,
                'impact_score' => isset($validated['impact_score']) ? (float) $validated['impact_score'] : null,
                'measurements' => $validated['measurements'] ?? null,
                'status' => $validated['status'] ?? DamageAssessment::STATUS_FINAL,
                'notes' => $validated['notes'] ?? null,
                'assessment_source' => $validated['assessment_source'] ?? 'field_inspection',
                'is_demo' => (bool) ($validated['is_demo'] ?? false),
            ]);

            $this->auditLogService->record(
                actor: $actor,
                action: 'damage_assessment.created',
                entityType: 'damage_assessment',
                entityId: $created->id,
                changesSummary: [
                    'road_segment_id' => $created->road_segment_id,
                    'severity_level' => $created->severity_level,
                    'safety_risk_score' => $created->safety_risk_score,
                    'status' => $created->status,
                    'assessment_source' => $created->assessment_source,
                ]
            );

            return $created->fresh(['roadSegment:id,road_reference_code,name', 'assessor:id,name,role']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Damage assessment recorded successfully.',
            'data' => $assessment,
        ], 201);
    }
}
