<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminOrganizationController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\DashboardController;
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
        // FIX [010]: no exponemos la versión en el endpoint público para evitar
        // fingerprinting. El estado alcanza para health checks de monitoreo.
        return response()->json([
            'status' => 'ok',
            'service' => 'termovault-api',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    });

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('cognito.auth')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/catalogos', [\App\Http\Controllers\CatalogController::class, 'index']);

        Route::middleware('role.claim:admin,supervisor')->group(function () {
            Route::get('/elementos/yacimientos', [ElementoController::class, 'listElementYacimientoOptions']);
        });

        Route::middleware('role.claim:admin,supervisor,tecnico')->group(function () {
            Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
            Route::get('/elementos', [ElementoController::class, 'listElements']);
            Route::get('/elementos/{id}', [ElementoController::class, 'show']);
            Route::get('/inspecciones/{id}', [\App\Http\Controllers\InspeccionController::class, 'show']);
            Route::post('/inspecciones', [\App\Http\Controllers\InspeccionController::class, 'store'])->middleware('throttle:50,60');
            Route::get('/archivos/{id}/download', [ArchivoController::class, 'download']);
        });

        Route::middleware('role.claim:admin,supervisor')->group(function () {
            Route::post('/elementos', [ElementoController::class, 'store']);
            Route::put('/elementos/{id}', [ElementoController::class, 'update']);
            Route::delete('/elementos/{id}', [ElementoController::class, 'destroy']);
            Route::patch('/inspecciones/{id}/estado', [\App\Http\Controllers\InspeccionController::class, 'updateEstado']);
        });

        Route::middleware('role.claim:admin')->group(function () {
            Route::get('/admin/usuarios', [AdminUserController::class, 'index']);
            Route::get('/admin/usuarios/meta', [AdminUserController::class, 'meta']);
            Route::post('/admin/usuarios', [AdminUserController::class, 'store']);
            Route::put('/admin/usuarios/{id}', [AdminUserController::class, 'update']);
            Route::post('/admin/empresas', [AdminOrganizationController::class, 'createEmpresa']);
            Route::post('/admin/yacimientos', [AdminOrganizationController::class, 'createYacimiento']);
        });
    });
});
