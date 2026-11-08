<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TerminalPairingController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\PosSyncController;
use App\Http\Controllers\Api\SettingsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/pos')->group(function () {
    // Pairing Route (can be protected by a different guard or open if using a pairing code system)
    Route::post('/pair', [TerminalPairingController::class, 'pair']);

    // Protected Terminal Routes
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckTenantActive::class])->group(function () {
        Route::get('/catalog/delta', [CatalogController::class, 'delta']);
        Route::post('/sync/sales', [PosSyncController::class, 'syncSales']);
        Route::post('/sync/wastes', [PosSyncController::class, 'syncWastes']);
        Route::get('/settings', [SettingsController::class, 'index']);
    });
});
