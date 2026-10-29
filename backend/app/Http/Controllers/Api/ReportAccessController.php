<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\UpdateReportStatusRequest;
use App\Models\DamageReport;
use App\Models\User;
use App\Services\ReportStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportAccessController extends Controller
{
    public function __construct(
        private ReportStatusService $reportStatusService
    ) {
    }

    public function show(Request $request, DamageReport $damageReport): JsonResponse
    {
        $this->authorize('view', $damageReport);

        /** @var User $viewer */
        $viewer = $request->user();
        $canSeeReporterIdentity = $viewer->isAdmin()
            || $viewer->isOfficer()
            || (int) $damageReport->reporter_id === (int) $viewer->id;

        return response()->json([
            'success' => true,
            'message' => 'Damage report retrieved successfully.',
            'data' => [
                'id' => $damageReport->id,
                'report_code' => $damageReport->report_code,
                'reporter_id' => $canSeeReporterIdentity ? $damageReport->reporter_id : null,
                'road_segment_id' => $damageReport->road_segment_id,
                'segment_match_distance_m' => $damageReport->segment_match_distance_m !== null
                    ? (float) $damageReport->segment_match_distance_m
                    : null,
                'damage_type' => $damageReport->damage_type,
                'description' => $damageReport->description,
                'latitude' => (float) $damageReport->latitude,
                'longitude' => (float) $damageReport->longitude,
                'gps_accuracy_m' => $damageReport->gps_accuracy_m !== null ? (float) $damageReport->gps_accuracy_m : null,
                'status' => $damageReport->status,
                'verification_notes' => $damageReport->verification_notes,
                'verified_by' => $damageReport->verified_by,
                'is_demo' => (bool) $damageReport->is_demo,
                'reported_at' => $damageReport->reported_at?->toIso8601String(),
                'verified_at' => $damageReport->verified_at?->toIso8601String(),
            ],
        ], 200);
    }

    public function updateStatus(UpdateReportStatusRequest $request, DamageReport $damageReport): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $updated = $this->reportStatusService->transition(
            actor: $actor,
            report: $damageReport,
            newStatus: $validated['new_status'],
            notes: $validated['notes'] ?? null,
            roadSegmentId: $validated['road_segment_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully.',
            'data' => [
                'id' => $updated->id,
                'report_code' => $updated->report_code,
                'status' => $updated->status,
                'road_segment_id' => $updated->road_segment_id,
                'verified_by' => $updated->verified_by,
                'verified_at' => $updated->verified_at?->toIso8601String(),
                'verification_notes' => $updated->verification_notes,
                'status_histories' => $updated->statusHistories->map(fn ($history) => [
                    'id' => $history->id,
                    'previous_status' => $history->previous_status,
                    'new_status' => $history->new_status,
                    'changed_by' => $history->changed_by,
                    'notes' => $history->notes,
                    'changed_at' => $history->changed_at?->toIso8601String(),
                ])->values(),
            ],
        ], 200);
    }
}
