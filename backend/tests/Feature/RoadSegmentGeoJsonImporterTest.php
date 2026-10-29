<?php

namespace Tests\Feature;

use App\Models\DamageAssessment;
use App\Models\DecisionRun;
use App\Models\RoadSegment;
use App\Models\User;
use App\Services\RoadSegmentGeoJsonImporter;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class RoadSegmentGeoJsonImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function writeTempGeoJson(string $rawContent, string $suffix = '.geojson'): string
    {
        $path = storage_path('app/test_import_' . uniqid('', true) . $suffix);
        file_put_contents($path, $rawContent);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * 1. File GeoJSON valid dengan beberapa ruas jalan berhasil diimpor.
     */
    public function test_valid_geojson_imports_multiple_road_segments_accurately(): void
    {
        $fixturePath = base_path('database/fixtures/synthetic_sample_segments.geojson');

        $exitCode = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $fixturePath,
            '--source-name' => 'OpenStreetMap Sample',
            '--source-reference' => 'ODbL 1.0',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertSame(3, RoadSegment::query()->where('is_demo', false)->count());

        $seg1 = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-001')->firstOrFail();
        $this->assertSame('[CONTOH UJI] Ruas Arteri Uji Importer A', $seg1->name);
        $this->assertSame('arteri', $seg1->road_function);
        $this->assertSame('kota', $seg1->road_authority);
        $this->assertSame(150.5, (float) $seg1->nearest_facility_meters);
        $this->assertFalse((bool) $seg1->is_demo);
        $this->assertSame('public_dataset', $seg1->data_origin);
        $this->assertGreaterThan(700.0, (float) $seg1->length_meters);
        $this->assertSame('LineString', $seg1->geometry_geojson['type']);
        // Pastikan urutan [longitude, latitude] tidak tertukar
        $this->assertSame([106.652, -6.302], $seg1->geometry_geojson['coordinates'][0]);
        $this->assertEqualsWithDelta(-6.300, (float) $seg1->centroid_latitude, 0.001);
        $this->assertEqualsWithDelta(106.655, (float) $seg1->centroid_longitude, 0.001);

        // SYNTH-SEG-002 menggunakan tag OSM `highway: secondary` -> dipetakan ke `kolektor`, dan nearest_facility_meters = null
        $seg2 = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-002')->firstOrFail();
        $this->assertSame('kolektor', $seg2->road_function);
        $this->assertSame('kabupaten', $seg2->road_authority);
        $this->assertNull($seg2->nearest_facility_meters);

        // SYNTH-SEG-003 menggunakan length_meters eksplisit 320.0
        $seg3 = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-003')->firstOrFail();
        $this->assertSame('lokal', $seg3->road_function);
        $this->assertSame('belum_terverifikasi', $seg3->road_authority);
        $this->assertSame(320.0, (float) $seg3->length_meters);
        $this->assertSame(85.0, (float) $seg3->nearest_facility_meters);
    }

    /**
     * 2. File rusak, path tidak valid, atau bukan FeatureCollection ditolak.
     */
    public function test_corrupted_json_or_non_feature_collection_is_rejected(): void
    {
        $corruptedPath = $this->writeTempGeoJson('{not-valid-json');
        $exitCorrupt = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $corruptedPath,
        ]);
        $this->assertSame(1, $exitCorrupt);
        $this->assertStringContainsString('File bukan JSON yang valid', Artisan::output());

        $wrongRootPath = $this->writeTempGeoJson((string) json_encode([
            'type' => 'Feature',
            'properties' => [],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[106.65, -6.30], [106.66, -6.30]]],
        ]));
        $exitWrongRoot = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $wrongRootPath,
        ]);
        $this->assertSame(1, $exitWrongRoot);
        $this->assertStringContainsString("Root GeoJSON wajib bertipe 'FeatureCollection'", Artisan::output());

        $emptyFeaturesPath = $this->writeTempGeoJson((string) json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ]));
        $exitEmpty = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $emptyFeaturesPath,
        ]);
        $this->assertSame(1, $exitEmpty);
        $this->assertStringContainsString("wajib memiliki array 'features' yang tidak kosong", Artisan::output());

        // Path yang tidak ada
        $exitMissing = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => 'storage/app/non_existent_12345.geojson',
        ]);
        $this->assertSame(1, $exitMissing);
        $this->assertSame(0, RoadSegment::query()->count());
    }

    /**
     * 3. Geometri tidak didukung (Point, Polygon, MultiLineString) ditolak dengan alasan jelas.
     */
    public function test_unsupported_geometries_are_rejected_with_feature_index_and_reason(): void
    {
        $payload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'BAD-GEOM-POINT',
                        'name' => 'Titik Saja',
                        'road_function' => 'lokal',
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [106.65, -6.30],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'BAD-GEOM-POLY',
                        'name' => 'Poligon',
                        'road_function' => 'lokal',
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[106.65, -6.30], [106.66, -6.30], [106.66, -6.31], [106.65, -6.30]]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'BAD-GEOM-MLS',
                        'name' => 'MultiLineString',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'MultiLineString',
                        'coordinates' => [
                            [[106.65, -6.30], [106.66, -6.30]],
                            [[106.67, -6.30], [106.68, -6.30]],
                        ],
                    ],
                ],
            ],
        ];

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);
        $summary = $importer->importPayload($payload);

        $this->assertSame(3, $summary['total_features']);
        $this->assertSame(0, $summary['valid_features']);
        $this->assertSame(3, $summary['rejected_features']);
        $this->assertSame(0, $summary['created_count']);
        $this->assertCount(3, $summary['errors']);
        $this->assertStringContainsString("Feature #0 (BAD-GEOM-POINT): Tipe geometri 'Point' tidak didukung", $summary['errors'][0]['reason']);
        $this->assertStringContainsString("Feature #1 (BAD-GEOM-POLY): Tipe geometri 'Polygon' tidak didukung", $summary['errors'][1]['reason']);
        $this->assertStringContainsString("Feature #2 (BAD-GEOM-MLS): Tipe geometri 'MultiLineString' tidak didukung", $summary['errors'][2]['reason']);
        $this->assertSame(0, RoadSegment::query()->count());
    }

    /**
     * 4. Koordinat di luar rentang geografis, kurang dari 2 titik, degenerat 0 meter, atau di luar bbox studi ditolak.
     */
    public function test_invalid_coordinates_and_study_bbox_enforcement(): void
    {
        $payload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'COORD-OUT-OF-RANGE',
                        'name' => 'Koordinat Luar Rentang WGS84',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [195.0, -6.30], // longitude > 180
                            [106.65, -95.0], // latitude < -90
                        ],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'COORD-SINGLE-PT',
                        'name' => 'Hanya Satu Titik',
                        'road_function' => 'kolektor',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [106.65, -6.30],
                        ],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'COORD-ZERO-LENGTH',
                        'name' => 'Titik Identik Panjang Nol',
                        'road_function' => 'lokal',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [106.65, -6.30],
                            [106.65, -6.30],
                        ],
                    ],
                ],
            ],
        ];

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);
        $summary = $importer->importPayload($payload);

        $this->assertSame(3, $summary['rejected_features']);
        $this->assertStringContainsString('di luar batas geografis WGS 84', $summary['errors'][0]['reason']);
        $this->assertStringContainsString('minimal 2 titik koordinat', $summary['errors'][1]['reason']);
        $this->assertStringContainsString('degenerat (panjang ruas 0 meter)', $summary['errors'][2]['reason']);

        // Uji --enforce-study-bbox untuk koordinat yang valid di WGS 84 tetapi di luar kotak referensi BSD/Tangerang
        $outsideStudyPayload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'OUTSIDE-BBOX-001',
                        'name' => 'Ruas Luar Wilayah Studi',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [107.619, -6.917], // Bandung
                            [107.621, -6.918],
                        ],
                    ],
                ],
            ],
        ];

        $rejectedBbox = $importer->importPayload($outsideStudyPayload, ['enforce_study_bbox' => true]);
        $this->assertSame(1, $rejectedBbox['rejected_features']);
        $this->assertStringContainsString('di luar batas wilayah studi', $rejectedBbox['errors'][0]['reason']);

        // Tanpa enforce_study_bbox, dicatat sebagai boundary_warnings namun tetap valid secara geografis
        $warningOnly = $importer->importPayload($outsideStudyPayload, [
            'enforce_study_bbox' => false,
            'dry_run' => true,
        ]);
        $this->assertSame(1, $warningOnly['valid_features']);
        $this->assertNotEmpty($warningOnly['boundary_warnings']);
    }

    /**
     * 5. Properti wajib hilang (road_reference_code, name, road_function) ditolak.
     */
    public function test_missing_or_invalid_required_properties_are_rejected(): void
    {
        $payload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'name' => 'Tanpa Kode Referensi',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.65, -6.30], [106.66, -6.30]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'MISSING-NAME-001',
                        'name' => '   ',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.65, -6.30], [106.66, -6.30]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'MISSING-FUNC-001',
                        'name' => 'Tanpa Fungsi Jalan',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.65, -6.30], [106.66, -6.30]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'INVALID-FUNC-001',
                        'name' => 'Fungsi Jalan Salah',
                        'road_function' => 'jalan_tol_bebas_hambatan',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.65, -6.30], [106.66, -6.30]],
                    ],
                ],
            ],
        ];

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);
        $summary = $importer->importPayload($payload);

        $this->assertSame(4, $summary['rejected_features']);
        $this->assertSame(0, $summary['valid_features']);
        $this->assertStringContainsString("Properti wajib 'road_reference_code' tidak ditemukan", $summary['errors'][0]['reason']);
        $this->assertStringContainsString("Properti wajib 'name' kosong", $summary['errors'][1]['reason']);
        $this->assertStringContainsString("Properti wajib 'road_function'", $summary['errors'][2]['reason']);
        $this->assertStringContainsString("Nilai 'road_function' ('jalan_tol_bebas_hambatan') tidak valid", $summary['errors'][3]['reason']);
    }

    /**
     * 6. Duplikat road_reference_code dalam satu file GeoJSON ditolak.
     */
    public function test_duplicate_road_reference_code_within_same_file_is_rejected(): void
    {
        $payload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'DUP-CODE-001',
                        'name' => 'Ruas Pertama',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.650, -6.300], [106.655, -6.300]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'DUP-CODE-001',
                        'name' => 'Ruas Duplikat Kedua',
                        'road_function' => 'kolektor',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.660, -6.305], [106.665, -6.305]],
                    ],
                ],
            ],
        ];

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);
        $summary = $importer->importPayload($payload);

        $this->assertSame(2, $summary['total_features']);
        $this->assertSame(1, $summary['valid_features']);
        $this->assertSame(1, $summary['rejected_features']);
        $this->assertTrue($summary['rolled_back']);
        $this->assertSame(0, $summary['created_count']);
        $this->assertStringContainsString("Duplikat 'road_reference_code' di dalam file yang sama (sudah muncul pada Feature #0)", $summary['errors'][0]['reason']);
        $this->assertSame(0, RoadSegment::query()->count());
    }

    /**
     * 7 & 8. Impor file yang sama dua kali bersifat idempoten (unchanged_count), dan perubahan atribut memerlukan --update-existing.
     */
    public function test_importing_same_file_twice_is_idempotent_and_updating_requires_explicit_flag(): void
    {
        $fixturePath = base_path('database/fixtures/synthetic_sample_segments.geojson');

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);

        $firstRun = $importer->importFromFile($fixturePath);
        $this->assertSame(3, $firstRun['created_count']);
        $this->assertSame(0, $firstRun['unchanged_count']);
        $this->assertSame(3, RoadSegment::query()->count());

        // Impor kedua dengan file yang sama persis -> unchanged_count = 3, tidak ada duplikat
        $secondRun = $importer->importFromFile($fixturePath);
        $this->assertSame(0, $secondRun['created_count']);
        $this->assertSame(0, $secondRun['updated_count']);
        $this->assertSame(3, $secondRun['unchanged_count']);
        $this->assertSame(0, $secondRun['conflict_count']);
        $this->assertFalse($secondRun['rolled_back']);
        $this->assertSame(3, RoadSegment::query()->count());

        // Ubah nama & nearest_facility_meters pada SYNTH-SEG-001 tanpa --update-existing -> konflik & rollback
        $modifiedPayload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'SYNTH-SEG-001',
                        'name' => '[CONTOH UJI] Ruas Arteri Uji Importer A (Diperbarui)',
                        'road_function' => 'arteri',
                        'road_authority' => 'kota',
                        'nearest_facility_meters' => 95.0,
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [106.652, -6.302],
                            [106.655, -6.300],
                            [106.658, -6.298],
                        ],
                    ],
                ],
            ],
        ];

        $conflictRun = $importer->importPayload($modifiedPayload, ['update_existing' => false]);
        $this->assertSame(1, $conflictRun['conflict_count']);
        $this->assertTrue($conflictRun['rolled_back']);
        $this->assertSame(0, $conflictRun['updated_count']);
        $this->assertSame('[CONTOH UJI] Ruas Arteri Uji Importer A', RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-001')->value('name'));

        // Dengan update_existing = true -> berhasil diperbarui
        $updateRun = $importer->importPayload($modifiedPayload, ['update_existing' => true]);
        $this->assertSame(0, $updateRun['conflict_count']);
        $this->assertFalse($updateRun['rolled_back']);
        $this->assertSame(1, $updateRun['updated_count']);
        $updatedSeg = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-001')->firstOrFail();
        $this->assertSame('[CONTOH UJI] Ruas Arteri Uji Importer A (Diperbarui)', $updatedSeg->name);
        $this->assertSame(95.0, (float) $updatedSeg->nearest_facility_meters);
    }

    /**
     * 9. Kegagalan di tengah impor membatalkan penulisan secara atomik (default), sedangkan --allow-partial dan --dry-run bekerja sesuai kontrak.
     */
    public function test_mid_import_failure_atomic_rollback_vs_allow_partial_and_dry_run(): void
    {
        $mixedPayload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'MIXED-VALID-001',
                        'name' => 'Ruas Valid Pertama',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.650, -6.300], [106.655, -6.300]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'MIXED-INVALID-002',
                        'name' => 'Ruas Rusak Geometri',
                        'road_function' => 'kolektor',
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [106.660, -6.305],
                    ],
                ],
            ],
        ];

        $tempPath = $this->writeTempGeoJson((string) json_encode($mixedPayload));

        // Default (atomik): harus gagal (exit code 1) dan 0 baris tersimpan
        $exitAtomic = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $tempPath,
        ]);
        $this->assertSame(1, $exitAtomic);
        $this->assertSame(0, RoadSegment::query()->count());

        // Mode --dry-run pada fixture valid: exit code 0 dan 0 baris tersimpan di DB
        $exitDryRun = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => base_path('database/fixtures/synthetic_sample_segments.geojson'),
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $exitDryRun);
        $this->assertSame(0, RoadSegment::query()->count());

        // Mode --allow-partial pada file campuran: menyimpan feature yang valid (1 baris), namun exit code tetap 1 karena ada feature ditolak
        $exitPartial = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $tempPath,
            '--allow-partial' => true,
        ]);
        $this->assertSame(1, $exitPartial);
        $this->assertSame(1, RoadSegment::query()->where('road_reference_code', 'MIXED-VALID-001')->count());
    }

    /**
     * 10. Data demo tidak tertimpa dan tidak berubah menjadi data resmi meskipun road_reference_code sama.
     */
    public function test_demo_segments_are_protected_and_never_converted_to_official_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSimulationSeeder::class);

        $demoSegBefore = RoadSegment::query()->where('road_reference_code', 'DEMO-SEG-001')->firstOrFail();
        $this->assertTrue((bool) $demoSegBefore->is_demo);

        $attemptOverwriteDemoPayload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'road_reference_code' => 'DEMO-SEG-001',
                        'name' => 'Upaya Menimpa Segmen Demo Menjadi Resmi',
                        'road_function' => 'arteri',
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[106.650, -6.300], [106.655, -6.300]],
                    ],
                ],
            ],
        ];

        /** @var RoadSegmentGeoJsonImporter $importer */
        $importer = app(RoadSegmentGeoJsonImporter::class);
        $summary = $importer->importPayload($attemptOverwriteDemoPayload, [
            'update_existing' => true,
        ]);

        $this->assertSame(1, $summary['conflict_count']);
        $this->assertTrue($summary['rolled_back']);
        $this->assertSame(0, $summary['updated_count']);
        $this->assertStringContainsString('Konflik dengan segmen simulasi/demo yang sudah ada', $summary['errors'][0]['reason']);

        $demoSegAfter = RoadSegment::query()->where('road_reference_code', 'DEMO-SEG-001')->firstOrFail();
        $this->assertTrue((bool) $demoSegAfter->is_demo);
        $this->assertSame('demo', $demoSegAfter->data_origin);
        $this->assertNotSame('Upaya Menimpa Segmen Demo Menjadi Resmi', $demoSegAfter->name);

        // Pastikan opsi data_origin = 'demo' juga ditolak oleh importer resmi
        $this->expectException(InvalidArgumentException::class);
        $importer->importPayload($attemptOverwriteDemoPayload, [
            'data_origin' => 'demo',
        ]);
    }

    /**
     * 11 & 12 & 13. Segmen baru hasil impor tidak memiliki asesmen fiktif, tidak memicu AHP/TOPSIS otomatis,
     * nearest_facility_meters tetap null jika tidak tersedia, dan terintegrasi dengan GET /api/road-segments
     * serta GET /api/decision-data-readiness.
     */
    public function test_imported_segments_have_no_fictional_assessments_and_integrate_with_api_and_readiness(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSimulationSeeder::class);

        $this->withoutMockingConsoleOutput();
        $fixturePath = base_path('database/fixtures/synthetic_sample_segments.geojson');
        $exitCode = Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $fixturePath,
            '--json' => true,
        ]);
        $this->assertSame(0, $exitCode);

        $rawOutput = trim(Artisan::output());
        $jsonSummary = json_decode($rawOutput, true);
        $this->assertIsArray($jsonSummary, 'Raw Artisan output: ' . $rawOutput);
        $this->assertSame(3, $jsonSummary['total_features']);
        $this->assertSame(3, $jsonSummary['valid_features']);
        $this->assertSame(0, $jsonSummary['rejected_features']);
        $this->assertSame(3, $jsonSummary['created_count']);
        $this->assertSame(0, $jsonSummary['updated_count']);
        $this->assertSame(0, $jsonSummary['unchanged_count']);
        $this->assertSame(0, $jsonSummary['conflict_count']);

        $importedIds = RoadSegment::query()
            ->whereIn('road_reference_code', ['SYNTH-SEG-001', 'SYNTH-SEG-002', 'SYNTH-SEG-003'])
            ->pluck('id')
            ->all();

        // Tidak boleh ada DamageAssessment fiktif atau DecisionRun otomatis untuk segmen yang baru diimpor
        $this->assertSame(0, DamageAssessment::query()->whereIn('road_segment_id', $importedIds)->count());
        $this->assertSame(0, DecisionRun::query()->count());

        // Endpoint publik GET /api/road-segments hanya mengembalikan 3 segmen resmi (bukan 4 segmen demo)
        $publicRes = $this->getJson('/api/road-segments');
        $publicRes->assertOk();
        $returnedCodes = array_column($publicRes->json('data.items'), 'road_reference_code');
        $this->assertEqualsCanonicalizing(['SYNTH-SEG-001', 'SYNTH-SEG-002', 'SYNTH-SEG-003'], $returnedCodes);

        // Periksa GET /api/decision-data-readiness (default include_demo = false)
        $admin = User::query()->where('email', 'admin@roadpriority.local')->firstOrFail();
        $token = $admin->createToken('test-admin')->plainTextToken;

        $readinessRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/decision-data-readiness');
        $readinessRes->assertOk();

        $summary = $readinessRes->json('data.summary');
        $this->assertSame(3, $summary['total_active_segments']);
        $this->assertSame(0, $summary['eligible_segments_count']);
        $this->assertSame(3, $summary['incomplete_segments_count']);

        $incompleteByCode = collect($readinessRes->json('data.incomplete_segments'))
            ->keyBy('code');

        // SYNTH-SEG-001 sudah punya K5 (150.5m) dan K3 (arteri -> 4), tetapi belum punya asesmen kerusakan (K1, K2, K4)
        $reasonsSeg1 = array_column($incompleteByCode['SYNTH-SEG-001']['missing_or_invalid'], 'reason');
        $this->assertEqualsCanonicalizing(
            ['missing_or_invalid:K1', 'missing_or_invalid:K2', 'missing_or_invalid:K4'],
            $reasonsSeg1
        );

        // SYNTH-SEG-002 punya nearest_facility_meters = null -> wajib terdeteksi missing_or_invalid:K5 (tidak diisi angka fiktif!)
        $reasonsSeg2 = array_column($incompleteByCode['SYNTH-SEG-002']['missing_or_invalid'], 'reason');
        $this->assertEqualsCanonicalizing(
            ['missing_or_invalid:K1', 'missing_or_invalid:K2', 'missing_or_invalid:K4', 'missing_or_invalid:K5'],
            $reasonsSeg2
        );

        // Setelah petugas/admin mencatat asesmen teknis nyata pada SYNTH-SEG-001, segmen tersebut berpindah ke eligible_segments
        $seg1 = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-001')->firstOrFail();
        $assessRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $seg1->id,
                'severity_level' => 4.0,
                'safety_risk_score' => 4.0,
                'road_function_score' => 4.0,
                'impact_score' => 3.0,
                'assessment_source' => 'field_inspection',
                'notes' => 'Survei teknis ruas hasil impor GeoJSON.',
            ]);
        $assessRes->assertCreated();

        $readinessAfter = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/decision-data-readiness');
        $readinessAfter->assertOk();
        $this->assertSame(1, $readinessAfter->json('data.summary.eligible_segments_count'));
        $this->assertSame(2, $readinessAfter->json('data.summary.incomplete_segments_count'));
        $this->assertSame('SYNTH-SEG-001', $readinessAfter->json('data.eligible_segments.0.code'));
        $this->assertSame('public_dataset', $readinessAfter->json('data.eligible_segments.0.data_origin'));
        $this->assertSame('Fixture Pengujian Otomatis PRD 05.2', $readinessAfter->json('data.eligible_segments.0.source_name'));
        $this->assertSame('field_inspection', $readinessAfter->json('data.eligible_segments.0.assessment_source'));
        $this->assertSame('SDI-ADAPTED-1.0', $readinessAfter->json('data.eligible_segments.0.assessment_protocol'));
    }

    /**
     * 14. (PRD 05.3 §5 & §6) Provenance per-feature, keselarasan assessment_source ('field_survey', 'report_verification'),
     * serta isolasi tegas agar asesmen demo tidak bisa masuk ke segmen resmi dan sebaliknya.
     */
    public function test_prd_05_3_provenance_isolation_and_assessment_source_alignment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSimulationSeeder::class);

        $fixturePath = base_path('database/fixtures/synthetic_sample_segments.geojson');
        Artisan::call('roadpriority:import-segments', [
            'geojson_path' => $fixturePath,
        ]);

        $admin = User::query()->where('email', 'admin@roadpriority.local')->firstOrFail();
        $token = $admin->createToken('test-admin-prd053')->plainTextToken;

        $officialSeg = RoadSegment::query()->where('road_reference_code', 'SYNTH-SEG-001')->firstOrFail();
        $demoSeg = RoadSegment::query()->where('road_reference_code', 'DEMO-SEG-001')->firstOrFail();

        // 1. Segmen resmi menerima assessment_source = 'field_survey' (sesuai DATA_DICTIONARY.md & FIELD_PROTOCOL.md)
        $surveyRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $officialSeg->id,
                'severity_level' => 4.0,
                'safety_risk_score' => 3.0,
                'road_function_score' => 4.0,
                'impact_score' => 4.0,
                'assessment_source' => 'field_survey',
                'measurements' => [
                    'crack_area_pct' => 15.0,
                    'crack_width_mm' => 2.5,
                    'pothole_count_per_100m' => 8,
                    'rutting_depth_cm' => 1.5,
                ],
                'notes' => 'Survei lapangan sesuai protokol SDI-ADAPTED-1.0.',
            ]);
        $surveyRes->assertCreated()
            ->assertJsonPath('data.assessment_source', 'field_survey')
            ->assertJsonPath('data.protocol_version', 'SDI-ADAPTED-1.0')
            ->assertJsonPath('data.is_demo', false);

        // 2. Segmen resmi menolak asesmen berlabel demo (is_demo = true atau assessment_source = 'demo_fixture')
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $officialSeg->id,
                'severity_level' => 3.0,
                'safety_risk_score' => 3.0,
                'is_demo' => true,
            ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['is_demo']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $officialSeg->id,
                'severity_level' => 3.0,
                'safety_risk_score' => 3.0,
                'assessment_source' => 'demo_fixture',
            ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['assessment_source']]);

        // 3. Segmen demo menolak asesmen penelitian resmi (is_demo = false atau assessment_source = 'field_survey')
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $demoSeg->id,
                'severity_level' => 3.0,
                'safety_risk_score' => 3.0,
                'is_demo' => false,
            ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['is_demo']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/assessments', [
                'road_segment_id' => $demoSeg->id,
                'severity_level' => 3.0,
                'safety_risk_score' => 3.0,
                'assessment_source' => 'field_survey',
            ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['assessment_source']]);
    }
}
