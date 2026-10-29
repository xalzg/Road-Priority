<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Models\AdministrativeArea;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\DecisionCriterion;
use App\Models\ReportStatusHistory;
use App\Models\RoadSegment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ReportStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AuthAndAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRole(string $role, bool $isActive = true, ?string $email = null): User
    {
        $user = new User([
            'name' => ucfirst($role) . ' Test User',
            'email' => $email ?? ($role . '.' . uniqid() . '@roadpriority.test'),
            'password' => Hash::make('SecretPass123!'),
        ]);
        $user->role = $role;
        $user->is_active = $isActive;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function createRoadSegment(): RoadSegment
    {
        $area = AdministrativeArea::query()->create([
            'name' => 'Pagedangan',
            'administrative_level' => 'kecamatan',
            'external_code' => 'ID-BT-TGR-PAG-' . uniqid(),
            'is_study_area' => true,
        ]);

        return RoadSegment::query()->create([
            'administrative_area_id' => $area->id,
            'name' => 'Jl. BSD Grand Boulevard Segmen 1',
            'road_reference_code' => 'SEG-' . strtoupper(uniqid()),
            'geometry_geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [106.6510000, -6.3010000],
                    [106.6540000, -6.3020000],
                ],
            ],
            'centroid_latitude' => -6.3015000,
            'centroid_longitude' => 106.6525000,
            'length_meters' => 450.00,
            'road_function' => 'kolektor',
            'road_authority' => 'kabupaten',
            'nearest_facility_meters' => 120.00,
            'source_name' => 'OSM + Survey Lapangan',
            'data_origin' => 'field_survey',
            'is_demo' => false,
            'is_active' => true,
        ]);
    }

    private function createDamageReport(
        User $reporter,
        string $status = DamageReport::STATUS_SUBMITTED,
        ?RoadSegment $segment = null
    ): DamageReport {
        return DamageReport::query()->create([
            'report_code' => 'RPT-' . strtoupper(uniqid()),
            'reporter_id' => $reporter->id,
            'road_segment_id' => $segment?->id,
            'damage_type' => 'berlubang',
            'description' => 'Terdapat lubang berdiameter sekitar 80 cm yang membahayakan pengendara motor.',
            'latitude' => -6.3015000,
            'longitude' => 106.6525000,
            'gps_accuracy_m' => 8.5,
            'reported_at' => now(),
            'status' => $status,
            'data_origin' => 'citizen_report',
            'is_demo' => false,
        ]);
    }

    public function test_login_succeeds_with_valid_credentials_and_records_audit_log(): void
    {
        $user = $this->createUserWithRole(User::ROLE_OFFICER, true, 'officer.login@roadpriority.test');

        $response = $this->postJson('/api/login', [
            'email' => 'officer.login@roadpriority.test',
            'password' => 'SecretPass123!',
            'device_name' => 'phpunit-suite',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'officer.login@roadpriority.test')
            ->assertJsonPath('data.user.role', User::ROLE_OFFICER)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.expires_at'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login_succeeded',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_login_fails_with_wrong_password_and_redacts_secret_in_audit_log(): void
    {
        $user = $this->createUserWithRole(User::ROLE_REPORTER, true, 'reporter.wrongpass@roadpriority.test');

        $response = $this->postJson('/api/login', [
            'email' => 'reporter.wrongpass@roadpriority.test',
            'password' => 'WrongSecret999!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
                'errors' => null,
            ]);

        $audit = AuditLog::query()->where('action', 'auth.login_failed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertArrayNotHasKey('password', $audit->changes_summary ?? []);
        $this->assertStringNotContainsString('WrongSecret999!', json_encode($audit->changes_summary));
    }

    public function test_login_fails_for_inactive_account(): void
    {
        $inactive = $this->createUserWithRole(User::ROLE_EXPERT, false, 'inactive.expert@roadpriority.test');

        $response = $this->postJson('/api/login', [
            'email' => 'inactive.expert@roadpriority.test',
            'password' => 'SecretPass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is inactive.',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $inactive->id,
            'action' => 'auth.login_blocked_inactive',
        ]);
    }

    public function test_private_endpoints_reject_unauthenticated_requests_with_401(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => null,
            ]);

        $this->getJson('/api/users')->assertStatus(401);
        $this->postJson('/api/logout')->assertStatus(401);
    }

    public function test_me_endpoint_returns_current_user_and_logout_revokes_sanctum_token(): void
    {
        $user = $this->createUserWithRole(User::ROLE_REPORTER, true, 'me.test@roadpriority.test');
        $plainTextToken = $user->createToken('browser-session')->plainTextToken;

        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'me.test@roadpriority.test')
            ->assertJsonPath('data.role', User::ROLE_REPORTER)
            ->assertJsonMissingPath('data.password');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_deactivated_user_cannot_access_protected_routes_even_with_existing_token(): void
    {
        $user = $this->createUserWithRole(User::ROLE_OFFICER, true);
        $plainTextToken = $user->createToken('active-session')->plainTextToken;

        $user->is_active = false;
        $user->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/me');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_login_endpoint_enforces_rate_limiting(): void
    {
        $email = 'ratelimit.target@roadpriority.test';
        RateLimiter::clear($email . '|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'WrongPass123!',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'WrongPass123!',
        ])->assertStatus(429)
            ->assertJsonPath('success', false);

        RateLimiter::clear($email . '|127.0.0.1');
    }

    public function test_role_authorization_matrix_for_admin_user_and_criteria_endpoints(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $expert = $this->createUserWithRole(User::ROLE_EXPERT);
        $admin = $this->createUserWithRole(User::ROLE_ADMIN);

        // Reporter cannot access user management or criteria management
        Sanctum::actingAs($reporter);
        $this->getJson('/api/users')->assertStatus(403);
        $this->getJson('/api/audit-logs')->assertStatus(403);
        $this->getJson('/api/criteria')->assertStatus(403);
        $this->postJson('/api/criteria', [])->assertStatus(403);

        // Officer can read criteria, but cannot create criteria or manage users
        Sanctum::actingAs($officer);
        $this->getJson('/api/criteria')->assertStatus(200);
        $this->postJson('/api/criteria', [
            'code' => 'C99',
            'name' => 'Kriteria Uji',
            'description' => 'Deskripsi uji',
            'data_type' => 'ordinal',
            'criterion_type' => 'benefit',
            'minimum_value' => 1,
            'maximum_value' => 5,
        ])->assertStatus(403);
        $this->getJson('/api/users')->assertStatus(403);
        $this->postJson('/api/users', [])->assertStatus(403);

        // Expert can read criteria, but cannot create criteria or manage users
        Sanctum::actingAs($expert);
        $this->getJson('/api/criteria')->assertStatus(200);
        $this->postJson('/api/criteria', [
            'code' => 'C98',
            'name' => 'Kriteria Pakar',
            'description' => 'Deskripsi pakar',
            'data_type' => 'ordinal',
            'criterion_type' => 'benefit',
            'minimum_value' => 1,
            'maximum_value' => 5,
        ])->assertStatus(403);
        $this->getJson('/api/users')->assertStatus(403);

        // Admin can manage users, read audit logs, and create criteria
        Sanctum::actingAs($admin);
        $this->getJson('/api/users')->assertStatus(200);
        $this->getJson('/api/audit-logs')->assertStatus(200);

        $createCriterionResponse = $this->postJson('/api/criteria', [
            'code' => 'C10',
            'name' => 'Kepadatan Lalu Lintas Harian',
            'description' => 'Indeks kepadatan lalu lintas pada ruas jalan',
            'data_type' => DecisionCriterion::DATA_TYPE_ORDINAL,
            'unit' => 'skala_1_5',
            'criterion_type' => DecisionCriterion::TYPE_BENEFIT,
            'minimum_value' => 1,
            'maximum_value' => 5,
            'is_required' => true,
            'is_active' => true,
        ]);

        $createCriterionResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'C10');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'criterion.created',
            'entity_type' => 'decision_criterion',
        ]);
    }

    public function test_idor_prevention_and_reporter_privacy_on_damage_reports(): void
    {
        $reporterA = $this->createUserWithRole(User::ROLE_REPORTER);
        $reporterB = $this->createUserWithRole(User::ROLE_REPORTER);
        $expert = $this->createUserWithRole(User::ROLE_EXPERT);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);

        $unverifiedReportOfB = $this->createDamageReport($reporterB, DamageReport::STATUS_SUBMITTED);
        $verifiedReportOfB = $this->createDamageReport($reporterB, DamageReport::STATUS_VERIFIED);

        // Reporter A cannot read Reporter B's submitted report (anti-IDOR)
        Sanctum::actingAs($reporterA);
        $this->getJson("/api/reports/{$unverifiedReportOfB->id}")
            ->assertStatus(403);

        // Reporter B can read their own submitted report and sees their own reporter_id
        Sanctum::actingAs($reporterB);
        $this->getJson("/api/reports/{$unverifiedReportOfB->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.reporter_id', $reporterB->id);

        // Expert cannot read unverified report, can read verified report, but reporter_id is redacted (privacy NFR-09)
        Sanctum::actingAs($expert);
        $this->getJson("/api/reports/{$unverifiedReportOfB->id}")
            ->assertStatus(403);

        $this->getJson("/api/reports/{$verifiedReportOfB->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.reporter_id', null);

        // Officer can read submitted report and see reporter_id for verification workflow
        Sanctum::actingAs($officer);
        $this->getJson("/api/reports/{$unverifiedReportOfB->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.reporter_id', $reporterB->id);
    }

    public function test_reporter_cannot_verify_own_report_while_officer_can_verify_atomically(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $segment = $this->createRoadSegment();
        $report = $this->createDamageReport($reporter, DamageReport::STATUS_SUBMITTED);

        // Reporter attempting to verify own report must receive 403
        Sanctum::actingAs($reporter);
        $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => DamageReport::STATUS_VERIFIED,
            'notes' => 'Saya verifikasi sendiri',
        ])->assertStatus(403);

        // Officer transitions submitted -> under_review -> verified with road_segment_id
        Sanctum::actingAs($officer);
        $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => DamageReport::STATUS_UNDER_REVIEW,
            'notes' => 'Mulai pemeriksaan lapangan',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', DamageReport::STATUS_UNDER_REVIEW);

        $verifyResponse = $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => DamageReport::STATUS_VERIFIED,
            'road_segment_id' => $segment->id,
            'notes' => 'Kerusakan terkonfirmasi pada segmen BSD Grand Boulevard',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', DamageReport::STATUS_VERIFIED)
            ->assertJsonPath('data.road_segment_id', $segment->id)
            ->assertJsonPath('data.verified_by', $officer->id);

        $this->assertDatabaseHas('report_status_histories', [
            'damage_report_id' => $report->id,
            'previous_status' => DamageReport::STATUS_UNDER_REVIEW,
            'new_status' => DamageReport::STATUS_VERIFIED,
            'changed_by' => $officer->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $officer->id,
            'action' => 'report.status_changed',
            'entity_type' => 'damage_report',
            'entity_id' => $report->id,
        ]);
    }

    public function test_invalid_report_status_transition_returns_409_conflict(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $report = $this->createDamageReport($reporter, DamageReport::STATUS_SUBMITTED);

        Sanctum::actingAs($officer);
        // submitted -> resolved directly is forbidden by the state machine
        $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => DamageReport::STATUS_RESOLVED,
            'notes' => 'Langsung selesai tanpa verifikasi',
        ])->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_last_active_admin_cannot_be_deactivated_or_demoted(): void
    {
        $soleAdmin = $this->createUserWithRole(User::ROLE_ADMIN);

        Sanctum::actingAs($soleAdmin);

        // Attempting to demote the sole active admin must fail with 409
        $this->patchJson("/api/users/{$soleAdmin->id}", [
            'role' => User::ROLE_OFFICER,
        ])->assertStatus(409)
            ->assertJsonPath('success', false);

        // Attempting to deactivate the sole active admin must fail with 409
        $this->patchJson("/api/users/{$soleAdmin->id}", [
            'is_active' => false,
        ])->assertStatus(409)
            ->assertJsonPath('success', false);

        // Creating a second active admin allows updating the first admin
        $secondAdminResponse = $this->postJson('/api/users', [
            'name' => 'Second Admin',
            'email' => 'second.admin@roadpriority.test',
            'password' => 'SecondAdmin123!',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $secondAdminResponse->assertStatus(201);

        $this->patchJson("/api/users/{$soleAdmin->id}", [
            'role' => User::ROLE_OFFICER,
        ])->assertStatus(200)
            ->assertJsonPath('data.role', User::ROLE_OFFICER);
    }

    public function test_server_side_validation_rejects_missing_fields_invalid_enums_and_out_of_range_coordinates(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $report = $this->createDamageReport($reporter, DamageReport::STATUS_SUBMITTED);

        // 1. Missing required login fields -> 422
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['email', 'password']]);

        // 2. Rejecting report without notes or with out-of-range coordinates -> 422
        Sanctum::actingAs($officer);
        $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => DamageReport::STATUS_REJECTED,
            'latitude' => -95.123,
            'longitude' => 195.456,
        ])->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['notes', 'latitude', 'longitude']]);

        // 3. Invalid enum status -> 422
        $this->patchJson("/api/reports/{$report->id}/status", [
            'new_status' => 'invalid_status_value',
        ])->assertStatus(422)
            ->assertJsonStructure(['success', 'message', 'errors' => ['new_status']]);
    }

    public function test_multi_table_status_transition_rolls_back_completely_if_audit_step_throws(): void
    {
        $reporter = $this->createUserWithRole(User::ROLE_REPORTER);
        $officer = $this->createUserWithRole(User::ROLE_OFFICER);
        $report = $this->createDamageReport($reporter, DamageReport::STATUS_SUBMITTED);

        $failingAuditService = new class extends AuditLogService {
            public function record(
                ?User $actor,
                string $action,
                string $entityType,
                ?int $entityId = null,
                ?array $changesSummary = null
            ): AuditLog {
                throw new RuntimeException('Simulated database failure during audit log write.');
            }
        };

        $service = new ReportStatusService($failingAuditService);

        try {
            $service->transition(
                actor: $officer,
                report: $report,
                newStatus: DamageReport::STATUS_UNDER_REVIEW,
                notes: 'Should roll back'
            );
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database failure during audit log write.', $e->getMessage());
        }

        // Verify neither damage_reports nor report_status_histories persisted partial state
        $this->assertSame(DamageReport::STATUS_SUBMITTED, $report->fresh()->status);
        $this->assertSame(
            0,
            ReportStatusHistory::query()->where('damage_report_id', $report->id)->count()
        );
    }

    public function test_api_exception_handler_never_leaks_stack_trace_or_sql_details_on_unexpected_errors(): void
    {
        /** @var Handler $handler */
        $handler = app(Handler::class);
        $request = Request::create('/api/reports/1', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $simulatedException = new RuntimeException(
            'SQLSTATE[42S02]: Base table or view not found: 1146 Table roadpriority.secret_internal_table at C:\xampp\htdocs\secret.php:42'
        );

        $response = $handler->render($request, $simulatedException);

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);

        $this->assertFalse($payload['success']);
        $this->assertSame('An unexpected server error occurred.', $payload['message']);
        $this->assertNull($payload['errors']);
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('secret.php', (string) $response->getContent());
    }

    public function test_local_admin_seeder_creates_initial_admin_and_logs_bootstrap_event(): void
    {
        $this->seed(\Database\Seeders\LocalAdminSeeder::class);

        $admin = User::query()->where('email', 'admin@roadpriority.local')->first();
        $this->assertNotNull($admin);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue((bool) $admin->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'user.bootstrap_admin_seeded',
            'entity_type' => 'user',
            'entity_id' => $admin->id,
        ]);
    }
}
