<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoadSegment\ListRoadSegmentsRequest;
use App\Models\DamageReport;
use App\Models\RoadSegment;
use App\Models\User;
use App\Services\GeoGeometryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoadSegmentController extends Controller
{
    public function __construct(
        private GeoGeometryService $geoGeometryService
    ) {
    }

    /**
     * Daftar segmen jalan beserta geometri GeoJSON (urutan [longitude, latitude] - PRD 04 §8 & §9.2).
     */
    public function index(ListRoadSegmentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum') ?? $request->user();
        $canIncludeDemo = $viewer !== null && $viewer->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);

        $query = RoadSegment::query()
            ->where('is_active', true)
            ->withCount([
                'damageReports as verified_reports_count' => function ($q) {
                    $q->where('status', DamageReport::STATUS_VERIFIED)
                        ->where('is_demo', false);
                },
            ]);

        if (! ($canIncludeDemo && $request->boolean('include_demo'))) {
            $query->where('is_demo', false);
        }

        if (! empty($validated['administrative_area_id'])) {
            $query->where('administrative_area_id', (int) $validated['administrative_area_id']);
        }

        if (! empty($validated['road_function'])) {
            $query->where('road_function', $validated['road_function']);
        }

        if (! empty($validated['road_authority'])) {
            $query->where('road_authority', $validated['road_authority']);
        }

        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('road_reference_code', 'like', "%{$q}%");
            });
        }

        if (! empty($validated['bbox'])) {
            $bbox = $this->geoGeometryService->parseBoundingBox((string) $validated['bbox']);
            if ($bbox !== null) {
                $query->whereBetween('centroid_latitude', [$bbox['min_lat'], $bbox['max_lat']])
                    ->whereBetween('centroid_longitude', [$bbox['min_lng'], $bbox['max_lng']]);
            }
        }

        $includeGeometry = ! array_key_exists('include_geometry', $validated)
            || filter_var($validated['include_geometry'], FILTER_VALIDATE_BOOLEAN);

        $perPage = min(max((int) ($validated['per_page'] ?? 20), 1), 100);
        $paginator = $query->orderBy('road_reference_code')->paginate($perPage);

        $items = $paginator->getCollection()->map(function (RoadSegment $segment) use ($includeGeometry) {
            return [
                'id' => $segment->id,
                'road_reference_code' => $segment->road_reference_code,
                'name' => $segment->name,
                'administrative_area_id' => $segment->administrative_area_id,
                'centroid_latitude' => $segment->centroid_latitude !== null ? (float) $segment->centroid_latitude : null,
                'centroid_longitude' => $segment->centroid_longitude !== null ? (float) $segment->centroid_longitude : null,
                'length_meters' => $segment->length_meters !== null ? (float) $segment->length_meters : null,
                'road_function' => $segment->road_function,
                'road_authority' => $segment->road_authority,
                'nearest_facility_meters' => $segment->nearest_facility_meters !== null
                    ? (float) $segment->nearest_facility_meters
                    : null,
                'geometry_geojson' => $includeGeometry ? $segment->geometry_geojson : null,
                'source_name' => $segment->source_name,
                'data_origin' => $segment->data_origin,
                'is_demo' => (bool) $segment->is_demo,
                'verified_reports_count' => (int) ($segment->verified_reports_count ?? 0),
                'last_verified_at' => $segment->last_verified_at?->toIso8601String(),
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
            'message' => 'Road segments retrieved successfully.',
            'data' => [
                'items' => $items,
                'meta' => $meta,
            ],
            'meta' => $meta,
        ], 200);
    }

    /**
     * Detail satu segmen jalan berdasarkan ID atau road_reference_code (PRD 04 §9.2).
     */
    public function show(Request $request, string $segmentIdentifier): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum') ?? $request->user();
        $canViewDemo = $viewer !== null && $viewer->hasRole(User::ROLE_OFFICER, User::ROLE_EXPERT, User::ROLE_ADMIN);

        $query = RoadSegment::query()
            ->with(['administrativeArea:id,name,administrative_level'])
            ->withCount([
                'damageReports as verified_reports_count' => function ($q) {
                    $q->where('status', DamageReport::STATUS_VERIFIED)
                        ->where('is_demo', false);
                },
            ])
            ->where(function ($q) use ($segmentIdentifier) {
                if (ctype_digit($segmentIdentifier)) {
                    $q->where('id', (int) $segmentIdentifier);
                } else {
                    $q->where('road_reference_code', $segmentIdentifier);
                }
            });

        if (! $canViewDemo) {
            $query->where('is_demo', false);
        }

        $segment = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Road segment retrieved successfully.',
            'data' => [
                'id' => $segment->id,
                'road_reference_code' => $segment->road_reference_code,
                'name' => $segment->name,
                'administrative_area_id' => $segment->administrative_area_id,
                'administrative_area' => $segment->administrativeArea ? [
                    'id' => $segment->administrativeArea->id,
                    'name' => $segment->administrativeArea->name,
                    'administrative_level' => $segment->administrativeArea->administrative_level,
                ] : null,
                'centroid_latitude' => $segment->centroid_latitude !== null ? (float) $segment->centroid_latitude : null,
                'centroid_longitude' => $segment->centroid_longitude !== null ? (float) $segment->centroid_longitude : null,
                'length_meters' => $segment->length_meters !== null ? (float) $segment->length_meters : null,
                'road_function' => $segment->road_function,
                'road_authority' => $segment->road_authority,
                'nearest_facility_meters' => $segment->nearest_facility_meters !== null
                    ? (float) $segment->nearest_facility_meters
                    : null,
                'geometry_geojson' => $segment->geometry_geojson,
                'source_name' => $segment->source_name,
                'source_reference' => $segment->source_reference,
                'data_origin' => $segment->data_origin,
                'is_demo' => (bool) $segment->is_demo,
                'verified_reports_count' => (int) ($segment->verified_reports_count ?? 0),
                'last_verified_at' => $segment->last_verified_at?->toIso8601String(),
            ],
        ], 200);
    }
}

