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
     * Seeder data sintetis khusus untuk pengujian/simulasi lokal.
     * DILARANG dijalankan pada lingkungan production (Master PRD §0.1 & §13).
     * Seluruh baris ditandai `is_demo = true` dan `data_origin = 'demo'`.
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

        $segment = RoadSegment::updateOrCreate(
            ['road_reference_code' => 'DEMO-SEG-001'],
            [
                'administrative_area_id' => $area->id,
                'name' => '[DATA DEMO] Ruas Simulasi 001',
                'geometry_geojson' => [
                    'type' => 'LineString',
                    'coordinates' => [[106.6520, -6.3016], [106.6540, -6.3016]],
                ],
                'centroid_latitude' => -6.3016000,
                'centroid_longitude' => 106.6530000,
                'length_meters' => 221.04,
                'road_function' => 'kolektor',
                'road_authority' => 'belum_terverifikasi',
                'nearest_facility_meters' => 150.00,
                'source_name' => 'Synthetic Demo Seeder',
                'source_reference' => 'DEMO-ONLY',
                'data_origin' => 'demo',
                'is_demo' => true,
                'is_active' => true,
            ]
        );

        $report = DamageReport::updateOrCreate(
            ['report_code' => 'DEMO-RPT-0001'],
            [
                'road_segment_id' => $segment->id,
                'segment_match_distance_m' => 4.20,
                'damage_type' => 'pothole',
                'description' => '[DATA DEMO] Contoh laporan sintetis untuk pengujian antarmuka.',
                'latitude' => -6.3016000,
                'longitude' => 106.6530000,
                'gps_accuracy_m' => 8.50,
                'reported_at' => now()->subDays(10),
                'status' => DamageReport::STATUS_VERIFIED,
                'verification_notes' => '[DATA DEMO] Terverifikasi untuk simulasi.',
                'verified_at' => now()->subDays(9),
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
                'assessment_date' => now()->subDays(8),
                'severity_level' => 4,
                'safety_risk_score' => 3,
                'road_function_score' => 3,
                'impact_score' => 3,
                'measurements' => [
                    'crack_area_pct' => 15,
                    'crack_width_mm' => 2,
                    'pothole_count_per_100m' => 8,
                    'rutting_depth_cm' => 1.5,
                ],
                'status' => DamageAssessment::STATUS_FINAL,
                'notes' => '[DATA DEMO] Penilaian sintetis untuk pengujian.',
                'assessment_source' => 'demo',
                'is_demo' => true,
            ]
        );
    }
}

