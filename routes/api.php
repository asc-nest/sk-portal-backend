<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\OfficialProfileController;
use App\Http\Controllers\Api\V1\OfficialMeasureController;
use App\Http\Controllers\Api\V1\CouncilSessionController;
use App\Http\Controllers\Api\V1\StatsController;

/*
|--------------------------------------------------------------------------
| API Routes - Sangguniang Kabataan Portal
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // -------------------------------------------------------------
    // Public Endpoints
    // -------------------------------------------------------------

    // Authentication (Login)
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Invite-Link Flow (Verify & Accept)
    Route::get('/invitations/verify/{token}', [InvitationController::class, 'verify']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    // Public Constituent Directory (General Info)
    Route::get('/officials', [OfficialProfileController::class, 'index']);
    Route::get('/officials/{id}', [OfficialProfileController::class, 'show']);
    Route::get('/stats/summary', [StatsController::class, 'summary']);

    // -------------------------------------------------------------
    // Protected Endpoints (Requires Laravel Sanctum Bearer Token)
    // -------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
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
    });
});
