<?php

namespace App\Services;

use App\Models\DamageReport;
use App\Models\ReportStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReportStatusService
{
    /**
     * Peta transisi status yang diizinkan sesuai state machine Master PRD §8.
     */
    public const ALLOWED_TRANSITIONS = [
        DamageReport::STATUS_SUBMITTED => [
            DamageReport::STATUS_UNDER_REVIEW,
        ],
        DamageReport::STATUS_UNDER_REVIEW => [
            DamageReport::STATUS_VERIFIED,
            DamageReport::STATUS_REJECTED,
            DamageReport::STATUS_NEEDS_INFO,
        ],
        DamageReport::STATUS_NEEDS_INFO => [
            DamageReport::STATUS_SUBMITTED,
        ],
        DamageReport::STATUS_VERIFIED => [
            DamageReport::STATUS_RESOLVED,
        ],
        DamageReport::STATUS_REJECTED => [],
        DamageReport::STATUS_RESOLVED => [],
    ];

    private AuditLogService $auditLogs;

    public function __construct(AuditLogService $auditLogs)
    {
        $this->auditLogs = $auditLogs;
    }

    public function transition(
        User $actor,
        DamageReport $report,
        string $newStatus,
        ?string $notes = null,
        ?int $roadSegmentId = null
    ): DamageReport {
        $currentStatus = $report->status;
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new ConflictHttpException(
                "Invalid status transition from '{$currentStatus}' to '{$newStatus}'."
            );
        }

        return DB::transaction(function () use ($actor, $report, $currentStatus, $newStatus, $notes, $roadSegmentId) {
            $report->status = $newStatus;
            if ($notes !== null) {
                $report->verification_notes = $notes;
            }
            if ($roadSegmentId !== null) {
                $report->road_segment_id = $roadSegmentId;
            }
            if ($newStatus === DamageReport::STATUS_VERIFIED) {
                $report->verified_by = $actor->id;
                $report->verified_at = now();
            }
            $report->save();

            ReportStatusHistory::create([
                'damage_report_id' => $report->id,
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'changed_by' => $actor->id,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            $this->auditLogs->record($actor, 'report.status_changed', 'damage_report', $report->id, [
                'report_code' => $report->report_code,
                'previous_status' => $currentStatus,
                'new_status' => $newStatus,
                'road_segment_id' => $report->road_segment_id,
                'notes' => $notes,
            ]);

            return $report->fresh(['statusHistories']);
        });
    }
}
