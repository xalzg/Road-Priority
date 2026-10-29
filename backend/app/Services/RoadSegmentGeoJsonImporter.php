<?php

namespace App\Services;

use App\Models\AdministrativeArea;
use App\Models\RoadSegment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RoadSegmentGeoJsonImporter
{
    /**
     * Pemetaan opsional tag OSM `highway` ke klasifikasi fungsi jalan RoadPriority (D-03).
     */
    private const OSM_HIGHWAY_TO_FUNCTION = [
        'trunk' => 'arteri',
        'trunk_link' => 'arteri',
        'primary' => 'arteri',
        'primary_link' => 'arteri',
        'secondary' => 'kolektor',
        'secondary_link' => 'kolektor',
        'tertiary' => 'kolektor',
        'tertiary_link' => 'kolektor',
        'unclassified' => 'lokal',
        'residential' => 'lingkungan',
        'living_street' => 'lingkungan',
        'service' => 'lingkungan',
    ];

    public function __construct(
        private GeoGeometryService $geoGeometry,
        private AuditLogService $auditLogs
    ) {
    }

    /**
     * Memvalidasi dan mengimpor file GeoJSON FeatureCollection ke tabel `road_segments` (PRD 05.2).
     *
     * @param  array{
     *     dry_run?: bool,
     *     update_existing?: bool,
     *     atomic?: bool,
     *     enforce_study_bbox?: bool,
     *     source_name?: string|null,
     *     source_reference?: string|null,
     *     data_origin?: string
     * }  $options
     * @return array{
     *     file_path: string,
     *     dry_run: bool,
     *     atomic: bool,
     *     rolled_back: bool,
     *     total_features: int,
     *     valid_features: int,
     *     rejected_features: int,
     *     created_count: int,
     *     updated_count: int,
     *     unchanged_count: int,
     *     conflict_count: int,
     *     errors: array<int, array{feature_index: int|null, road_reference_code: string|null, reason: string}>,
     *     boundary_warnings: array<int, string>
     * }
     */
    public function importFromFile(string $filePath, array $options = []): array
    {
        $resolvedPath = $this->validateAndResolveFilePath($filePath);
        $rawJson = @file_get_contents($resolvedPath);
        if ($rawJson === false) {
            throw new InvalidArgumentException("File GeoJSON tidak dapat dibaca: {$filePath}");
        }

        $decoded = json_decode($rawJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new InvalidArgumentException(
                'File bukan JSON yang valid: ' . json_last_error_msg()
            );
        }

        return $this->importPayload($decoded, $options + ['file_path' => $resolvedPath]);
    }

    /**
     * Memvalidasi dan mengimpor struktur array GeoJSON yang sudah di-decode.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function importPayload(array $payload, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $updateExisting = (bool) ($options['update_existing'] ?? false);
        $atomic = array_key_exists('atomic', $options) ? (bool) $options['atomic'] : true;
        $enforceStudyBbox = (bool) ($options['enforce_study_bbox'] ?? false);
        $defaultSourceName = isset($options['source_name']) && trim((string) $options['source_name']) !== ''
            ? trim((string) $options['source_name'])
            : null;
        $defaultSourceRef = isset($options['source_reference']) && trim((string) $options['source_reference']) !== ''
            ? trim((string) $options['source_reference'])
            : null;
        $defaultDataOrigin = isset($options['data_origin']) && trim((string) $options['data_origin']) !== ''
            ? strtolower(trim((string) $options['data_origin']))
            : 'public_dataset';

        if (! in_array($defaultDataOrigin, RoadSegment::NON_DEMO_DATA_ORIGINS, true)) {
            throw new InvalidArgumentException(
                "data_origin ('{$defaultDataOrigin}') tidak valid pada importer data resmi. Nilai yang didukung: "
                . implode(', ', RoadSegment::NON_DEMO_DATA_ORIGINS) . '.'
            );
        }

        if (($payload['type'] ?? null) !== 'FeatureCollection') {
            throw new InvalidArgumentException(
                "Root GeoJSON wajib bertipe 'FeatureCollection' (ditemukan: '" . (string) ($payload['type'] ?? 'null') . "')."
            );
        }

        if (! isset($payload['features']) || ! is_array($payload['features']) || $payload['features'] === []) {
            throw new InvalidArgumentException(
                "GeoJSON FeatureCollection wajib memiliki array 'features' yang tidak kosong."
            );
        }

        $features = array_values($payload['features']);
        $totalFeatures = count($features);
        $seenCodesInFile = [];
        $preparedRows = [];
        $errors = [];
        $boundaryWarnings = [];

        foreach ($features as $index => $feature) {
            $validation = $this->validateAndNormalizeFeature(
                $index,
                $feature,
                $seenCodesInFile,
                $enforceStudyBbox,
                $defaultSourceName,
                $defaultSourceRef,
                $defaultDataOrigin
            );

            if ($validation['error'] !== null) {
                $errors[] = $validation['error'];
                continue;
            }

            $record = $validation['record'];
            $seenCodesInFile[$record['road_reference_code']] = $index;
            if ($validation['boundary_warning'] !== null) {
                $boundaryWarnings[] = $validation['boundary_warning'];
            }

            $preparedRows[] = [
                'feature_index' => $index,
                'record' => $record,
            ];
        }

        $validFeaturesCount = count($preparedRows);
        $rejectedFeaturesCount = count($errors);
        $createdCount = 0;
        $updatedCount = 0;
        $unchangedCount = 0;
        $conflictCount = 0;
        $rolledBack = false;

        // Periksa konflik terhadap database untuk setiap feature yang valid secara sintaksis
        $plannedOperations = [];
        foreach ($preparedRows as $item) {
            $idx = $item['feature_index'];
            $rec = $item['record'];
            $code = $rec['road_reference_code'];

            /** @var RoadSegment|null $existing */
            $existing = RoadSegment::query()->where('road_reference_code', $code)->first();

            if ($existing === null) {
                $plannedOperations[] = [
                    'action' => 'create',
                    'feature_index' => $idx,
                    'record' => $rec,
                    'existing' => null,
                ];
                continue;
            }

            // PRD 05.2 §4.C: Dilarang mengubah data demo menjadi data resmi atau menimpanya
            if ((bool) $existing->is_demo || $existing->data_origin === 'demo') {
                $conflictCount++;
                $errors[] = [
                    'feature_index' => $idx,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$idx} ({$code}): Konflik dengan segmen simulasi/demo yang sudah ada. Data demo tidak boleh diubah menjadi data resmi.",
                ];
                continue;
            }

            if ($this->isRecordEquivalentToSegment($rec, $existing)) {
                $plannedOperations[] = [
                    'action' => 'unchanged',
                    'feature_index' => $idx,
                    'record' => $rec,
                    'existing' => $existing,
                ];
                continue;
            }

            if (! $updateExisting) {
                $conflictCount++;
                $errors[] = [
                    'feature_index' => $idx,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$idx} ({$code}): Ruas jalan sudah ada di database dengan atribut/geometri berbeda. Gunakan opsi --update-existing jika ingin memperbarui data yang sudah ada.",
                ];
                continue;
            }

            $plannedOperations[] = [
                'action' => 'update',
                'feature_index' => $idx,
                'record' => $rec,
                'existing' => $existing,
            ];
        }

        $hasFailures = ($rejectedFeaturesCount > 0 || $conflictCount > 0);

        if ($dryRun) {
            foreach ($plannedOperations as $op) {
                if ($op['action'] === 'create') {
                    $createdCount++;
                } elseif ($op['action'] === 'update') {
                    $updatedCount++;
                } elseif ($op['action'] === 'unchanged') {
                    $unchangedCount++;
                }
            }

            return [
                'file_path' => (string) ($options['file_path'] ?? 'memory'),
                'dry_run' => true,
                'atomic' => $atomic,
                'rolled_back' => false,
                'total_features' => $totalFeatures,
                'valid_features' => $validFeaturesCount,
                'rejected_features' => $rejectedFeaturesCount,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'unchanged_count' => $unchangedCount,
                'conflict_count' => $conflictCount,
                'errors' => $errors,
                'boundary_warnings' => $boundaryWarnings,
            ];
        }

        // Dalam mode atomik (default), jika ada feature gagal validasi atau konflik, batalkan seluruh penulisan
        if ($atomic && $hasFailures) {
            return [
                'file_path' => (string) ($options['file_path'] ?? 'memory'),
                'dry_run' => false,
                'atomic' => true,
                'rolled_back' => true,
                'total_features' => $totalFeatures,
                'valid_features' => $validFeaturesCount,
                'rejected_features' => $rejectedFeaturesCount,
                'created_count' => 0,
                'updated_count' => 0,
                'unchanged_count' => 0,
                'conflict_count' => $conflictCount,
                'errors' => $errors,
                'boundary_warnings' => $boundaryWarnings,
            ];
        }

        try {
            DB::transaction(function () use (
                $plannedOperations,
                &$createdCount,
                &$updatedCount,
                &$unchangedCount,
                $options
            ): void {
                foreach ($plannedOperations as $op) {
                    $rec = $op['record'];
                    if ($op['action'] === 'create') {
                        RoadSegment::query()->create($rec);
                        $createdCount++;
                    } elseif ($op['action'] === 'update') {
                        /** @var RoadSegment $existing */
                        $existing = $op['existing'];
                        $existing->fill($rec);
                        $existing->is_demo = false;
                        $existing->save();
                        $updatedCount++;
                    } elseif ($op['action'] === 'unchanged') {
                        $unchangedCount++;
                    }
                }

                if ($createdCount > 0 || $updatedCount > 0) {
                    $this->auditLogs->record(
                        actor: null,
                        action: 'road_segments.geojson_imported',
                        entityType: 'road_segment',
                        entityId: null,
                        changesSummary: [
                            'file_path' => basename((string) ($options['file_path'] ?? 'memory')),
                            'created_count' => $createdCount,
                            'updated_count' => $updatedCount,
                            'unchanged_count' => $unchangedCount,
                        ]
                    );
                }
            });
        } catch (Throwable $e) {
            $rolledBack = true;
            $createdCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;
            $errors[] = [
                'feature_index' => null,
                'road_reference_code' => null,
                'reason' => 'Transaksi database dibatalkan (rollback): ' . $e->getMessage(),
            ];
        }

        return [
            'file_path' => (string) ($options['file_path'] ?? 'memory'),
            'dry_run' => false,
            'atomic' => $atomic,
            'rolled_back' => $rolledBack,
            'total_features' => $totalFeatures,
            'valid_features' => $validFeaturesCount,
            'rejected_features' => $rejectedFeaturesCount,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'unchanged_count' => $unchangedCount,
            'conflict_count' => $conflictCount,
            'errors' => $errors,
            'boundary_warnings' => $boundaryWarnings,
        ];
    }

    /**
     * Memvalidasi path file agar tidak membaca lokasi sensitif di luar proyek atau direktori sementara uji.
     */
    private function validateAndResolveFilePath(string $filePath): string
    {
        if (trim($filePath) === '' || str_contains($filePath, "\0")) {
            throw new InvalidArgumentException('Path file GeoJSON tidak valid.');
        }

        $candidate = $filePath;
        if (! is_file($candidate)) {
            $relativeToBase = base_path($filePath);
            $relativeToRepo = dirname(base_path()) . DIRECTORY_SEPARATOR . ltrim($filePath, '/\\');
            if (is_file($relativeToBase)) {
                $candidate = $relativeToBase;
            } elseif (is_file($relativeToRepo)) {
                $candidate = $relativeToRepo;
            }
        }

        $realPath = @realpath($candidate);
        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw new InvalidArgumentException("File GeoJSON tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $allowedRoots = array_filter([
            @realpath(dirname(base_path())),
            @realpath(base_path()),
            @realpath(storage_path()),
            @realpath(sys_get_temp_dir()),
        ]);

        $normalizedTarget = strtolower(str_replace('\\', '/', $realPath));
        $isAllowed = false;
        foreach ($allowedRoots as $root) {
            $normalizedRoot = rtrim(strtolower(str_replace('\\', '/', (string) $root)), '/') . '/';
            if (str_starts_with($normalizedTarget, $normalizedRoot)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            throw new InvalidArgumentException(
                'Akses ditolak: File GeoJSON harus berada di dalam direktori proyek RoadPriority, storage, atau direktori sementara pengujian.'
            );
        }

        return $realPath;
    }

    /**
     * Memvalidasi satu feature GeoJSON dan menormalisasinya ke struktur kolom `road_segments`.
     *
     * @param  mixed  $feature
     * @param  array<string, int>  $seenCodesInFile
     * @return array{
     *     record: array<string, mixed>|null,
     *     error: array{feature_index: int, road_reference_code: string|null, reason: string}|null,
     *     boundary_warning: string|null
     * }
     */
    private function validateAndNormalizeFeature(
        int $index,
        $feature,
        array $seenCodesInFile,
        bool $enforceStudyBbox,
        ?string $defaultSourceName,
        ?string $defaultSourceRef,
        string $defaultDataOrigin
    ): array {
        if (! is_array($feature) || ($feature['type'] ?? null) !== 'Feature') {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => null,
                    'reason' => "Feature #{$index}: Elemen harus bertipe 'Feature'.",
                ],
                'boundary_warning' => null,
            ];
        }

        $props = $feature['properties'] ?? null;
        if (! is_array($props)) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => null,
                    'reason' => "Feature #{$index}: Objek 'properties' wajib tersedia.",
                ],
                'boundary_warning' => null,
            ];
        }

        // 1. Identitas stabil ruas jalan (road_reference_code)
        $rawCode = $props['road_reference_code'] ?? $props['segment_code'] ?? null;
        if (! is_string($rawCode) && ! is_int($rawCode)) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => null,
                    'reason' => "Feature #{$index}: Properti wajib 'road_reference_code' tidak ditemukan.",
                ],
                'boundary_warning' => null,
            ];
        }

        $code = trim((string) $rawCode);
        if ($code === '' || strlen($code) > 64 || ! preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code !== '' ? $code : null,
                    'reason' => "Feature #{$index}: Nilai 'road_reference_code' ('{$code}') tidak valid (wajib 1–64 karakter alfanumerik/tanda hubung).",
                ],
                'boundary_warning' => null,
            ];
        }

        if (array_key_exists($code, $seenCodesInFile)) {
            $firstIdx = $seenCodesInFile[$code];

            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Duplikat 'road_reference_code' di dalam file yang sama (sudah muncul pada Feature #{$firstIdx}).",
                ],
                'boundary_warning' => null,
            ];
        }

        // 2. Nama ruas jalan (wajib)
        $name = isset($props['name']) && is_string($props['name']) ? trim($props['name']) : '';
        if ($name === '' || strlen($name) > 191) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Properti wajib 'name' kosong atau melebihi 191 karakter.",
                ],
                'boundary_warning' => null,
            ];
        }

        // 3. Fungsi jalan (road_function atau pemetaan eksplisit dari tag OSM highway)
        $roadFunction = null;
        if (isset($props['road_function']) && is_string($props['road_function']) && trim($props['road_function']) !== '') {
            $candidateFunc = strtolower(trim($props['road_function']));
            if (! in_array($candidateFunc, RoadSegment::FUNCTIONS, true)) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Nilai 'road_function' ('{$props['road_function']}') tidak valid. Nilai yang didukung: " . implode(', ', RoadSegment::FUNCTIONS) . '.',
                    ],
                    'boundary_warning' => null,
                ];
            }
            $roadFunction = $candidateFunc;
        } elseif (isset($props['highway']) && is_string($props['highway'])) {
            $hw = strtolower(trim($props['highway']));
            $roadFunction = self::OSM_HIGHWAY_TO_FUNCTION[$hw] ?? null;
            if ($roadFunction === null) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Tag OSM 'highway' ('{$props['highway']}') tidak termasuk kelas jalan kendaraan yang didukung.",
                    ],
                    'boundary_warning' => null,
                ];
            }
        } else {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Properti wajib 'road_function' (atau tag 'highway' yang didukung) tidak ditemukan.",
                ],
                'boundary_warning' => null,
            ];
        }

        // 4. Kewenangan jalan (road_authority)
        $roadAuthority = 'belum_terverifikasi';
        if (isset($props['road_authority']) && $props['road_authority'] !== null && $props['road_authority'] !== '') {
            if (! is_string($props['road_authority'])) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Properti 'road_authority' harus berupa string.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $candidateAuth = strtolower(trim($props['road_authority']));
            if (! in_array($candidateAuth, RoadSegment::AUTHORITIES, true)) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Nilai 'road_authority' ('{$props['road_authority']}') tidak valid.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $roadAuthority = $candidateAuth;
        }

        // 5. Jarak ke fasilitas publik terdekat (nearest_facility_meters — opsional/nullable, DILARANG diisi angka fiktif)
        $nearestFacilityMeters = null;
        if (array_key_exists('nearest_facility_meters', $props) && $props['nearest_facility_meters'] !== null && $props['nearest_facility_meters'] !== '') {
            if (! is_numeric($props['nearest_facility_meters']) || (float) $props['nearest_facility_meters'] < 0.0) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Nilai 'nearest_facility_meters' harus berupa angka >= 0.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $nearestFacilityMeters = round((float) $props['nearest_facility_meters'], 2);
        }

        // 6. Wilayah administratif opsional
        $administrativeAreaId = null;
        if (isset($props['administrative_area_external_code']) && is_string($props['administrative_area_external_code']) && trim($props['administrative_area_external_code']) !== '') {
            $area = AdministrativeArea::query()
                ->where('external_code', trim($props['administrative_area_external_code']))
                ->first();
            if ($area === null) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): 'administrative_area_external_code' ('{$props['administrative_area_external_code']}') tidak ditemukan.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $administrativeAreaId = $area->id;
        }

        // 7. Validasi Geometri (wajib LineString dengan koordinat [longitude, latitude])
        $geometry = $feature['geometry'] ?? null;
        if (! is_array($geometry) || ! isset($geometry['type'])) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Objek 'geometry' wajib tersedia.",
                ],
                'boundary_warning' => null,
            ];
        }

        $geomType = (string) $geometry['type'];
        if ($geomType !== 'LineString') {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Tipe geometri '{$geomType}' tidak didukung (hanya 'LineString' yang didukung).",
                ],
                'boundary_warning' => null,
            ];
        }

        $rawCoords = $geometry['coordinates'] ?? null;
        if (! is_array($rawCoords) || count($rawCoords) < 2) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): LineString wajib memiliki minimal 2 titik koordinat [longitude, latitude].",
                ],
                'boundary_warning' => null,
            ];
        }

        $normalizedCoords = [];
        $sumLat = 0.0;
        $sumLng = 0.0;
        $computedLengthM = 0.0;
        $outsideStudyArea = false;

        foreach ($rawCoords as $ptIdx => $pt) {
            if (! is_array($pt) || count($pt) < 2 || ! is_numeric($pt[0]) || ! is_numeric($pt[1])) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Koordinat pada titik #{$ptIdx} bukan pasangan angka [longitude, latitude] yang valid.",
                    ],
                    'boundary_warning' => null,
                ];
            }

            $lng = (float) $pt[0];
            $lat = (float) $pt[1];

            if (! is_finite($lng) || ! is_finite($lat) || $lng < -180.0 || $lng > 180.0 || $lat < -90.0 || $lat > 90.0) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Koordinat titik #{$ptIdx} ([{$lng}, {$lat}]) berada di luar batas geografis WGS 84 (longitude [-180, 180], latitude [-90, 90]).",
                    ],
                    'boundary_warning' => null,
                ];
            }

            $areaCheck = $this->geoGeometry->evaluateStudyAreaLocation($lat, $lng);
            if (! $areaCheck['within_reference_area']) {
                $outsideStudyArea = true;
                if ($enforceStudyBbox) {
                    return [
                        'record' => null,
                        'error' => [
                            'feature_index' => $index,
                            'road_reference_code' => $code,
                            'reason' => "Feature #{$index} ({$code}): Koordinat titik #{$ptIdx} ([{$lng}, {$lat}]) berada di luar batas wilayah studi/kotak referensi yang ditetapkan.",
                        ],
                        'boundary_warning' => null,
                    ];
                }
            }

            if ($ptIdx > 0) {
                $prev = $normalizedCoords[$ptIdx - 1];
                $computedLengthM += $this->geoGeometry->distanceMeters($prev[1], $prev[0], $lat, $lng);
            }

            // Simpan tetap dalam urutan standar GeoJSON [longitude, latitude]
            $normalizedCoords[] = [round($lng, 7), round($lat, 7)];
            $sumLng += $lng;
            $sumLat += $lat;
        }

        if ($computedLengthM <= 0.0) {
            return [
                'record' => null,
                'error' => [
                    'feature_index' => $index,
                    'road_reference_code' => $code,
                    'reason' => "Feature #{$index} ({$code}): Geometri LineString degenerat (panjang ruas 0 meter).",
                ],
                'boundary_warning' => null,
            ];
        }

        $lengthMeters = round($computedLengthM, 2);
        if (array_key_exists('length_meters', $props) && $props['length_meters'] !== null && $props['length_meters'] !== '') {
            if (! is_numeric($props['length_meters']) || (float) $props['length_meters'] <= 0.0) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Properti 'length_meters' harus berupa angka > 0.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $lengthMeters = round((float) $props['length_meters'], 2);
        }

        $countPts = count($normalizedCoords);
        $centroidLat = round($sumLat / $countPts, 7);
        $centroidLng = round($sumLng / $countPts, 7);

        $sourceName = isset($props['source_name']) && is_string($props['source_name']) && trim($props['source_name']) !== ''
            ? trim($props['source_name'])
            : $defaultSourceName;
        $sourceRef = isset($props['source_reference']) && is_string($props['source_reference']) && trim($props['source_reference']) !== ''
            ? trim($props['source_reference'])
            : $defaultSourceRef;

        $featureDataOrigin = $defaultDataOrigin;
        if (isset($props['data_origin']) && $props['data_origin'] !== null && $props['data_origin'] !== '') {
            if (! is_string($props['data_origin'])) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Properti 'data_origin' harus berupa string.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $candidateOrigin = strtolower(trim($props['data_origin']));
            if (! in_array($candidateOrigin, RoadSegment::NON_DEMO_DATA_ORIGINS, true)) {
                return [
                    'record' => null,
                    'error' => [
                        'feature_index' => $index,
                        'road_reference_code' => $code,
                        'reason' => "Feature #{$index} ({$code}): Nilai 'data_origin' ('{$props['data_origin']}') tidak valid untuk impor data resmi.",
                    ],
                    'boundary_warning' => null,
                ];
            }
            $featureDataOrigin = $candidateOrigin;
        }

        $boundaryWarning = $outsideStudyArea
            ? "Feature #{$index} ({$code}) memiliki titik di luar kotak referensi BSD/Tangerang (catatan: kotak referensi bukan batas administratif resmi BIG)."
            : null;

        return [
            'record' => [
                'administrative_area_id' => $administrativeAreaId,
                'road_reference_code' => $code,
                'name' => $name,
                'geometry_geojson' => [
                    'type' => 'LineString',
                    'coordinates' => $normalizedCoords,
                ],
                'centroid_latitude' => $centroidLat,
                'centroid_longitude' => $centroidLng,
                'length_meters' => $lengthMeters,
                'road_function' => $roadFunction,
                'road_authority' => $roadAuthority,
                'nearest_facility_meters' => $nearestFacilityMeters,
                'source_name' => $sourceName,
                'source_reference' => $sourceRef,
                'data_origin' => $featureDataOrigin,
                'is_demo' => false,
                'is_active' => isset($props['is_active']) ? (bool) $props['is_active'] : true,
            ],
            'error' => null,
            'boundary_warning' => $boundaryWarning,
        ];
    }

    /**
     * Membandingkan apakah data ruas yang akan diimpor identik dengan baris `RoadSegment` yang sudah ada.
     *
     * @param  array<string, mixed>  $record
     */
    private function isRecordEquivalentToSegment(array $record, RoadSegment $segment): bool
    {
        $existingCoords = $segment->geometry_geojson['coordinates'] ?? null;
        $newCoords = $record['geometry_geojson']['coordinates'] ?? null;

        $sameFacility = ($record['nearest_facility_meters'] === null && $segment->nearest_facility_meters === null)
            || (
                $record['nearest_facility_meters'] !== null
                && $segment->nearest_facility_meters !== null
                && abs((float) $record['nearest_facility_meters'] - (float) $segment->nearest_facility_meters) < 0.01
            );

        $sameAdminArea = ($record['administrative_area_id'] ?? null) === ($segment->administrative_area_id !== null ? (int) $segment->administrative_area_id : null);
        $sameSourceName = (string) ($record['source_name'] ?? '') === (string) ($segment->source_name ?? '');
        $sameSourceRef = (string) ($record['source_reference'] ?? '') === (string) ($segment->source_reference ?? '');
        $sameDataOrigin = (string) ($record['data_origin'] ?? '') === (string) ($segment->data_origin ?? '');

        return (string) $record['name'] === (string) $segment->name
            && (string) $record['road_function'] === (string) $segment->road_function
            && (string) $record['road_authority'] === (string) $segment->road_authority
            && abs((float) $record['length_meters'] - (float) $segment->length_meters) < 0.01
            && $sameFacility
            && $sameAdminArea
            && $sameSourceName
            && $sameSourceRef
            && $sameDataOrigin
            && json_encode($newCoords) === json_encode($existingCoords);
    }
}

