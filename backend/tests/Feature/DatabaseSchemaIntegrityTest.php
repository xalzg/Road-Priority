<?php

namespace Tests\Feature;

use App\Models\AdministrativeArea;
use App\Models\AhpPairwiseComparison;
use App\Models\AuditLog;
use App\Models\CriterionWeightItem;
use App\Models\CriterionWeightVersion;
use App\Models\DamageAssessment;
use App\Models\DamageReport;
use App\Models\DecisionCriterion;
use App\Models\DecisionRun;
use App\Models\DecisionRunItem;
use App\Models\ReportPhoto;
use App\Models\ReportStatusHistory;
use App\Models\RoadSegment;
use App\Models\User;
use Database\Seeders\CandidateCriteriaSeeder;
use Database\Seeders\DemoSimulationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DatabaseSchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_suite_uses_dedicated_test_database_and_migrates_all_tables(): void
    {
        $this->assertSame('roadpriority_test', DB::connection()->getDatabaseName());

        $expectedTables = [
            'users',
            'administrative_areas',
            'road_segments',
            'damage_reports',
            'report_photos',
            'report_status_histories',
            'damage_assessments',
            'decision_criteria',
            'criterion_weight_versions',
            'criterion_weight_items',
            'ahp_pairwise_comparisons',
            'decision_runs',
            'decision_run_items',
            'audit_logs',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} harus tersedia.");
        }
    }

    public function test_user_mass_assignment_protects_role_and_is_active(): void
    {
        $user = User::create([
            'name' => 'Pelapor Uji',
            'email' => 'pelapor@example.test',
            'password' => Hash::make('Secret123!'),
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ]);

        $user->refresh();
        $this->assertSame(User::ROLE_REPORTER, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_nullable_fields_and_core_eloquent_relationships_work(): void
    {
        $parentArea = AdministrativeArea::create([
            'name' => 'Kota Tangerang Selatan',
            'administrative_level' => 'kota',
            'external_code' => '36.74',
            'is_study_area' => false,
        ]);

        $childArea = AdministrativeArea::create([
            'name' => 'Kecamatan Serpong',
            'administrative_level' => 'kecamatan',
            'parent_id' => $parentArea->id,
            'external_code' => '36.74.01',
            'is_study_area' => true,
            'boundary_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[[106.62, -6.34], [106.72, -6.34], [106.72, -6.25], [106.62, -6.25], [106.62, -6.34]]],
            ],
        ]);

        $segment = RoadSegment::create([
            'administrative_area_id' => $childArea->id,
            'name' => 'Jl. Pahlawan Seribu Segmen 01',
            'road_reference_code' => 'SEG-BSD-001',
            'geometry_geojson' => [
                'type' => 'LineString',
                'coordinates' => [[106.6520, -6.3010], [106.6540, -6.3010]],
            ],
            'centroid_latitude' => -6.3010000,
            'centroid_longitude' => 106.6530000,
            'length_meters' => 221.04,
            'road_function' => 'arteri',
            'road_authority' => 'kota',
            'nearest_facility_meters' => 120.50,
            'source_name' => 'OpenStreetMap',
            'source_reference' => 'way/10001',
        ]);

        // Pelaporan dengan reporter_id nullable & verified_by nullable
        $report = DamageReport::create([
            'report_code' => 'RPT-2026-0001',
            'reporter_id' => null,
            'road_segment_id' => $segment->id,
            'segment_match_distance_m' => 6.40,
            'damage_type' => 'pothole',
            'description' => 'Lubang di lajur kiri dekat simpang.',
            'latitude' => -6.3010500,
            'longitude' => 106.6531000,
            'gps_accuracy_m' => 12.00,
            'status' => DamageReport::STATUS_SUBMITTED,
        ]);

        $photo = ReportPhoto::create([
            'damage_report_id' => $report->id,
            'storage_disk' => 'local',
            'file_path' => 'reports/2026/10/random-uuid.jpg',
            'original_filename' => 'foto_jalan.jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 245120,
            'width' => 1280,
            'height' => 720,
            'is_public' => false,
        ]);

        $history = ReportStatusHistory::create([
            'damage_report_id' => $report->id,
            'previous_status' => null,
            'new_status' => DamageReport::STATUS_SUBMITTED,
            'changed_by' => null,
            'notes' => 'Laporan awal diterima.',
        ]);

        $assessment = DamageAssessment::create([
            'road_segment_id' => $segment->id,
            'damage_report_id' => $report->id,
            'assessed_by' => null,
            'severity_level' => 4,
            'safety_risk_score' => 4,
            'road_function_score' => 4,
            'impact_score' => 3,
            'measurements' => ['pothole_count_per_100m' => 7],
            'status' => DamageAssessment::STATUS_FINAL,
            'assessment_source' => 'field_survey',
        ]);

        $this->assertTrue($childArea->parent->is($parentArea));
        $this->assertCount(1, $parentArea->children);
        $this->assertTrue($segment->administrativeArea->is($childArea));
        $this->assertCount(1, $segment->damageReports);
        $this->assertCount(1, $report->photos);
        $this->assertCount(1, $report->statusHistories);
        $this->assertTrue($photo->damageReport->is($report));
        $this->assertTrue($history->damageReport->is($report));
        $this->assertTrue($assessment->roadSegment->is($segment));
        $this->assertTrue($assessment->damageReport->is($report));
    }

    public function test_duplicate_unique_codes_are_rejected_by_constraints(): void
    {
        DamageReport::create([
            'report_code' => 'RPT-DUP-001',
            'damage_type' => 'pothole',
            'description' => 'Laporan pertama',
            'latitude' => -6.3010000,
            'longitude' => 106.6530000,
        ]);

        $this->expectException(QueryException::class);

        DamageReport::create([
            'report_code' => 'RPT-DUP-001',
            'damage_type' => 'rutting',
            'description' => 'Laporan kedua dengan kode sama',
            'latitude' => -6.3020000,
            'longitude' => 106.6540000,
        ]);
    }

    public function test_damage_assessment_requires_segment_or_report(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DamageAssessment::create([
            'road_segment_id' => null,
            'damage_report_id' => null,
            'severity_level' => 3,
            'assessment_source' => 'field_survey',
        ]);
    }

    public function test_decision_run_links_to_weight_version_and_enforces_unique_segment_and_rank(): void
    {
        $k1 = DecisionCriterion::create([
            'code' => 'K1',
            'name' => 'Tingkat kerusakan',
            'description' => 'Skor kondisi fisik perkerasan jalan',
            'data_type' => 'ordinal',
            'unit' => 'Skala 1-5',
            'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
        ]);
        $k2 = DecisionCriterion::create([
            'code' => 'K2',
            'name' => 'Kedekatan fasilitas publik',
            'description' => 'Jarak minimum ke fasilitas publik',
            'data_type' => 'numeric',
            'unit' => 'Meter',
            'criterion_type' => DecisionCriterion::TYPE_COST,
        ]);

        $version = CriterionWeightVersion::create([
            'version_name' => 'Bobot Uji v1',
            'status' => CriterionWeightVersion::STATUS_APPROVED,
            'criteria_count' => 2,
            'lambda_max' => 2.0,
            'consistency_index' => 0.0,
            'consistency_ratio' => 0.0,
            'is_consistent' => true,
        ]);

        CriterionWeightItem::create([
            'criterion_weight_version_id' => $version->id,
            'decision_criterion_id' => $k1->id,
            'weight_value' => 0.75,
        ]);
        CriterionWeightItem::create([
            'criterion_weight_version_id' => $version->id,
            'decision_criterion_id' => $k2->id,
            'weight_value' => 0.25,
        ]);

        AhpPairwiseComparison::create([
            'criterion_weight_version_id' => $version->id,
            'expert_id' => null,
            'criterion_a_id' => $k1->id,
            'criterion_b_id' => $k2->id,
            'comparison_value' => 3.0,
        ]);

        $seg1 = RoadSegment::create(['road_reference_code' => 'SEG-01', 'name' => 'Ruas 1']);
        $seg2 = RoadSegment::create(['road_reference_code' => 'SEG-02', 'name' => 'Ruas 2']);

        $run = DecisionRun::create([
            'run_code' => 'RUN-2026-0001',
            'name' => 'Eksekusi Uji 1',
            'criterion_weight_version_id' => $version->id,
            'algorithm_name' => 'AHP-TOPSIS',
            'algorithm_version' => 'ahp-topsis-1.0.0',
            'status' => DecisionRun::STATUS_COMPLETED,
            'is_simulation' => false,
            'input_hash' => str_repeat('a', 64),
            'input_snapshot_reference' => [
                'weights' => ['K1' => 0.75, 'K2' => 0.25],
                'ideal_solutions' => ['positive' => ['K1' => 0.6, 'K2' => 0.1], 'negative' => ['K1' => 0.3, 'K2' => 0.2]],
            ],
            'executed_at' => now(),
        ]);

        DecisionRunItem::create([
            'decision_run_id' => $run->id,
            'road_segment_id' => $seg1->id,
            'is_included' => true,
            'rank_position' => 1,
            'preference_score' => 0.712345678901,
            'd_plus' => 0.08,
            'd_minus' => 0.20,
            'largest_driving_criterion' => 'K1',
            'input_values_snapshot' => ['K1' => 5, 'K2' => 120],
            'intermediate_values_snapshot' => ['normalized' => ['K1' => 0.8, 'K2' => 0.4]],
        ]);

        $this->assertTrue($run->weightVersion->is($version));
        $this->assertCount(2, $version->weightItems);
        $this->assertCount(1, $version->ahpComparisons);
        $this->assertCount(1, $run->items);

        // Peringkat ganda pada decision_run yang sama wajib ditolak oleh unique constraint
        $this->expectException(QueryException::class);
        DecisionRunItem::create([
            'decision_run_id' => $run->id,
            'road_segment_id' => $seg2->id,
            'is_included' => true,
            'rank_position' => 1,
            'preference_score' => 0.50,
            'input_values_snapshot' => ['K1' => 3, 'K2' => 250],
        ]);
    }

    public function test_deletion_rules_protect_historical_records_and_preserve_audit_trail(): void
    {
        $officer = User::forceCreate([
            'name' => 'Petugas Verifikator',
            'email' => 'petugas@example.test',
            'password' => Hash::make('Secret123!'),
            'role' => User::ROLE_OFFICER,
            'is_active' => true,
        ]);

        $report = DamageReport::create([
            'report_code' => 'RPT-HIST-001',
            'damage_type' => 'pothole',
            'description' => 'Laporan dengan histori status',
            'latitude' => -6.3010000,
            'longitude' => 106.6530000,
            'verified_by' => $officer->id,
        ]);

        $history = ReportStatusHistory::create([
            'damage_report_id' => $report->id,
            'previous_status' => DamageReport::STATUS_SUBMITTED,
            'new_status' => DamageReport::STATUS_VERIFIED,
            'changed_by' => $officer->id,
            'notes' => 'Diverifikasi petugas.',
        ]);

        $log = AuditLog::create([
            'actor_id' => $officer->id,
            'action' => 'report.verified',
            'entity_type' => 'damage_report',
            'entity_id' => $report->id,
            'changes_summary' => ['from' => 'submitted', 'to' => 'verified'],
        ]);

        // Menghapus akun petugas tidak boleh menghapus histori status atau audit log (nullOnDelete)
        $officer->delete();
        $this->assertNull($history->fresh()->changed_by);
        $this->assertNull($log->fresh()->actor_id);
        $this->assertNull($report->fresh()->verified_by);

        // Menghapus laporan yang sudah memiliki histori status wajib ditolak oleh restrictOnDelete
        $this->expectException(QueryException::class);
        $report->delete();
    }

    public function test_seeders_populate_candidate_criteria_and_labeled_demo_data(): void
    {
        $this->seed(CandidateCriteriaSeeder::class);
        $this->seed(DemoSimulationSeeder::class);

        $this->assertSame(7, DecisionCriterion::count());
        $this->assertTrue(RoadSegment::where('road_reference_code', 'DEMO-SEG-001')->firstOrFail()->is_demo);
        $this->assertSame('demo', DamageReport::where('report_code', 'DEMO-RPT-0001')->firstOrFail()->data_origin);
        $this->assertTrue(DamageAssessment::firstOrFail()->is_demo);
    }
}

