<?php

namespace Tests\Feature;

use App\Models\AdministrativeArea;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\ReportPhoto;
use App\Models\ReportStatusHistory;
use App\Models\RoadSegment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DamageReportService;
use App\Services\GeoGeometryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class DamageReportingAndWebGisTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role, bool $isActive = true, ?string $email = null): User
    {
        $user = new User([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? ($role . '.' . uniqid() . '@roadpriority.test'),
            'password' => Hash::make('SecretPass123!'),
        ]);
        $user->role = $role;
        $user->is_active = $isActive;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function createRoadSegment(
        string $code = 'SEG-BSD-01',
        float $lat = -6.3015000,
        float $lng = 106.6525000,
        bool $isDemo = false
    ): RoadSegment {
        $area = AdministrativeArea::query()->create([
            'name' => 'Serpong',
            'administrative_level' => 'kecamatan',
            'external_code' => 'AREA-' . uniqid(),
            'is_study_area' => true,
        ]);

        return RoadSegment::query()->create([
            'administrative_area_id' => $area->id,
            'name' => 'Jl. Pahlawan Seribu ' . $code,
            'road_reference_code' => $code,
            'geometry_geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [$lng - 0.0010, $lat],
                    [$lng + 0.0010, $lat],
                ],
            ],
            'centroid_latitude' => $lat,
            'centroid_longitude' => $lng,
            'length_meters' => 220.5,
            'road_function' => 'arteri',
            'road_authority' => 'kota',
            'nearest_facility_meters' => 85.0,
            'source_name' => 'OpenStreetMap',
            'data_origin' => $isDemo ? 'demo' : 'public_dataset',
            'is_demo' => $isDemo,
            'is_active' => true,
        ]);
    }

    private function createValidPngFile(string $filename = 'bukti.png'): UploadedFile
    {
        // 1x1 pixel valid PNG binary (passes finfo MIME image/png and getimagesize() without requiring ext-gd)
        $pngBinary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true
        );

        return UploadedFile::fake()->createWithContent($filename, $pngBinary ?: '');
    }

    public function test_reporter_can_submit_report_without_segment_with_valid_photo_and_server_generates_unique_code(): void
    {
        Storage::fake('local');
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $otherUser = $this->createUserWithRole(User::ROLE_REPORTER);

        Sanctum::actingAs($reporter);

        $photo = $this->createValidPngFile('lubang-bsd.png');

        $response = $this->postJson('/api/reports', [
            'damage_type' => 'lubang',
            'description' => 'Terdapat lubang jalan sedalam 12 cm di lajur kiri arah simpang German Centre.',
            'latitude' => -6.3015200,
            'longitude' => 106.6525100,
            'gps_accuracy_m' => 6.4,
            // Attempt to spoof report_code, status, and reporter_id (must be ignored by server)
            'report_code' => 'SPOOFED-CODE-001',
            'status' => 'verified',
            'reporter_id' => $otherUser->id,
            'photos' => [$photo],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DamageReport::STATUS_SUBMITTED)
            ->assertJsonPath('data.reporter_id', $reporter->id)
            ->assertJsonPath('data.road_segment_id', null)
            ->assertJsonPath('data.damage_type', 'lubang')
            ->assertJsonPath('data.study_area_check.within_reference_area', true);

        $reportCode = $response->json('data.report_code');
        $this->assertNotSame('SPOOFED-CODE-001', $reportCode);
        $this->assertMatchesRegularExpression('/^RPT-\d{8}-[A-Z0-9]{6}$/', $reportCode);

        $reportId = (int) $response->json('data.id');
        $this->assertDatabaseHas('report_status_histories', [
            'damage_report_id' => $reportId,
            'previous_status' => null,
            'new_status' => DamageReport::STATUS_SUBMITTED,
            'changed_by' => $reporter->id,
        ]);

        $savedPhoto = ReportPhoto::query()->where('damage_report_id', $reportId)->first();
        $this->assertNotNull($savedPhoto);
        $this->assertFalse((bool) $savedPhoto->is_public);
        $this->assertSame('image/png', $savedPhoto->mime_type);
        $this->assertSame(1, $savedPhoto->width);
        $this->assertSame(1, $savedPhoto->height);
        $this->assertStringNotContainsString('lubang-bsd.png', $savedPhoto->file_path);
        Storage::disk('local')->assertExists($savedPhoto->file_path);
    }

    public function test_report_submission_rejects_invalid_coordinates_and_disguised_non_image_files(): void
    {
        Storage::fake('local');
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        Sanctum::actingAs($reporter);

        // 1. Out-of-bounds coordinates & short description
        $this->postJson('/api/reports', [
            'damage_type' => 'retak',
            'description' => 'Pendek',
            'latitude' => -95.0,
            'longitude' => 185.0,
        ])->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['description', 'latitude', 'longitude']]);

        // 2. Disguised PHP script renamed to .jpg
        $fakeImage = UploadedFile::fake()->createWithContent('exploit.jpg', "<?php echo 'hacked'; ?>");
        $this->postJson('/api/reports', [
            'damage_type' => 'retak',
            'description' => 'Retak memanjang sekitar 4 meter di permukaan aspal.',
            'latitude' => -6.3015,
            'longitude' => 106.6525,
            'photos' => [$fakeImage],
        ])->assertStatus(422);

        $this->assertSame(0, DamageReport::query()->count());
    }

    public function test_orphan_uploaded_files_are_cleaned_up_if_database_transaction_fails(): void
    {
        Storage::fake('local');
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $photo = $this->createValidPngFile('valid-photo.png');

        $failingAudit = new class extends AuditLogService {
            public function record(
                ?User $actor,
                string $action,
                string $entityType,
                ?int $entityId = null,
                ?array $changesSummary = null
            ): AuditLog {
                throw new RuntimeException('Simulated DB failure after file upload.');
            }
        };

        $service = new DamageReportService($failingAudit, new GeoGeometryService());

        try {
            $service->createReport($reporter, [
                'damage_type' => 'lubang',
                'description' => 'Lubang besar yang seharusnya dibatalkan karena gagal simpan audit.',
                'latitude' => -6.3015,
                'longitude' => 106.6525,
            ], [$photo]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated DB failure after file upload.', $e->getMessage());
        }

        $this->assertSame(0, DamageReport::query()->count());
        $this->assertSame(0, ReportPhoto::query()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('report-photos'));
    }

    public function test_photo_and_report_visibility_enforced_across_guest_reporter_and_officer_with_correction_trail(): void
    {
        Storage::fake('local');
        $reporterA = $this->createUserWithRole(User::ROLE_REPORTER);
        $reporterB = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $segment = $this->createRoadSegment('SEG-BSD-10', -6.3015000, 106.6525000);

        Sanctum::actingAs($reporterA);
        $createRes = $this->postJson('/api/reports', [
            'damage_type' => 'aspal_mengelupas',
            'description' => 'Lapisan aspal mengelupas sepanjang 3 meter di dekat bundaran.',
            'latitude' => -6.3015100,
            'longitude' => 106.6525000,
            'photos' => [$this->createValidPngFile('bukti.png')],
        ])->assertStatus(201);

        $reportId = (int) $createRes->json('data.id');
        $photoId = (int) $createRes->json('data.photos.0.id');

        // 1. Reporter B cannot access Reporter A's unverified report or photo (403)
        Sanctum::actingAs($reporterB);
        $this->getJson("/api/reports/{$reportId}")->assertStatus(403);
        $this->get("/api/reports/{$reportId}/photos/{$photoId}")->assertStatus(403);

        // 2. Reporter A (owner) can access their own unverified report and photo (200)
        Sanctum::actingAs($reporterA);
        $this->getJson("/api/reports/{$reportId}")->assertStatus(200);
        $this->get("/api/reports/{$reportId}/photos/{$photoId}")->assertStatus(200);

        // 3. Officer sees suggested_segments and transitions submitted -> under_review -> verified
        Sanctum::actingAs($officer);
        $officerDetail = $this->getJson("/api/reports/{$reportId}")->assertStatus(200);
        $this->assertNotEmpty($officerDetail->json('data.suggested_segments'));
        $this->assertSame($segment->id, $officerDetail->json('data.suggested_segments.0.id'));

        $this->patchJson("/api/reports/{$reportId}/status", [
            'new_status' => DamageReport::STATUS_UNDER_REVIEW,
            'notes' => 'Meninjau foto dan koordinat laporan.',
        ])->assertStatus(200);

        $this->patchJson("/api/reports/{$reportId}/status", [
            'new_status' => DamageReport::STATUS_VERIFIED,
            'road_segment_id' => $segment->id,
            'notes' => 'Terverifikasi masuk ke segmen SEG-BSD-10.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', DamageReport::STATUS_VERIFIED)
            ->assertJsonPath('data.road_segment_id', $segment->id);

        // 4. Now that report is verified, another user (Reporter B) can view public report & photo,
        //    but reporter_id and internal verification_notes are hidden!
        Sanctum::actingAs($reporterB);
        $publicDetail = $this->getJson("/api/reports/{$reportId}")->assertStatus(200);
        $publicDetail->assertJsonPath('data.reporter_id', null)
            ->assertJsonPath('data.verification_notes', null);
        $this->get("/api/reports/{$reportId}/photos/{$photoId}")->assertStatus(200);

        // 5. Officer corrects verified report back to under_review: history is preserved and photo becomes private again
        Sanctum::actingAs($officer);
        $this->patchJson("/api/reports/{$reportId}/status", [
            'new_status' => DamageReport::STATUS_UNDER_REVIEW,
            'notes' => 'Koreksi verifikasi untuk pengecekan ulang segmen.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', DamageReport::STATUS_UNDER_REVIEW);

        $this->assertSame(
            4,
            ReportStatusHistory::query()->where('damage_report_id', $reportId)->count()
        );

        Sanctum::actingAs($reporterB);
        $this->get("/api/reports/{$reportId}/photos/{$photoId}")->assertStatus(403);
    }

    public function test_duplicate_status_transition_and_nearby_duplicate_candidate_detection(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);

        Sanctum::actingAs($reporter);
        $firstId = (int) $this->postJson('/api/reports', [
            'damage_type' => 'lubang',
            'description' => 'Laporan pertama mengenai lubang di dekat lampu merah BSD.',
            'latitude' => -6.3020000,
            'longitude' => 106.6530000,
        ])->assertStatus(201)->json('data.id');

        $secondId = (int) $this->postJson('/api/reports', [
            'damage_type' => 'lubang',
            'description' => 'Laporan kedua di titik yang hampir sama dengan laporan pertama.',
            'latitude' => -6.3020500,
            'longitude' => 106.6530500,
        ])->assertStatus(201)->json('data.id');

        Sanctum::actingAs($officer);
        $detailRes = $this->getJson("/api/reports/{$secondId}")->assertStatus(200);
        $candidates = $detailRes->json('data.nearby_duplicate_candidates');
        $this->assertNotEmpty($candidates);
        $this->assertSame($firstId, $candidates[0]['id']);

        $this->patchJson("/api/reports/{$secondId}/status", [
            'new_status' => DamageReport::STATUS_UNDER_REVIEW,
        ])->assertStatus(200);

        // Marking duplicate without duplicate_of_id fails with 422
        $this->patchJson("/api/reports/{$secondId}/status", [
            'new_status' => DamageReport::STATUS_DUPLICATE,
            'notes' => 'Duplikat laporan sebelumnya.',
        ])->assertStatus(422);

        // Marking duplicate of itself fails with 409
        $this->patchJson("/api/reports/{$secondId}/status", [
            'new_status' => DamageReport::STATUS_DUPLICATE,
            'duplicate_of_id' => $secondId,
            'notes' => 'Duplikat diri sendiri.',
        ])->assertStatus(409);

        // Valid duplicate transition succeeds
        $this->patchJson("/api/reports/{$secondId}/status", [
            'new_status' => DamageReport::STATUS_DUPLICATE,
            'duplicate_of_id' => $firstId,
            'notes' => 'Lokasi dan waktu identik dengan laporan pertama.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', DamageReport::STATUS_DUPLICATE)
            ->assertJsonPath('data.duplicate_of_id', $firstId);
    }

    public function test_public_reports_and_road_segments_support_filters_bbox_pagination_and_exclude_demo_data(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $realSegment = $this->createRoadSegment('SEG-REAL-01', -6.3015000, 106.6525000, false);
        $this->createRoadSegment('SEG-DEMO-01', -6.3015000, 106.6525000, true);

        // 1 verified real report inside BSD bbox
        $verifiedReal = DamageReport::query()->create([
            'report_code' => 'RPT-20261029-REAL01',
            'reporter_id' => $reporter->id,
            'road_segment_id' => $realSegment->id,
            'damage_type' => 'lubang',
            'description' => 'Laporan terverifikasi di area BSD.',
            'latitude' => -6.3015000,
            'longitude' => 106.6525000,
            'reported_at' => now(),
            'status' => DamageReport::STATUS_VERIFIED,
            'verification_notes' => 'Catatan internal rahasia petugas',
            'data_origin' => 'citizen_report',
            'is_demo' => false,
        ]);

        // 1 submitted (unverified) real report
        DamageReport::query()->create([
            'report_code' => 'RPT-20261029-SUBM01',
            'reporter_id' => $reporter->id,
            'damage_type' => 'retak',
            'description' => 'Laporan belum diverifikasi.',
            'latitude' => -6.3016000,
            'longitude' => 106.6526000,
            'reported_at' => now(),
            'status' => DamageReport::STATUS_SUBMITTED,
            'data_origin' => 'citizen_report',
            'is_demo' => false,
        ]);

        // 1 verified DEMO report (must never appear in public endpoints)
        DamageReport::query()->create([
            'report_code' => 'RPT-20261029-DEMO01',
            'reporter_id' => $reporter->id,
            'damage_type' => 'lubang',
            'description' => 'Laporan simulasi demo.',
            'latitude' => -6.3015000,
            'longitude' => 106.6525000,
            'reported_at' => now(),
            'status' => DamageReport::STATUS_VERIFIED,
            'data_origin' => 'demo',
            'is_demo' => true,
        ]);

        // Public GET /api/reports returns only the 1 verified non-demo report
        $listRes = $this->getJson('/api/reports?bbox=106.60,-6.35,106.70,-6.25&per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.items.0.report_code', $verifiedReal->report_code)
            ->assertJsonPath('data.items.0.reporter_id', null);

        $this->assertArrayNotHasKey('verification_notes', $listRes->json('data.items.0'));

        // Public guest requesting status=submitted receives 403
        $this->getJson('/api/reports?status=submitted')->assertStatus(403);

        // Invalid bbox or excessive per_page (> 100) receives 422
        $this->getJson('/api/reports?bbox=invalid_bbox')->assertStatus(422);
        $this->getJson('/api/reports?per_page=500')->assertStatus(422);

        // Public GET /api/road-segments returns only non-demo active segment with GeoJSON [lng, lat]
        $segRes = $this->getJson('/api/road-segments?bbox=106.60,-6.35,106.70,-6.25')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.items.0.road_reference_code', 'SEG-REAL-01')
            ->assertJsonPath('data.items.0.verified_reports_count', 1);

        $coords = $segRes->json('data.items.0.geometry_geojson.coordinates.0');
        // Verify GeoJSON [longitude, latitude] order
        $this->assertGreaterThan(100.0, $coords[0]);
        $this->assertLessThan(0.0, $coords[1]);
    }
}
