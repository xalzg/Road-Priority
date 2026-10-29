<?php

namespace Tests\Feature;

use App\Models\AdministrativeArea;
use App\Models\AuditLog;
use App\Models\DamageAssessment;
use App\Models\DamageReport;
use App\Models\DecisionCriterion;
use App\Models\ReportPhoto;
use App\Models\RoadSegment;
use App\Models\User;
use Database\Seeders\CandidateCriteriaSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Prd051BugFixRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role, string $email, bool $isActive = true): User
    {
        $user = new User([
            'name' => ucfirst($role) . ' Regression Tester',
            'email' => $email,
            'password' => Hash::make('SecretPass123!'),
        ]);
        $user->role = $role;
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }

    public function test_bug_p1_01_database_seeder_is_idempotent_and_preserves_existing_admin_password(): void
    {
        // Jalankan DatabaseSeeder pertama kali
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, DecisionCriterion::count());
        $admin = User::query()->where('email', 'admin@roadpriority.local')->first();
        $this->assertNotNull($admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue((bool) $admin->is_active);
        $firstPasswordHash = $admin->password;

        // Jalankan DatabaseSeeder kedua kali: tidak boleh menggandakan baris atau merusak hash password admin yang sudah ada
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, DecisionCriterion::count());
        $this->assertSame(1, User::query()->where('email', 'admin@roadpriority.local')->count());
        $this->assertSame($firstPasswordHash, $admin->fresh()->password);

        // Pastikan audit log tidak pernah menyimpan password mentah maupun hash password
        $logs = AuditLog::query()->where('action', 'user.bootstrap_admin_seeded')->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());
        foreach ($logs as $log) {
            $summaryJson = json_encode($log->changes_summary);
            $this->assertStringNotContainsString($firstPasswordHash, (string) $summaryJson);
        }
    }

    public function test_bug_p1_02_demo_simulation_seeder_supports_simulation_topsis_and_stays_isolated_from_official(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSimulationSeeder::class);

        // 1. Verifikasi 4 segmen demo, enum damage_type resmi, dan assessment_source = demo_fixture
        $demoSegments = RoadSegment::query()->where('is_demo', true)->orderBy('road_reference_code')->get();
        $this->assertCount(4, $demoSegments);
        foreach ($demoSegments as $seg) {
            $this->assertSame('demo', $seg->data_origin);
            $this->assertTrue((bool) $seg->is_demo);
        }

        $demoReports = DamageReport::query()->where('is_demo', true)->get();
        $this->assertCount(4, $demoReports);
        foreach ($demoReports as $rep) {
            $this->assertContains($rep->damage_type, DamageReport::DAMAGE_TYPES);
            $this->assertSame('demo', $rep->data_origin);
        }

        $demoAssessments = DamageAssessment::query()->where('is_demo', true)->get();
        $this->assertCount(4, $demoAssessments);
        foreach ($demoAssessments as $ass) {
            $this->assertSame('demo_fixture', $ass->assessment_source);
            $this->assertGreaterThanOrEqual(1.0, (float) $ass->road_function_score);
            $this->assertLessThanOrEqual(4.0, (float) $ass->road_function_score);
        }

        // 2. Pastikan data demo tidak bocor ke endpoint publik
        $this->getJson('/api/reports')->assertStatus(200)->assertJsonCount(0, 'data.items');
        $this->getJson('/api/road-segments')->assertStatus(200)->assertJsonCount(0, 'data.items');

        // 3. Uji kesiapan data & eksekusi TOPSIS mode simulasi vs mode resmi
        $admin = User::query()->where('email', 'admin@roadpriority.local')->firstOrFail();
        Sanctum::actingAs($admin);

        // Mode resmi (include_demo=0) melihat 0 segmen, sedangkan mode simulasi (include_demo=1) melihat 4 segmen layak
        $this->getJson('/api/decision-data-readiness?include_demo=0')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.eligible_segments_count', 0)
            ->assertJsonPath('data.summary.ready_for_topsis_run', false);

        $this->getJson('/api/decision-data-readiness?include_demo=1')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.eligible_segments_count', 4)
            ->assertJsonPath('data.summary.incomplete_segments_count', 0)
            ->assertJsonPath('data.summary.ready_for_topsis_run', true);

        // Buat versi bobot 7 kriteria (semua perbandingan = 1 -> CR = 0,0) dan aktifkan
        $criteria = DecisionCriterion::query()->where('is_active', true)->orderBy('code')->get()->values();
        $comparisons = [];
        for ($i = 0; $i < $criteria->count(); $i++) {
            for ($j = $i + 1; $j < $criteria->count(); $j++) {
                $comparisons[] = [
                    'criterion_a_id' => $criteria[$i]->id,
                    'criterion_b_id' => $criteria[$j]->id,
                    'comparison_value' => 1.0,
                ];
            }
        }

        $versionId = (int) $this->postJson('/api/criterion-weight-versions', [
            'version_name' => 'Bobot Uji Simulasi 7 Kriteria',
        ])->assertStatus(201)->json('data.id');

        $this->postJson("/api/criterion-weight-versions/{$versionId}/calculate", [
            'comparisons' => $comparisons,
        ])->assertStatus(200)->assertJsonPath('data.is_consistent', true);

        $this->postJson("/api/criterion-weight-versions/{$versionId}/activate")->assertStatus(200);

        // Mode resmi (is_simulation = false) wajib menolak karena tidak ada segmen resmi non-demo
        $this->postJson('/api/decision-runs', [
            'name' => 'Run Resmi Tanpa Data Nyata',
            'criterion_weight_version_id' => $versionId,
            'is_simulation' => false,
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'insufficient_alternatives');

        // Mode simulasi (is_simulation = true) berhasil memeringkat ke-4 segmen demo tanpa error
        $simRun = $this->postJson('/api/decision-runs', [
            'name' => 'Run Simulasi 4 Segmen Demo',
            'criterion_weight_version_id' => $versionId,
            'is_simulation' => true,
            'parameters' => ['include_demo' => true],
        ]);
        $simRun->assertStatus(201)
            ->assertJsonPath('data.is_simulation', true)
            ->assertJsonPath('data.reproducibility_verified', true)
            ->assertJsonCount(4, 'data.ranked_items')
            ->assertJsonCount(0, 'data.excluded_items');
    }

    public function test_bug_p2_01_k3_road_function_score_validation_accepts_1_and_4_and_rejects_5(): void
    {
        $this->seed(CandidateCriteriaSeeder::class);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'officer-k3@roadpriority.test');

        $segment = RoadSegment::query()->create([
            'road_reference_code' => 'SEG-K3-001',
            'name' => 'Ruas Uji K3',
            'road_function' => 'kolektor',
            'nearest_facility_meters' => 120.0,
            'is_demo' => false,
            'is_active' => true,
        ]);

        Sanctum::actingAs($officer);

        // Nilai batas bawah K3 = 1 diterima (201)
        $this->postJson('/api/assessments', [
            'road_segment_id' => $segment->id,
            'severity_level' => 3,
            'safety_risk_score' => 3,
            'road_function_score' => 1,
            'impact_score' => 2,
            'status' => 'final',
        ])->assertStatus(201)
            ->assertJsonPath('data.road_function_score', 1);

        // Nilai batas atas K3 = 4 diterima (201)
        $this->postJson('/api/assessments', [
            'road_segment_id' => $segment->id,
            'severity_level' => 4,
            'safety_risk_score' => 4,
            'road_function_score' => 4,
            'impact_score' => 4,
            'status' => 'final',
        ])->assertStatus(201)
            ->assertJsonPath('data.road_function_score', 4);

        // Nilai di luar rentang K3 = 5 ditolak oleh server (422)
        $this->postJson('/api/assessments', [
            'road_segment_id' => $segment->id,
            'severity_level' => 4,
            'safety_risk_score' => 4,
            'road_function_score' => 5,
            'impact_score' => 4,
            'status' => 'final',
        ])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['road_function_score']]);
    }

    public function test_bug_p2_02_deactivated_user_with_token_cannot_access_demo_road_segments(): void
    {
        $this->seed(DemoSimulationSeeder::class);

        $area = AdministrativeArea::query()->first();
        RoadSegment::query()->create([
            'administrative_area_id' => $area?->id,
            'road_reference_code' => 'OFFICIAL-SEG-001',
            'name' => 'Ruas Jalan Resmi 001',
            'road_function' => 'arteri',
            'nearest_facility_meters' => 100.0,
            'data_origin' => 'field_survey',
            'is_demo' => false,
            'is_active' => true,
        ]);

        // 1. Pengunjung publik hanya melihat 1 segmen resmi meskipun meminta include_demo=1
        $this->getJson('/api/road-segments?include_demo=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.road_reference_code', 'OFFICIAL-SEG-001');
        $this->getJson('/api/road-segments/DEMO-SEG-001')->assertStatus(404);

        // 2. Pelapor aktif (tanpa peran petugas/pakar/admin) tetap hanya melihat 1 segmen resmi
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER, 'rep-seg@roadpriority.test');
        $reporterToken = $reporter->createToken('test-rep')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$reporterToken}")
            ->getJson('/api/road-segments?include_demo=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
        $this->withHeader('Authorization', "Bearer {$reporterToken}")
            ->getJson('/api/road-segments/DEMO-SEG-001')
            ->assertStatus(404);

        // 3. Petugas aktif berwenang dapat melihat 5 segmen (1 resmi + 4 demo) ketika include_demo=1
        $this->app['auth']->forgetGuards();
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'off-seg@roadpriority.test');
        $officerToken = $officer->createToken('test-off')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$officerToken}")
            ->getJson('/api/road-segments?include_demo=1')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data.items');
        $this->withHeader('Authorization', "Bearer {$officerToken}")
            ->getJson('/api/road-segments/DEMO-SEG-001')
            ->assertStatus(200)
            ->assertJsonPath('data.road_reference_code', 'DEMO-SEG-001');

        // 4. Ketika akun petugas dinonaktifkan, token lama wajib ditolak (403) dan dicabut
        $officer->is_active = false;
        $officer->save();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$officerToken}")
            ->getJson('/api/road-segments?include_demo=1')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Account is inactive.');

        $this->assertSame(0, $officer->tokens()->count());
    }

    public function test_bug_p2_03_damage_assessment_and_decision_criterion_policies_enforce_role_matrix(): void
    {
        $this->seed(CandidateCriteriaSeeder::class);
        $criterion = DecisionCriterion::query()->firstOrFail();

        $reporter = $this->createUserWithRole(User::ROLE_REPORTER, 'rep-pol@roadpriority.test');
        $expert = $this->createUserWithRole(User::ROLE_EXPERT, 'exp-pol@roadpriority.test');
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'off-pol@roadpriority.test');
        $admin = $this->createUserWithRole(User::ROLE_ADMIN, 'adm-pol@roadpriority.test');

        // Matriks Policy langsung (Gate::forUser)
        $this->assertFalse(Gate::forUser($reporter)->allows('viewAny', DecisionCriterion::class));
        $this->assertFalse(Gate::forUser($reporter)->allows('view', $criterion));
        $this->assertFalse(Gate::forUser($reporter)->allows('viewAny', DamageAssessment::class));
        $this->assertFalse(Gate::forUser($reporter)->allows('create', DamageAssessment::class));

        $this->assertTrue(Gate::forUser($expert)->allows('viewAny', DecisionCriterion::class));
        $this->assertFalse(Gate::forUser($expert)->allows('create', DecisionCriterion::class));
        $this->assertTrue(Gate::forUser($expert)->allows('viewAny', DamageAssessment::class));
        $this->assertFalse(Gate::forUser($expert)->allows('create', DamageAssessment::class));

        $this->assertTrue(Gate::forUser($officer)->allows('viewAny', DecisionCriterion::class));
        $this->assertFalse(Gate::forUser($officer)->allows('create', DecisionCriterion::class));
        $this->assertTrue(Gate::forUser($officer)->allows('viewAny', DamageAssessment::class));
        $this->assertTrue(Gate::forUser($officer)->allows('create', DamageAssessment::class));

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', DecisionCriterion::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', DecisionCriterion::class));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', DamageAssessment::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', DamageAssessment::class));

        // Verifikasi juga via HTTP endpoint untuk setiap peran
        Sanctum::actingAs($reporter);
        $this->getJson('/api/assessments')->assertStatus(403);
        $this->postJson('/api/assessments', [])->assertStatus(403);

        Sanctum::actingAs($expert);
        $this->getJson('/api/assessments')->assertStatus(200);
        $this->postJson('/api/assessments', [])->assertStatus(403);
    }

    public function test_bug_p2_06_deactivated_user_token_revoked_on_show_photo(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER, 'rep-photo@roadpriority.test');
        $report = DamageReport::query()->create([
            'report_code' => 'RPT-PHOTO-001',
            'reporter_user_id' => $reporter->id,
            'title' => 'Laporan Uji Foto Nonaktif',
            'description' => 'Deskripsi kerusakan jalan minimal sepuluh karakter untuk pengujian.',
            'damage_type' => 'jalan_berlubang',
            'self_reported_severity' => 3,
            'latitude' => -6.3015000,
            'longitude' => 106.6520000,
            'status' => DamageReport::STATUS_VERIFIED,
            'is_public' => true,
            'submitted_at' => now(),
        ]);

        $photo = ReportPhoto::query()->create([
            'damage_report_id' => $report->id,
            'file_path' => 'report-photos/test-photo.png',
            'original_filename' => 'test-photo.png',
            'mime_type' => 'image/png',
            'file_size_bytes' => 128,
            'storage_disk' => 'local',
            'is_public' => true,
        ]);

        $token = $reporter->createToken('photo-token')->plainTextToken;
        $reporter->is_active = false;
        $reporter->save();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/reports/{$report->id}/photos/{$photo->id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Account is inactive.');

        $this->assertSame(0, $reporter->tokens()->count());
    }
}
