<?php

namespace App\Services;

use App\Models\DamageReport;
use App\Models\ReportStatusHistory;
use App\Models\RoadSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReportStatusService
{
    /**
     * Peta transisi status yang diizinkan sesuai state machine Master PRD §8 & PRD 04 §4.4.
     */
    public const ALLOWED_TRANSITIONS = [
        DamageReport::STATUS_SUBMITTED => [
            DamageReport::STATUS_UNDER_REVIEW,
        ],
        DamageReport::STATUS_UNDER_REVIEW => [
            DamageReport::STATUS_VERIFIED,
            DamageReport::STATUS_REJECTED,
            DamageReport::STATUS_DUPLICATE,
            DamageReport::STATUS_NEEDS_INFO,
        ],
        DamageReport::STATUS_NEEDS_INFO => [
            DamageReport::STATUS_SUBMITTED,
            DamageReport::STATUS_UNDER_REVIEW,
        ],
        DamageReport::STATUS_VERIFIED => [
            DamageReport::STATUS_RESOLVED,
            DamageReport::STATUS_UNDER_REVIEW, // Mekanisme koreksi berjejak histori (PRD 04 §4.4)
        ],
        DamageReport::STATUS_REJECTED => [
            DamageReport::STATUS_UNDER_REVIEW,
        ],
        DamageReport::STATUS_DUPLICATE => [
            DamageReport::STATUS_UNDER_REVIEW,
        ],
        DamageReport::STATUS_RESOLVED => [],
    ];

    private AuditLogService $auditLogs;
    private ?GeoGeometryService $geoGeometry;

    public function __construct(AuditLogService $auditLogs, ?GeoGeometryService $geoGeometry = null)
    {
        $this->auditLogs = $auditLogs;
        $this->geoGeometry = $geoGeometry ?? new GeoGeometryService();
    }

    public function transition(
        User $actor,
        DamageReport $report,
        string $newStatus,
        ?string $notes = null,
        ?int $roadSegmentId = null,
        ?int $duplicateOfId = null,
        bool $publishPhotos = true
    ): DamageReport {
        $currentStatus = $report->status;
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw new ConflictHttpException(
                "Invalid status transition from '{$currentStatus}' to '{$newStatus}'."
            );
        }

        if ($newStatus === DamageReport::STATUS_DUPLICATE && $duplicateOfId !== null && $duplicateOfId === (int) $report->id) {
            throw new ConflictHttpException('A report cannot be marked as a duplicate of itself.');
        }

        return DB::transaction(function () use (
            $actor,
            $report,
            $currentStatus,
            $newStatus,
            $notes,
            $roadSegmentId,
            $duplicateOfId,
            $publishPhotos
        ) {
            $report->status = $newStatus;
            if ($notes !== null) {
                $report->verification_notes = $notes;
            }
            if ($roadSegmentId !== null) {
                $report->road_segment_id = $roadSegmentId;
                $segment = RoadSegment::query()->find($roadSegmentId);
                if ($segment !== null && $this->geoGeometry !== null) {
                    $dist = $this->geoGeometry->distanceToLineStringMeters(
                        (float) $report->latitude,
                        (float) $report->longitude,
                        $segment->geometry_geojson
                    );
                    if ($dist === null && $segment->centroid_latitude !== null && $segment->centroid_longitude !== null) {
                        $dist = $this->geoGeometry->distanceMeters(
                            (float) $report->latitude,
                            (float) $report->longitude,
                            (float) $segment->centroid_latitude,
                            (float) $segment->centroid_longitude
                        );
                    }
                    $report->segment_match_distance_m = $dist;
                }
            }

            if ($newStatus === DamageReport::STATUS_DUPLICATE) {
                $report->duplicate_of_id = $duplicateOfId;
            }

            if ($newStatus === DamageReport::STATUS_VERIFIED) {
                $report->verified_by = $actor->id;
                $report->verified_at = now();
                $report->photos()->update(['is_public' => $publishPhotos]);
            } elseif ($currentStatus === DamageReport::STATUS_VERIFIED && $newStatus !== DamageReport::STATUS_RESOLVED) {
                // Jika dikoreksi kembali ke under_review, tarik visibilitas foto publik hingga diverifikasi ulang
                $report->photos()->update(['is_public' => false]);
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
                'duplicate_of_id' => $report->duplicate_of_id,
                'notes' => $notes,
            ]);

            return $report->fresh(['statusHistories', 'photos', 'roadSegment']);
        });
    }
}
