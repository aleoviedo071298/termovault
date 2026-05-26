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

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('cognito.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/catalogos', [\App\Http\Controllers\CatalogController::class, 'index']);

        Route::middleware('role.claim:admin,supervisor,tecnico')->group(function () {
            Route::get('/elementos', [ElementoController::class, 'listElements']);
            Route::get('/elementos/{id}', [ElementoController::class, 'show']);
            Route::post('/inspecciones', [\App\Http\Controllers\InspeccionController::class, 'store']);
        });

        Route::middleware('role.claim:admin,supervisor')->group(function () {
            Route::post('/elementos', [ElementoController::class, 'store']);
            Route::put('/elementos/{id}', [ElementoController::class, 'update']);
            Route::delete('/elementos/{id}', [ElementoController::class, 'destroy']);
        });
    });
});
