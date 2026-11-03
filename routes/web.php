<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Health Check & System Status
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'online',
        'service' => 'Sangguniang Kabataan Portal API',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});
