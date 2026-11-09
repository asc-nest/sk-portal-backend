<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\OfficialProfileController;
use App\Http\Controllers\Api\V1\OfficialMeasureController;
use App\Http\Controllers\Api\V1\CouncilSessionController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminActivityLogController;
use App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingsController;

/*
|--------------------------------------------------------------------------
| API Routes - Sangguniang Kabataan Portal
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // -------------------------------------------------------------
    // Public Endpoints
    // -------------------------------------------------------------

    // Authentication & Onboarding
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Invite-Link Flow (Verify & Accept)
    Route::get('/invitations/verify/{token}', [InvitationController::class, 'verify']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    // Public Constituent Directory (General Info) & System Settings
    Route::get('/officials', [OfficialProfileController::class, 'index']);
    Route::get('/officials/{id}', [OfficialProfileController::class, 'show']);
    Route::get('/stats/summary', [StatsController::class, 'summary']);
    Route::get('/system/settings', [AdminSettingsController::class, 'publicSettings']);

    // -------------------------------------------------------------
    // Protected Endpoints (Requires Laravel Sanctum Bearer Token)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'check_maintenance'])->group(function () {
        // Authenticated Session
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Generate Invite Links (Admin & Chairperson)
        Route::post('/invitations/generate', [InvitationController::class, 'generate']);

        // Official Profile Management
        Route::get('/profile', [OfficialProfileController::class, 'current']);
        Route::put('/profile', [OfficialProfileController::class, 'update']);
        Route::post('/profile/avatar', [OfficialProfileController::class, 'uploadAvatar']);

        // Legislative Records & Authored Measures (Item 1)
        Route::get('/profile/measures', [OfficialMeasureController::class, 'index']);
        Route::post('/profile/measures', [OfficialMeasureController::class, 'store']);
        Route::put('/profile/measures/{id}', [OfficialMeasureController::class, 'update']);
        Route::delete('/profile/measures/{id}', [OfficialMeasureController::class, 'destroy']);

        // Council Sessions & Roll Call Ledger (Item 2-A)
        Route::get('/sessions', [CouncilSessionController::class, 'index']);
        Route::get('/sessions/{id}', [CouncilSessionController::class, 'show']);
        Route::post('/sessions', [CouncilSessionController::class, 'store']);
        Route::put('/sessions/{id}', [CouncilSessionController::class, 'update']);
        Route::delete('/sessions/{id}', [CouncilSessionController::class, 'destroy']);
        Route::get('/profile/attendance-history', [CouncilSessionController::class, 'officialHistory']);

        // -------------------------------------------------------------
        // Super Admin Management Suite (Requires super_admin privilege)
        // -------------------------------------------------------------
        Route::prefix('admin')->middleware('super_admin')->group(function () {
            // Dashboard metrics & activity feed
            Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);

            // User & Profile Management
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{id}', [AdminUserController::class, 'show']);
            Route::put('/users/{id}', [AdminUserController::class, 'update']);
            Route::patch('/users/{id}/status', [AdminUserController::class, 'updateStatus']);
            Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::put('/users/{id}/roles', [AdminUserController::class, 'updateRoles']);
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

            // Roles & Permissions RBAC
            Route::get('/roles', [AdminRoleController::class, 'index']);
            Route::put('/roles/{id}/permissions', [AdminRoleController::class, 'update']);

            // Activity Audit Logs
            Route::get('/activity-logs', [AdminActivityLogController::class, 'index']);

            // Reports & Analytics
            Route::get('/analytics', [AdminAnalyticsController::class, 'index']);

            // System Settings
            Route::get('/settings', [AdminSettingsController::class, 'index']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);
        });
    });
});
