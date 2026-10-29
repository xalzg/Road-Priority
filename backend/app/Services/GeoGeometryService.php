<?php

namespace App\Services;

use App\Models\AdministrativeArea;
use App\Models\DamageReport;
use App\Models\RoadSegment;

class GeoGeometryService
{
    private const EARTH_RADIUS_M = 6371008.8;

    /**
     * Menghitung jarak permukaan bumi (Haversine) dalam meter antara dua titik WGS 84.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return round(self::EARTH_RADIUS_M * $c, 2);
    }

    /**
     * Menghitung jarak minimum dari titik (lat, lng) ke geometri GeoJSON LineString dalam meter.
     * Sesuai DEC-008 & DEC-010: koordinat GeoJSON berurutan [longitude, latitude].
     *
     * @param  array<string, mixed>|null  $geoJson
     */
    public function distanceToLineStringMeters(float $lat, float $lng, ?array $geoJson): ?float
    {
        if (
            $geoJson === null
            || ($geoJson['type'] ?? null) !== 'LineString'
            || ! is_array($geoJson['coordinates'] ?? null)
            || count($geoJson['coordinates']) < 2
        ) {
            return null;
        }

        $coords = $geoJson['coordinates'];
        $cosLat = cos(deg2rad($lat));
        $metersPerDegLat = 111132.92;
        $metersPerDegLng = 111412.84 * $cosLat;

        $minDistanceSq = null;

        for ($i = 0, $n = count($coords) - 1; $i < $n; $i++) {
            $p1 = $coords[$i];
            $p2 = $coords[$i + 1];

            if (! is_array($p1) || ! is_array($p2) || count($p1) < 2 || count($p2) < 2) {
                continue;
            }

            // GeoJSON menggunakan urutan [longitude, latitude]
            $x1 = ((float) $p1[0] - $lng) * $metersPerDegLng;
            $y1 = ((float) $p1[1] - $lat) * $metersPerDegLat;
            $x2 = ((float) $p2[0] - $lng) * $metersPerDegLng;
            $y2 = ((float) $p2[1] - $lat) * $metersPerDegLat;

            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $lenSq = $dx * $dx + $dy * $dy;

            if ($lenSq <= 1e-12) {
                $distSq = $x1 * $x1 + $y1 * $y1;
            } else {
                // Proyeksi titik (0,0) ke ruas (x1,y1)->(x2,y2)
                $t = max(0.0, min(1.0, (-($x1 * $dx + $y1 * $dy)) / $lenSq));
                $projX = $x1 + $t * $dx;
                $projY = $y1 + $t * $dy;
                $distSq = $projX * $projX + $projY * $projY;
            }

            if ($minDistanceSq === null || $distSq < $minDistanceSq) {
                $minDistanceSq = $distSq;
            }
        }

        return $minDistanceSq !== null ? round(sqrt($minDistanceSq), 2) : null;
    }

    /**
     * Mem-parsing parameter bbox dengan format "minLng,minLat,maxLng,maxLat".
     *
     * @return array{min_lng: float, min_lat: float, max_lng: float, max_lat: float}|null
     */
    public function parseBoundingBox(?string $bbox): ?array
    {
        if ($bbox === null || trim($bbox) === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $bbox));
        if (count($parts) !== 4) {
            return null;
        }

        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                return null;
            }
        }

        $minLng = (float) $parts[0];
        $minLat = (float) $parts[1];
        $maxLng = (float) $parts[2];
        $maxLat = (float) $parts[3];

        if (
            $minLat < -90.0 || $maxLat > 90.0 || $minLat > $maxLat
            || $minLng < -180.0 || $maxLng > 180.0 || $minLng > $maxLng
        ) {
            return null;
        }

        return [
            'min_lng' => $minLng,
            'min_lat' => $minLat,
            'max_lng' => $maxLng,
            'max_lat' => $maxLat,
        ];
    }

    /**
     * Mengevaluasi apakah titik berada di dalam poligon wilayah studi (jika tersedia)
     * atau kotak referensi BSD/Tangerang (PRD 04 §4.2 & §7.3).
     *
     * @return array{within_reference_area: bool, boundary_source: string, warning: string|null}
     */
    public function evaluateStudyAreaLocation(float $lat, float $lng): array
    {
        $studyAreasWithPolygon = AdministrativeArea::query()
            ->where('is_study_area', true)
            ->whereNotNull('boundary_geojson')
            ->get();

        if ($studyAreasWithPolygon->isNotEmpty()) {
            foreach ($studyAreasWithPolygon as $area) {
                if ($this->pointInGeoJsonPolygon($lat, $lng, $area->boundary_geojson)) {
                    return [
                        'within_reference_area' => true,
                        'boundary_source' => 'verified_polygon:' . $area->name,
                        'warning' => null,
                    ];
                }
            }

            return [
                'within_reference_area' => false,
                'boundary_source' => 'verified_polygon',
                'warning' => 'Titik berada di luar poligon batas wilayah studi administratif yang terdaftar.',
            ];
        }

        /** @var array{min_lat: float, max_lat: float, min_lng: float, max_lng: float} $bbox */
        $bbox = config('reporting.study_area_reference_bbox', [
            'min_lat' => -6.38,
            'max_lat' => -6.22,
            'min_lng' => 106.58,
            'max_lng' => 106.75,
        ]);

        $insideBbox = $lat >= $bbox['min_lat']
            && $lat <= $bbox['max_lat']
            && $lng >= $bbox['min_lng']
            && $lng <= $bbox['max_lng'];

        return [
            'within_reference_area' => $insideBbox,
            'boundary_source' => 'reference_bbox_bsd_tangerang',
            'warning' => $insideBbox
                ? null
                : 'Titik berada di luar kotak referensi wilayah studi BSD/Tangerang. Petugas perlu memverifikasi cakupan wilayah laporan ini.',
        ];
    }

    /**
     * Mencari kandidat segmen jalan terdekat untuk membantu verifikasi petugas (PRD 04 §6 & §8).
     *
     * @return array<int, array<string, mixed>>
     */
    public function suggestNearestSegments(
        float $lat,
        float $lng,
        float $maxDistanceM = 100.0,
        int $limit = 5,
        bool $includeDemo = false
    ): array {
        // Saring awal berdasarkan bounding box derajat (~0.01 derajat ~= 1.1 km)
        $degDelta = 0.015;
        $query = RoadSegment::query()
            ->where('is_active', true)
            ->whereBetween('centroid_latitude', [$lat - $degDelta, $lat + $degDelta])
            ->whereBetween('centroid_longitude', [$lng - $degDelta, $lng + $degDelta]);

        if (! $includeDemo) {
            $query->where('is_demo', false);
        }

        $candidates = [];
        foreach ($query->get() as $segment) {
            $dist = $this->distanceToLineStringMeters($lat, $lng, $segment->geometry_geojson);
            if ($dist === null && $segment->centroid_latitude !== null && $segment->centroid_longitude !== null) {
                $dist = $this->distanceMeters(
                    $lat,
                    $lng,
                    (float) $segment->centroid_latitude,
                    (float) $segment->centroid_longitude
                );
            }

            if ($dist !== null && $dist <= $maxDistanceM) {
                $candidates[] = [
                    'id' => $segment->id,
                    'road_reference_code' => $segment->road_reference_code,
                    'name' => $segment->name,
                    'road_function' => $segment->road_function,
                    'road_authority' => $segment->road_authority,
                    'distance_meters' => $dist,
                ];
            }
        }

        usort($candidates, fn (array $a, array $b) => $a['distance_meters'] <=> $b['distance_meters']);

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Menampilkan kandidat laporan lain yang berdekatan berdasarkan koordinat dan waktu
     * untuk membantu petugas memeriksa kemungkinan duplikat (PRD 04 §6).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findNearbyCandidateDuplicates(
        DamageReport $report,
        float $maxDistanceM = 50.0,
        int $windowDays = 30
    ): array {
        $degDelta = 0.005; // ~550 m
        $reportedAt = $report->reported_at ?? now();

        $nearby = DamageReport::query()
            ->where('id', '!=', $report->id)
            ->where('is_demo', (bool) $report->is_demo)
            ->whereBetween('latitude', [(float) $report->latitude - $degDelta, (float) $report->latitude + $degDelta])
            ->whereBetween('longitude', [(float) $report->longitude - $degDelta, (float) $report->longitude + $degDelta])
            ->whereBetween('reported_at', [
                $reportedAt->copy()->subDays($windowDays),
                $reportedAt->copy()->addDays($windowDays),
            ])
            ->orderByDesc('reported_at')
            ->limit(25)
            ->get();

        $candidates = [];
        foreach ($nearby as $other) {
            $dist = $this->distanceMeters(
                (float) $report->latitude,
                (float) $report->longitude,
                (float) $other->latitude,
                (float) $other->longitude
            );

            if ($dist <= $maxDistanceM) {
                $candidates[] = [
                    'id' => $other->id,
                    'report_code' => $other->report_code,
                    'damage_type' => $other->damage_type,
                    'status' => $other->status,
                    'distance_meters' => $dist,
                    'reported_at' => $other->reported_at?->toIso8601String(),
                ];
            }
        }

        usort($candidates, fn (array $a, array $b) => $a['distance_meters'] <=> $b['distance_meters']);

        return $candidates;
    }

    /**
     * Ray-casting algoritma point-in-polygon untuk GeoJSON Polygon / MultiPolygon.
     *
     * @param  array<string, mixed>|null  $geoJson
     */
    private function pointInGeoJsonPolygon(float $lat, float $lng, ?array $geoJson): bool
    {
        if ($geoJson === null || ! isset($geoJson['type'], $geoJson['coordinates'])) {
            return false;
        }

        if ($geoJson['type'] === 'Polygon' && is_array($geoJson['coordinates'][0] ?? null)) {
            return $this->pointInRing($lat, $lng, $geoJson['coordinates'][0]);
        }

        if ($geoJson['type'] === 'MultiPolygon' && is_array($geoJson['coordinates'])) {
            foreach ($geoJson['coordinates'] as $polygon) {
                if (is_array($polygon[0] ?? null) && $this->pointInRing($lat, $lng, $polygon[0])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, float|int>>  $ring
     */
    private function pointInRing(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) ($ring[$i][0] ?? 0.0);
            $yi = (float) ($ring[$i][1] ?? 0.0);
            $xj = (float) ($ring[$j][0] ?? 0.0);
            $yj = (float) ($ring[$j][1] ?? 0.0);

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
