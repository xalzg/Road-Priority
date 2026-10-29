<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ListDamageReportsRequest;
use App\Http\Requests\Report\StoreDamageReportRequest;
use App\Http\Requests\Report\UpdateReportStatusRequest;
use App\Models\DamageReport;
use App\Models\ReportPhoto;
use App\Models\User;
use App\Policies\DamageReportPolicy;
use App\Services\DamageReportService;
use App\Services\GeoGeometryService;
use App\Services\ReportStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportAccessController extends Controller
{
    public function __construct(
        private ReportStatusService $reportStatusService,
        private DamageReportService $damageReportService,
        private GeoGeometryService $geoGeometryService,
        private DamageReportPolicy $damageReportPolicy
    ) {
    }

    /**
     * Daftar laporan kerusakan dengan filter, bounding box, pagination, dan aturan visibilitas (PRD 04 §9.1).
     */
    public function index(ListDamageReportsRequest $request): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum') ?? $request->user();

        if ($viewer !== null && ! $viewer->is_active) {
            $viewer->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
                'errors' => null,
            ], 403);
        }

        $validated = $request->validated();
        $isOfficerOrAdmin = $viewer !== null && $viewer->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
        $onlyMine = $request->boolean('mine');

        if ($onlyMine && $viewer === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => null,
            ], 401);
        }

        $query = DamageReport::query()
            ->with(['roadSegment:id,road_reference_code,name,road_function,road_authority'])
            ->withCount('photos');

        if ($onlyMine && $viewer !== null) {
            $query->where('reporter_id', $viewer->id);
            if (! empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }
        } elseif ($isOfficerOrAdmin) {
            if (! empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            if (! $request->boolean('include_demo')) {
                $query->where('is_demo', false);
            }
        } else {
            // Pengunjung publik / pelapor (tanpa mine=1) / pakar hanya boleh melihat laporan terverifikasi non-demo
            if (
                ! empty($validated['status'])
                && ! in_array($validated['status'], [DamageReport::STATUS_VERIFIED, DamageReport::STATUS_RESOLVED], true)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Public access is restricted to verified reports.',
                    'errors' => null,
                ], 403);
            }

            $statusFilter = $validated['status'] ?? null;
            if ($statusFilter !== null) {
                $query->where('status', $statusFilter);
            } else {
                $query->whereIn('status', [DamageReport::STATUS_VERIFIED, DamageReport::STATUS_RESOLVED]);
            }
            $query->where('is_demo', false);
        }

        if (! empty($validated['damage_type'])) {
            $query->where('damage_type', $validated['damage_type']);
        }

        if (! empty($validated['road_segment_id'])) {
            $query->where('road_segment_id', (int) $validated['road_segment_id']);
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('reported_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('reported_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where('report_code', 'like', "%{$q}%");
        }

        if (! empty($validated['bbox'])) {
            $bbox = $this->geoGeometryService->parseBoundingBox((string) $validated['bbox']);
            if ($bbox !== null) {
                $query->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
                    ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']]);
            }
        }

        $perPage = min(max((int) ($validated['per_page'] ?? 20), 1), 100);
        $paginator = $query->orderByDesc('reported_at')->orderByDesc('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(function (DamageReport $report) use ($viewer, $isOfficerOrAdmin) {
            $isOwner = $viewer !== null && $report->reporter_id !== null && (int) $report->reporter_id === (int) $viewer->id;
            $canSeePrivateMeta = $isOfficerOrAdmin || $isOwner;

            return [
                'id' => $report->id,
                'report_code' => $report->report_code,
                'reporter_id' => $canSeePrivateMeta ? $report->reporter_id : null,
                'road_segment_id' => $report->road_segment_id,
                'road_segment' => $report->roadSegment ? [
                    'id' => $report->roadSegment->id,
                    'road_reference_code' => $report->roadSegment->road_reference_code,
                    'name' => $report->roadSegment->name,
                    'road_function' => $report->roadSegment->road_function,
                    'road_authority' => $report->roadSegment->road_authority,
                ] : null,
                'segment_match_distance_m' => $report->segment_match_distance_m !== null
                    ? (float) $report->segment_match_distance_m
                    : null,
                'damage_type' => $report->damage_type,
                'description' => $report->description,
                'latitude' => (float) $report->latitude,
                'longitude' => (float) $report->longitude,
                'status' => $report->status,
                'photos_count' => (int) ($report->photos_count ?? 0),
                'is_demo' => (bool) $report->is_demo,
                'reported_at' => $report->reported_at?->toIso8601String(),
                'verified_at' => $report->verified_at?->toIso8601String(),
            ];
        })->values();

        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Damage reports retrieved successfully.',
            'data' => [
                'items' => $items,
                'meta' => $meta,
            ],
            'meta' => $meta,
        ], 200);
    }

    /**
     * Membuat laporan kerusakan jalan baru beserta foto bukti (PRD 04 §4, §5, §9.1).
     */
    public function store(StoreDamageReportRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        /** @var array<int, \Illuminate\Http\UploadedFile> $photos */
        $photos = $request->file('photos', []);

        $report = $this->damageReportService->createReport($actor, $validated, is_array($photos) ? $photos : []);
        $locationCheck = $this->geoGeometryService->evaluateStudyAreaLocation(
            (float) $report->latitude,
            (float) $report->longitude
        );

        return response()->json([
            'success' => true,
            'message' => 'Damage report submitted successfully.',
            'data' => $this->formatReportDetail($report, $actor) + [
                'study_area_check' => $locationCheck,
            ],
        ], 201);
    }

    /**
     * Menampilkan detail satu laporan berdasarkan ID atau report_code sesuai izin akses (PRD 04 §3, §9.1, §11).
     */
    public function show(Request $request, string $reportIdentifier): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum') ?? $request->user();

        if ($viewer !== null && ! $viewer->is_active) {
            $viewer->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
                'errors' => null,
            ], 403);
        }

        $damageReport = DamageReport::query()
            ->with(['photos', 'statusHistories', 'roadSegment', 'duplicateOf:id,report_code,status'])
            ->where(function ($q) use ($reportIdentifier) {
                if (ctype_digit($reportIdentifier)) {
                    $q->where('id', (int) $reportIdentifier);
                } else {
                    $q->where('report_code', $reportIdentifier);
                }
            })
            ->firstOrFail();

        if (! $this->damageReportPolicy->view($viewer, $damageReport)) {
            if ($viewer === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors' => null,
                ], 401);
            }

            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
                'errors' => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Damage report retrieved successfully.',
            'data' => $this->formatReportDetail($damageReport, $viewer),
        ], 200);
    }

    /**
     * Mengubah status laporan secara atomik melalui policy dan state machine (PRD 04 §4.4, §4.5, §6).
     */
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
            roadSegmentId: isset($validated['road_segment_id']) ? (int) $validated['road_segment_id'] : null,
            duplicateOfId: isset($validated['duplicate_of_id']) ? (int) $validated['duplicate_of_id'] : null,
            publishPhotos: array_key_exists('publish_photos', $validated) ? (bool) $validated['publish_photos'] : true
        );

        return response()->json([
            'success' => true,
            'message' => 'Report status updated successfully.',
            'data' => [
                'id' => $updated->id,
                'report_code' => $updated->report_code,
                'status' => $updated->status,
                'road_segment_id' => $updated->road_segment_id,
                'segment_match_distance_m' => $updated->segment_match_distance_m !== null
                    ? (float) $updated->segment_match_distance_m
                    : null,
                'duplicate_of_id' => $updated->duplicate_of_id,
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

    /**
     * Mengalirkan file foto laporan secara aman setelah memeriksa otorisasi (PRD 04 §5 & §11).
     */
    public function showPhoto(
        Request $request,
        DamageReport $damageReport,
        ReportPhoto $reportPhoto
    ): StreamedResponse|JsonResponse {
        if ((int) $reportPhoto->damage_report_id !== (int) $damageReport->id) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => null,
            ], 404);
        }

        /** @var User|null $viewer */
        $viewer = $request->user('sanctum') ?? $request->user();

        if ($viewer !== null && ! $viewer->is_active) {
            $viewer->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
                'errors' => null,
            ], 403);
        }

        if (! $this->damageReportPolicy->viewPhoto($viewer, $damageReport, $reportPhoto)) {
            if ($viewer === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'errors' => null,
                ], 401);
            }

            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
                'errors' => null,
            ], 403);
        }

        $disk = $reportPhoto->storage_disk ?: 'local';
        if (! Storage::disk($disk)->exists($reportPhoto->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => null,
            ], 404);
        }

        return Storage::disk($disk)->response($reportPhoto->file_path, null, [
            'Content-Type' => $reportPhoto->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Menyusun payload detail laporan dengan menerapkan aturan privasi field (PRD 04 §3 & §11).
     *
     * @return array<string, mixed>
     */
    private function formatReportDetail(DamageReport $damageReport, ?User $viewer): array
    {
        $isOfficerOrAdmin = $viewer !== null && $viewer->hasRole(User::ROLE_OFFICER, User::ROLE_ADMIN);
        $isOwner = $viewer !== null
            && $damageReport->reporter_id !== null
            && (int) $damageReport->reporter_id === (int) $viewer->id;
        $canSeeReporterIdentity = $isOfficerOrAdmin || $isOwner;
        $canSeeInternalNotes = $isOfficerOrAdmin || $isOwner;

        $visiblePhotos = $damageReport->photos
            ->filter(fn (ReportPhoto $photo) => $this->damageReportPolicy->viewPhoto($viewer, $damageReport, $photo))
            ->map(fn (ReportPhoto $photo) => [
                'id' => $photo->id,
                'url' => "/api/reports/{$damageReport->id}/photos/{$photo->id}",
                'mime_type' => $photo->mime_type,
                'file_size_bytes' => $photo->file_size_bytes,
                'width' => $photo->width,
                'height' => $photo->height,
                'is_public' => (bool) $photo->is_public,
                'captured_at' => $photo->captured_at?->toIso8601String(),
            ])
            ->values();

        $statusHistories = $damageReport->statusHistories
            ->map(fn ($history) => [
                'id' => $history->id,
                'previous_status' => $history->previous_status,
                'new_status' => $history->new_status,
                'changed_by' => $isOfficerOrAdmin ? $history->changed_by : null,
                'notes' => $canSeeInternalNotes ? $history->notes : null,
                'changed_at' => $history->changed_at?->toIso8601String(),
            ])
            ->values();

        $payload = [
            'id' => $damageReport->id,
            'report_code' => $damageReport->report_code,
            'reporter_id' => $canSeeReporterIdentity ? $damageReport->reporter_id : null,
            'road_segment_id' => $damageReport->road_segment_id,
            'road_segment' => $damageReport->roadSegment ? [
                'id' => $damageReport->roadSegment->id,
                'road_reference_code' => $damageReport->roadSegment->road_reference_code,
                'name' => $damageReport->roadSegment->name,
                'road_function' => $damageReport->roadSegment->road_function,
                'road_authority' => $damageReport->roadSegment->road_authority,
            ] : null,
            'segment_match_distance_m' => $damageReport->segment_match_distance_m !== null
                ? (float) $damageReport->segment_match_distance_m
                : null,
            'damage_type' => $damageReport->damage_type,
            'description' => $damageReport->description,
            'latitude' => (float) $damageReport->latitude,
            'longitude' => (float) $damageReport->longitude,
            'gps_accuracy_m' => $damageReport->gps_accuracy_m !== null ? (float) $damageReport->gps_accuracy_m : null,
            'status' => $damageReport->status,
            'duplicate_of_id' => $damageReport->duplicate_of_id,
            'verification_notes' => $canSeeInternalNotes ? $damageReport->verification_notes : null,
            'verified_by' => $isOfficerOrAdmin ? $damageReport->verified_by : null,
            'is_demo' => (bool) $damageReport->is_demo,
            'reported_at' => $damageReport->reported_at?->toIso8601String(),
            'verified_at' => $damageReport->verified_at?->toIso8601String(),
            'photos' => $visiblePhotos,
            'status_histories' => $statusHistories,
        ];

        if ($isOfficerOrAdmin) {
            $payload['suggested_segments'] = $this->geoGeometryService->suggestNearestSegments(
                (float) $damageReport->latitude,
                (float) $damageReport->longitude,
                (float) config('reporting.segment_suggestion_radius_m', 50.0),
                5,
                (bool) $damageReport->is_demo
            );
            $payload['nearby_duplicate_candidates'] = $this->geoGeometryService->findNearbyCandidateDuplicates(
                $damageReport,
                (float) config('reporting.duplicate_candidate_radius_m', 50.0),
                (int) config('reporting.duplicate_candidate_window_days', 30)
            );
            $payload['study_area_check'] = $this->geoGeometryService->evaluateStudyAreaLocation(
                (float) $damageReport->latitude,
                (float) $damageReport->longitude
            );
        }

        return $payload;
    }
}
