<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ElementoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('api')->group(function () {
    // Health check endpoint
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'termovault-api',
            'timestamp' => now()->toIso8601String(),
            'version' => '0.1.0',
        ], 200);
    });

    Route::middleware('cognito.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/elementos', [ElementoController::class, 'listElements']);
    });
});
