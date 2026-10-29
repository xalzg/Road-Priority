<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CriterionWeightVersionController;
use App\Http\Controllers\Api\DamageAssessmentController;
use App\Http\Controllers\Api\DecisionCriterionController;
use App\Http\Controllers\Api\DecisionRunController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReportAccessController;
use App\Http\Controllers\Api\RoadSegmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| RoadPriority REST API Routes (PRD 01 - PRD 05)
|
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.auth.login');

// Endpoint WebGIS & Pelaporan yang mendukung akses publik terbatas (hanya verified & non-demo)
// maupun akses penuh berbasis token Sanctum (melalui pemeriksaan DamageReportPolicy di controller).
Route::get('/road-segments', [RoadSegmentController::class, 'index'])->name('api.road-segments.index');
Route::get('/road-segments/{segmentIdentifier}', [RoadSegmentController::class, 'show'])->name('api.road-segments.show');
Route::get('/reports', [ReportAccessController::class, 'index'])->name('api.reports.index');
Route::get('/reports/{reportIdentifier}', [ReportAccessController::class, 'show'])->name('api.reports.show');
Route::get('/reports/{damageReport}/photos/{reportPhoto}', [ReportAccessController::class, 'showPhoto'])
    ->name('api.reports.photos.show');

Route::middleware(['auth:sanctum', 'active.user'])->group(function (): void {
    // Authentication & Identity
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');

    // User & Role Management (Admin only via role middleware + UserPolicy)
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('api.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('api.users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('api.users.update');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('api.audit-logs.index');
    });

    // Damage Report Creation & Officer Verification Workflow (PRD 04)
    Route::post('/reports', [ReportAccessController::class, 'store'])
        ->middleware('role:reporter,officer,admin')
        ->name('api.reports.store');
    Route::patch('/reports/{damageReport}/status', [ReportAccessController::class, 'updateStatus'])
        ->middleware('role:officer,admin')
        ->name('api.reports.update-status');

    // Field Damage Assessments (PRD 05 Data Readiness & Segment Assessment)
    Route::get('/assessments', [DamageAssessmentController::class, 'index'])
        ->middleware('role:officer,expert,admin')
        ->name('api.assessments.index');
    Route::post('/assessments', [DamageAssessmentController::class, 'store'])
        ->middleware('role:officer,admin')
        ->name('api.assessments.store');

    // Decision Criteria Configuration (read: officer/expert/admin; create/update: admin only)
    Route::get('/criteria', [DecisionCriterionController::class, 'index'])
        ->middleware('role:officer,expert,admin')
        ->name('api.criteria.index');
    Route::post('/criteria', [DecisionCriterionController::class, 'store'])
        ->middleware('role:admin')
        ->name('api.criteria.store');
    Route::patch('/criteria/{decisionCriterion}', [DecisionCriterionController::class, 'update'])
        ->middleware('role:admin')
        ->name('api.criteria.update');

    // AHP Pairwise Comparison & Criterion Weight Versions (PRD 05 §5 & §11)
    Route::get('/criterion-weight-versions', [CriterionWeightVersionController::class, 'index'])
        ->middleware('role:officer,expert,admin')
        ->name('api.weight-versions.index');
    Route::get('/criterion-weight-versions/{criterionWeightVersion}', [CriterionWeightVersionController::class, 'show'])
        ->middleware('role:officer,expert,admin')
        ->name('api.weight-versions.show');
    Route::post('/criterion-weight-versions', [CriterionWeightVersionController::class, 'store'])
        ->middleware('role:expert,admin')
        ->name('api.weight-versions.store');
    Route::post('/criterion-weight-versions/{criterionWeightVersion}/calculate', [CriterionWeightVersionController::class, 'calculate'])
        ->middleware('role:expert,admin')
        ->name('api.weight-versions.calculate');
    Route::post('/criterion-weight-versions/{criterionWeightVersion}/activate', [CriterionWeightVersionController::class, 'activate'])
        ->middleware('role:admin')
        ->name('api.weight-versions.activate');

    // Decision Engine Data Readiness & TOPSIS Execution (PRD 05 §6, §7, §8, §11)
    Route::get('/decision-data-readiness', [DecisionRunController::class, 'readiness'])
        ->middleware('role:officer,expert,admin')
        ->name('api.decision-runs.readiness');
    Route::get('/decision-runs', [DecisionRunController::class, 'index'])
        ->middleware('role:officer,expert,admin')
        ->name('api.decision-runs.index');
    Route::post('/decision-runs', [DecisionRunController::class, 'store'])
        ->middleware('role:officer,expert,admin')
        ->name('api.decision-runs.store');
    Route::get('/decision-runs/{decisionRun}', [DecisionRunController::class, 'show'])
        ->middleware('role:officer,expert,admin')
        ->name('api.decision-runs.show');
});
