<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DecisionCriterionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReportAccessController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| RoadPriority REST API Routes (PRD 01 - PRD 03)
|
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('api.auth.login');

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

    // Damage Report Access & Status Workflow (enforced via DamageReportPolicy + ReportStatusService)
    Route::get('/reports/{damageReport}', [ReportAccessController::class, 'show'])->name('api.reports.show');
    Route::patch('/reports/{damageReport}/status', [ReportAccessController::class, 'updateStatus'])
        ->middleware('role:officer,admin')
        ->name('api.reports.update-status');

    // Decision Criteria Configuration (read: officer/expert/admin; create: admin only)
    Route::get('/criteria', [DecisionCriterionController::class, 'index'])
        ->middleware('role:officer,expert,admin')
        ->name('api.criteria.index');
    Route::post('/criteria', [DecisionCriterionController::class, 'store'])
        ->middleware('role:admin')
        ->name('api.criteria.store');
});
