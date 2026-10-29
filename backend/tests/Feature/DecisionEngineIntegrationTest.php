<?php

namespace Tests\Feature;

use App\Models\AdministrativeArea;
use App\Models\CriterionWeightVersion;
use App\Models\DamageAssessment;
use App\Models\DecisionCriterion;
use App\Models\RoadSegment;
use App\Models\User;
use Database\Seeders\CandidateCriteriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DecisionEngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role, string $email): User
    {
        $user = new User([
            'name' => ucfirst($role) . ' Tester',
            'email' => $email,
            'password' => bcrypt('SecretPass123!'),
        ]);
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    /**
     * Mengaktifkan subset kriteria Lampiran C (K1: Tingkat kerusakan [benefit],
     * K2: Risiko keselamatan [benefit], K5: Kedekatan fasilitas publik [cost])
     * dan menonaktifkan K3, K4, K6, K7 agar matriks 3x3 Lampiran C diuji secara presisi.
     *
     * @return array<string, DecisionCriterion>
     */
    private function configureLampiranCCriteria(): array
    {
        $this->seed(CandidateCriteriaSeeder::class);

        DecisionCriterion::query()
            ->whereNotIn('code', ['K1', 'K2', 'K5'])
            ->update(['is_active' => false]);

        return DecisionCriterion::query()
            ->whereIn('code', ['K1', 'K2', 'K5'])
            ->orderBy('code')
            ->get()
            ->keyBy('code')
            ->all();
    }

    /**
     * Membuat 4 segmen jalan resmi sesuai Lampiran C (A1..A4) ditambah 1 segmen (A5)
     * tanpa asesmen lapangan untuk menguji aturan eksklusi D-09.
     *
     * @return array<string, RoadSegment>
     */
    private function seedLampiranCSegmentsAndAssessments(User $officer): array
    {
        $area = AdministrativeArea::query()->create([
            'external_code' => '33.01.01',
            'name' => 'Kecamatan Uji Prioritas',
            'administrative_level' => 'kecamatan',
            'is_study_area' => true,
        ]);

        // Matriks Keputusan Lampiran C (Master PRD v2.0):
        // A1: K1=4, K2=3, K5=150 m
        // A2: K1=5, K2=2, K5=600 m
        // A3: K1=3, K2=5, K5=300 m
        // A4: K1=2, K2=4, K5=100 m
        $specs = [
            'A1' => ['k1' => 4, 'k2' => 3, 'k5' => 150.0],
            'A2' => ['k1' => 5, 'k2' => 2, 'k5' => 600.0],
            'A3' => ['k1' => 3, 'k2' => 5, 'k5' => 300.0],
            'A4' => ['k1' => 2, 'k2' => 4, 'k5' => 100.0],
        ];

        $segments = [];
        foreach ($specs as $code => $row) {
            $segment = RoadSegment::query()->create([
                'road_reference_code' => $code,
                'name' => 'Ruas Jalan ' . $code,
                'administrative_area_id' => $area->id,
                'road_function' => 'kolektor',
                'road_authority' => 'kabupaten',
                'nearest_facility_meters' => $row['k5'],
                'length_meters' => 1000.0,
                'geometry_geojson' => [
                    'type' => 'LineString',
                    'coordinates' => [[110.400, -7.000], [110.405, -7.005]],
                ],
                'centroid_latitude' => -7.0025,
                'centroid_longitude' => 110.4025,
                'data_origin' => 'field_survey',
                'is_demo' => false,
                'is_active' => true,
            ]);

            DamageAssessment::query()->create([
                'road_segment_id' => $segment->id,
                'assessed_by' => $officer->id,
                'assessment_date' => now()->subDays(2),
                'protocol_version' => 'SDI-ADAPTED-1.0',
                'severity_level' => $row['k1'],
                'safety_risk_score' => $row['k2'],
                'road_function_score' => 3,
                'impact_score' => 3,
                'status' => DamageAssessment::STATUS_FINAL,
                'assessment_source' => 'field_inspection',
                'is_demo' => false,
            ]);

            $segments[$code] = $segment;
        }

        // Segmen ke-5 (A5) sengaja tidak memiliki asesmen lapangan untuk menguji eksklusi D-09
        $segments['A5'] = RoadSegment::query()->create([
            'road_reference_code' => 'A5',
            'name' => 'Ruas Jalan Tanpa Asesmen A5',
            'administrative_area_id' => $area->id,
            'road_function' => 'lokal',
            'road_authority' => 'kabupaten',
            'nearest_facility_meters' => 200.0,
            'length_meters' => 500.0,
            'geometry_geojson' => [
                'type' => 'LineString',
                'coordinates' => [[110.410, -7.010], [110.415, -7.015]],
            ],
            'centroid_latitude' => -7.0125,
            'centroid_longitude' => 110.4125,
            'data_origin' => 'field_survey',
            'is_demo' => false,
            'is_active' => true,
        ]);

        return $segments;
    }

    /**
     * @param  array<string, DecisionCriterion>  $criteriaByCode
     * @return array<int, array<string, mixed>>
     */
    private function lampiranCPairwiseComparisons(array $criteriaByCode): array
    {
        // Matriks perbandingan berpasangan 3x3 Lampiran C (Master PRD v2.0):
        // (K1, K2) = 3, (K1, K5) = 5, (K2, K5) = 2
        return [
            [
                'criterion_a_id' => $criteriaByCode['K1']->id,
                'criterion_b_id' => $criteriaByCode['K2']->id,
                'comparison_value' => 3.0,
            ],
            [
                'criterion_a_id' => $criteriaByCode['K1']->id,
                'criterion_b_id' => $criteriaByCode['K5']->id,
                'comparison_value' => 5.0,
            ],
            [
                'criterion_a_id' => $criteriaByCode['K2']->id,
                'criterion_b_id' => $criteriaByCode['K5']->id,
                'comparison_value' => 2.0,
            ],
        ];
    }

    public function test_full_ahp_topsis_workflow_and_lampiran_c_verification(): void
    {
        $criteriaByCode = $this->configureLampiranCCriteria();

        $admin = $this->createUserWithRole(User::ROLE_ADMIN, 'admin@roadpriority.test');
        $expert = $this->createUserWithRole(User::ROLE_EXPERT, 'expert@roadpriority.test');
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'officer@roadpriority.test');

        $this->seedLampiranCSegmentsAndAssessments($officer);

        // 1. Pakar membuat draft versi bobot
        Sanctum::actingAs($expert);
        $draftRes = $this->postJson('/api/criterion-weight-versions', [
            'version_name' => 'Bobot AHP Lampiran C v1',
            'aggregation_method' => 'geometric_mean_aij',
        ]);
        $draftRes->assertStatus(201)
            ->assertJsonPath('data.status', CriterionWeightVersion::STATUS_DRAFT);

        $versionId = (int) $draftRes->json('data.id');

        // 2. Pakar menghitung bobot AHP dari matriks perbandingan berpasangan Lampiran C
        $calcRes = $this->postJson("/api/criterion-weight-versions/{$versionId}/calculate", [
            'comparisons' => $this->lampiranCPairwiseComparisons($criteriaByCode),
        ]);
        $calcRes->assertStatus(200)
            ->assertJsonPath('data.status', CriterionWeightVersion::STATUS_COMPUTED)
            ->assertJsonPath('data.is_consistent', true);

        $this->assertEqualsWithDelta(3.0037, (float) $calcRes->json('data.lambda_max'), 0.0005);
        $this->assertEqualsWithDelta(0.0018, (float) $calcRes->json('data.consistency_index'), 0.0005);
        $this->assertEqualsWithDelta(0.0032, (float) $calcRes->json('data.consistency_ratio'), 0.0005);

        // 3. Pakar tidak boleh mengaktifkan versi bobot (wajib Admin)
        $this->postJson("/api/criterion-weight-versions/{$versionId}/activate")
            ->assertStatus(403);

        // 4. Admin menyetujui/mengaktifkan versi bobot
        Sanctum::actingAs($admin);
        $actRes = $this->postJson("/api/criterion-weight-versions/{$versionId}/activate");
        $actRes->assertStatus(200)
            ->assertJsonPath('data.status', CriterionWeightVersion::STATUS_APPROVED);

        // 5. Versi bobot yang sudah approved bersifat immutable (409 Conflict jika dihitung ulang)
        $this->postJson("/api/criterion-weight-versions/{$versionId}/calculate", [
            'comparisons' => $this->lampiranCPairwiseComparisons($criteriaByCode),
        ])->assertStatus(409);

        // 6. Petugas memeriksa laporan kesiapan data sebelum eksekusi TOPSIS
        Sanctum::actingAs($officer);
        $readinessRes = $this->getJson('/api/decision-data-readiness');
        $readinessRes->assertStatus(200)
            ->assertJsonPath('data.summary.total_active_segments', 5)
            ->assertJsonPath('data.summary.eligible_segments_count', 4)
            ->assertJsonPath('data.summary.incomplete_segments_count', 1)
            ->assertJsonPath('data.summary.ready_for_topsis_run', true);

        // 7. Petugas mengeksekusi perhitungan prioritas TOPSIS resmi
        $runRes = $this->postJson('/api/decision-runs', [
            'name' => 'Prioritas Penanganan Jalan Resmi Tahap I',
            'criterion_weight_version_id' => $versionId,
            'is_simulation' => false,
        ]);
        $runRes->assertStatus(201)
            ->assertJsonPath('data.reproducibility_verified', true)
            ->assertJsonCount(4, 'data.ranked_items')
            ->assertJsonCount(1, 'data.excluded_items');

        // Verifikasi kecocokan presisi dengan Tabel Lampiran C.4 (A2 > A1 > A3 > A4):
        $ranked = $runRes->json('data.ranked_items');
        $this->assertSame('A2', $ranked[0]['road_reference_code']);
        $this->assertSame(1, $ranked[0]['rank_position']);
        $this->assertEqualsWithDelta(0.1285, (float) $ranked[0]['d_plus'], 5e-5);
        $this->assertEqualsWithDelta(0.2647, (float) $ranked[0]['d_minus'], 5e-5);
        $this->assertEqualsWithDelta(0.6732, (float) $ranked[0]['preference_score'], 5e-5);

        $this->assertSame('A1', $ranked[1]['road_reference_code']);
        $this->assertSame(2, $ranked[1]['rank_position']);
        $this->assertEqualsWithDelta(0.1085, (float) $ranked[1]['d_plus'], 5e-5);
        $this->assertEqualsWithDelta(0.1959, (float) $ranked[1]['d_minus'], 5e-5);
        $this->assertEqualsWithDelta(0.6436, (float) $ranked[1]['preference_score'], 5e-5);

        $this->assertSame('A3', $ranked[2]['road_reference_code']);
        $this->assertSame(3, $ranked[2]['rank_position']);
        $this->assertEqualsWithDelta(0.1799, (float) $ranked[2]['d_plus'], 5e-5);
        $this->assertEqualsWithDelta(0.1391, (float) $ranked[2]['d_minus'], 5e-5);
        $this->assertEqualsWithDelta(0.4360, (float) $ranked[2]['preference_score'], 5e-5);

        $this->assertSame('A4', $ranked[3]['road_reference_code']);
        $this->assertSame(4, $ranked[3]['rank_position']);
        $this->assertEqualsWithDelta(0.2665, (float) $ranked[3]['d_plus'], 5e-5);
        $this->assertEqualsWithDelta(0.1078, (float) $ranked[3]['d_minus'], 5e-5);
        $this->assertEqualsWithDelta(0.2880, (float) $ranked[3]['preference_score'], 5e-5);

        // Segmen A5 tercatat eksplisit pada excluded_items karena tidak memiliki asesmen K1 & K2
        $excluded = $runRes->json('data.excluded_items');
        $this->assertSame('A5', $excluded[0]['road_reference_code']);
        $this->assertStringContainsString('missing_or_invalid:K1', (string) $excluded[0]['exclusion_reason']);
    }

    public function test_inconsistent_ahp_matrix_cannot_be_activated_or_used_in_official_run(): void
    {
        $criteriaByCode = $this->configureLampiranCCriteria();

        $admin = $this->createUserWithRole(User::ROLE_ADMIN, 'admin-inc@roadpriority.test');
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'officer-inc@roadpriority.test');
        $this->seedLampiranCSegmentsAndAssessments($officer);

        Sanctum::actingAs($admin);

        $k1 = $criteriaByCode['K1'];
        $k2 = $criteriaByCode['K2'];
        $k5 = $criteriaByCode['K5'];

        $draftRes = $this->postJson('/api/criterion-weight-versions', [
            'version_name' => 'Matriks Inkonsisten Uji',
        ])->assertStatus(201);

        $versionId = (int) $draftRes->json('data.id');

        // Siklus intransitif K1 > K2 (9), K2 > K5 (9), tetapi K1 < K5 (1/9) -> CR >> 0,10
        $calcRes = $this->postJson("/api/criterion-weight-versions/{$versionId}/calculate", [
            'criterion_ids' => [$k1->id, $k2->id, $k5->id],
            'comparisons' => [
                ['criterion_a_id' => $k1->id, 'criterion_b_id' => $k2->id, 'comparison_value' => 9],
                ['criterion_a_id' => $k2->id, 'criterion_b_id' => $k5->id, 'comparison_value' => 9],
                ['criterion_a_id' => $k1->id, 'criterion_b_id' => $k5->id, 'comparison_value' => 1 / 9],
            ],
        ]);

        $calcRes->assertStatus(200)
            ->assertJsonPath('data.is_consistent', false);
        $this->assertGreaterThan(0.10, (float) $calcRes->json('data.consistency_ratio'));

        // Aktivasi versi bobot dengan CR > 0,10 wajib ditolak (422)
        $this->postJson("/api/criterion-weight-versions/{$versionId}/activate")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'weight_version_inconsistent');

        // Eksekusi TOPSIS resmi dengan versi bobot belum disetujui / inkonsisten wajib ditolak (422)
        $this->postJson('/api/decision-runs', [
            'name' => 'Run Gagal Bobot Inkonsisten',
            'criterion_weight_version_id' => $versionId,
            'is_simulation' => false,
        ])->assertStatus(422);
    }

    public function test_historical_snapshot_reproducibility_and_criterion_immutability(): void
    {
        $criteriaByCode = $this->configureLampiranCCriteria();

        $admin = $this->createUserWithRole(User::ROLE_ADMIN, 'admin-rep@roadpriority.test');
        $officer = $this->createUserWithRole(User::ROLE_OFFICER, 'officer-rep@roadpriority.test');
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER, 'reporter-rep@roadpriority.test');
        $segments = $this->seedLampiranCSegmentsAndAssessments($officer);

        Sanctum::actingAs($admin);

        // Buat, hitung, dan aktifkan versi bobot
        $versionId = (int) $this->postJson('/api/criterion-weight-versions', [
            'version_name' => 'Bobot Historis v1',
        ])->json('data.id');

        $this->postJson("/api/criterion-weight-versions/{$versionId}/calculate", [
            'comparisons' => $this->lampiranCPairwiseComparisons($criteriaByCode),
        ])->assertStatus(200);

        $this->postJson("/api/criterion-weight-versions/{$versionId}/activate")
            ->assertStatus(200);

        // Jalankan decision run resmi
        $runRes = $this->postJson('/api/decision-runs', [
            'name' => 'Run Historis #1',
            'criterion_weight_version_id' => $versionId,
            'is_simulation' => false,
        ])->assertStatus(201);

        $runId = (int) $runRes->json('data.id');
        $originalHash = (string) $runRes->json('data.input_hash');
        $originalRank1Code = (string) $runRes->json('data.ranked_items.0.road_reference_code');
        $originalRank1Score = (float) $runRes->json('data.ranked_items.0.preference_score');

        // Sekarang ubah data kondisi lapangan pada segmen A4 (yang tadinya peringkat 4) menjadi sangat parah
        $this->postJson('/api/assessments', [
            'road_segment_id' => $segments['A4']->id,
            'severity_level' => 5,
            'safety_risk_score' => 5,
            'road_function_score' => 4,
            'status' => 'final',
            'assessment_source' => 'field_inspection',
        ])->assertStatus(201);

        // Pastikan hasil decision run historis #1 TIDAK berubah sedikit pun dan tetap reproducible
        $showRes = $this->getJson("/api/decision-runs/{$runId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.input_hash', $originalHash)
            ->assertJsonPath('data.reproducibility_verified', true)
            ->assertJsonPath('data.ranked_items.0.road_reference_code', $originalRank1Code);
        $this->assertEqualsWithDelta($originalRank1Score, (float) $showRes->json('data.ranked_items.0.preference_score'), 1e-12);

        // Uji immutability kriteria historis (PRD 05 §4.2):
        // Mengubah criterion_type dari K1 yang sudah dipakai di versi bobot harus ditolak 409 Conflict
        $k1 = $criteriaByCode['K1'];
        $this->patchJson("/api/criteria/{$k1->id}", [
            'criterion_type' => 'cost',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'HISTORICAL_CRITERION_IMMUTABLE');

        // Namun memperbarui deskripsi tekstual tanpa mengubah semantik perhitungan diizinkan
        $this->patchJson("/api/criteria/{$k1->id}", [
            'description' => 'Tingkat keparahan kerusakan permukaan jalan (diperbarui kejelasannya).',
        ])->assertStatus(200);

        // Reporter tidak memiliki akses ke endpoint Decision Engine
        Sanctum::actingAs($reporter);
        $this->getJson('/api/criteria')->assertStatus(403);
        $this->getJson('/api/criterion-weight-versions')->assertStatus(403);
        $this->getJson('/api/decision-data-readiness')->assertStatus(403);
        $this->getJson('/api/decision-runs')->assertStatus(403);
    }
}
