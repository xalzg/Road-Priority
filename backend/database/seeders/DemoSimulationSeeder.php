<?php

namespace Database\Seeders;

use App\Models\AdministrativeArea;
use App\Models\DamageAssessment;
use App\Models\DamageReport;
use App\Models\RoadSegment;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSimulationSeeder extends Seeder
{
    /**
     * Seeder data sintetis khusus untuk pengujian/simulasi lokal (PRD 05.1 BUG-P1-02).
     * DILARANG dijalankan pada lingkungan production (Master PRD §0.1 & §13).
     * Menyediakan 4 segmen jalan demo lengkap (K1–K7 valid) dengan penanda `is_demo = true`
     * dan `data_origin = 'demo'` agar mode simulasi TOPSIS dapat diuji tanpa mencemari data resmi.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSimulationSeeder dilarang dijalankan pada environment production.');
        }

        $area = AdministrativeArea::updateOrCreate(
            ['external_code' => 'DEMO-AREA-BSD'],
            [
                'name' => '[DATA DEMO] Kawasan Simulasi Serpong',
                'administrative_level' => 'kecamatan',
                'is_study_area' => false,
                'boundary_geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[106.62, -6.34], [106.72, -6.34], [106.72, -6.25], [106.62, -6.25], [106.62, -6.34]]],
                ],
            ]
        );

        $demoSpecs = [
            [
                'segment_code' => 'DEMO-SEG-001',
                'segment_name' => '[DATA DEMO] Ruas Simulasi 001 (Jl. Raya Serpong Segmen A)',
                'coordinates' => [[106.6520, -6.3016], [106.6540, -6.3016]],
                'centroid_lat' => -6.3016000,
                'centroid_lng' => 106.6530000,
                'length_meters' => 221.04,
                'road_function' => 'kolektor',
                'nearest_facility_meters' => 150.00,
                'report_code' => 'DEMO-RPT-0001',
                'damage_type' => 'lubang',
                'reported_days_ago' => 10,
                'verified_days_ago' => 9,
                'assessed_days_ago' => 8,
                'severity_level' => 4,
                'safety_risk_score' => 3,
                'road_function_score' => 3,
                'impact_score' => 4,
                'measurements' => [
                    'crack_area_pct' => 15,
                    'crack_width_mm' => 2,
                    'pothole_count_per_100m' => 8,
                    'rutting_depth_cm' => 1.5,
                ],
            ],
            [
                'segment_code' => 'DEMO-SEG-002',
                'segment_name' => '[DATA DEMO] Ruas Simulasi 002 (Jl. Pahlawan Seribu Segmen B)',
                'coordinates' => [[106.6600, -6.2950], [106.6635, -6.2950]],
                'centroid_lat' => -6.2950000,
                'centroid_lng' => 106.6617500,
                'length_meters' => 385.50,
                'road_function' => 'arteri',
                'nearest_facility_meters' => 95.00,
                'report_code' => 'DEMO-RPT-0002',
                'damage_type' => 'aspal_mengelupas',
                'reported_days_ago' => 18,
                'verified_days_ago' => 16,
                'assessed_days_ago' => 7,
                'severity_level' => 5,
                'safety_risk_score' => 5,
                'road_function_score' => 4,
                'impact_score' => 5,
                'measurements' => [
                    'crack_area_pct' => 32,
                    'crack_width_mm' => 5,
                    'pothole_count_per_100m' => 14,
                    'rutting_depth_cm' => 3.2,
                ],
            ],
            [
                'segment_code' => 'DEMO-SEG-003',
                'segment_name' => '[DATA DEMO] Ruas Simulasi 003 (Jl. Letnan Sutopo Segmen C)',
                'coordinates' => [[106.6710, -6.3080], [106.6740, -6.3080]],
                'centroid_lat' => -6.3080000,
                'centroid_lng' => 106.6725000,
                'length_meters' => 330.00,
                'road_function' => 'lokal',
                'nearest_facility_meters' => 310.00,
                'report_code' => 'DEMO-RPT-0003',
                'damage_type' => 'retak',
                'reported_days_ago' => 6,
                'verified_days_ago' => 5,
                'assessed_days_ago' => 4,
                'severity_level' => 3,
                'safety_risk_score' => 4,
                'road_function_score' => 2,
                'impact_score' => 3,
                'measurements' => [
                    'crack_area_pct' => 12,
                    'crack_width_mm' => 3,
                    'pothole_count_per_100m' => 4,
                    'rutting_depth_cm' => 1.1,
                ],
            ],
            [
                'segment_code' => 'DEMO-SEG-004',
                'segment_name' => '[DATA DEMO] Ruas Simulasi 004 (Jl. Ciater Raya Segmen D)',
                'coordinates' => [[106.6820, -6.3140], [106.6845, -6.3140]],
                'centroid_lat' => -6.3140000,
                'centroid_lng' => 106.6832500,
                'length_meters' => 275.00,
                'road_function' => 'lingkungan',
                'nearest_facility_meters' => 480.00,
                'report_code' => 'DEMO-RPT-0004',
                'damage_type' => 'permukaan_bergelombang',
                'reported_days_ago' => 4,
                'verified_days_ago' => 3,
                'assessed_days_ago' => 2,
                'severity_level' => 2,
                'safety_risk_score' => 2,
                'road_function_score' => 1,
                'impact_score' => 2,
                'measurements' => [
                    'crack_area_pct' => 5,
                    'crack_width_mm' => 1,
                    'pothole_count_per_100m' => 1,
                    'rutting_depth_cm' => 0.8,
                ],
            ],
        ];

        foreach ($demoSpecs as $spec) {
            $segment = RoadSegment::updateOrCreate(
                ['road_reference_code' => $spec['segment_code']],
                [
                    'administrative_area_id' => $area->id,
                    'name' => $spec['segment_name'],
                    'geometry_geojson' => [
                        'type' => 'LineString',
                        'coordinates' => $spec['coordinates'],
                    ],
                    'centroid_latitude' => $spec['centroid_lat'],
                    'centroid_longitude' => $spec['centroid_lng'],
                    'length_meters' => $spec['length_meters'],
                    'road_function' => $spec['road_function'],
                    'road_authority' => 'belum_terverifikasi',
                    'nearest_facility_meters' => $spec['nearest_facility_meters'],
                    'source_name' => 'Synthetic Demo Seeder',
                    'source_reference' => 'DEMO-ONLY',
                    'data_origin' => 'demo',
                    'is_demo' => true,
                    'is_active' => true,
                ]
            );

            $report = DamageReport::updateOrCreate(
                ['report_code' => $spec['report_code']],
                [
                    'road_segment_id' => $segment->id,
                    'segment_match_distance_m' => 4.20,
                    'damage_type' => $spec['damage_type'],
                    'description' => '[DATA DEMO] Contoh laporan sintetis untuk pengujian antarmuka (' . $spec['segment_code'] . ').',
                    'latitude' => $spec['centroid_lat'],
                    'longitude' => $spec['centroid_lng'],
                    'gps_accuracy_m' => 8.50,
                    'reported_at' => now()->subDays($spec['reported_days_ago']),
                    'status' => DamageReport::STATUS_VERIFIED,
                    'verification_notes' => '[DATA DEMO] Terverifikasi untuk simulasi.',
                    'verified_at' => now()->subDays($spec['verified_days_ago']),
                    'data_origin' => 'demo',
                    'is_demo' => true,
                ]
            );

            DamageAssessment::updateOrCreate(
                [
                    'road_segment_id' => $segment->id,
                    'damage_report_id' => $report->id,
                    'protocol_version' => 'SDI-ADAPTED-1.0',
                ],
                [
                    'assessment_date' => now()->subDays($spec['assessed_days_ago']),
                    'severity_level' => $spec['severity_level'],
                    'safety_risk_score' => $spec['safety_risk_score'],
                    'road_function_score' => $spec['road_function_score'],
                    'impact_score' => $spec['impact_score'],
                    'measurements' => $spec['measurements'],
                    'status' => DamageAssessment::STATUS_FINAL,
                    'notes' => '[DATA DEMO] Penilaian sintetis untuk pengujian (' . $spec['segment_code'] . ').',
                    'assessment_source' => 'demo_fixture',
                    'is_demo' => true,
                ]
            );
        }
    }
}
