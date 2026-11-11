<?php

use App\Http\Controllers\Api\EnergyImportController;
use App\Http\Controllers\Api\IntegrationMoodStateController;
use App\Http\Controllers\Api\JournalImportController;
use App\Http\Controllers\Api\MoodImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['integration.token', 'throttle:60,1'])
    ->prefix('v1/integrations')
    ->group(function (): void {
        Route::get('/mood-states', [IntegrationMoodStateController::class, 'index']);
        Route::post('/journal-entries', [JournalImportController::class, 'store']);
        Route::post('/mood-entries', [MoodImportController::class, 'store']);
        Route::post('/energy-entries', [EnergyImportController::class, 'store']);
    });

// CHG-068: acción "+1 vaso" del aviso push. La autoriza la firma temporal, sin sesión ni CSRF.
Route::post('/push/quick-water/{notification}', \App\Http\Controllers\Push\QuickWaterController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->whereUuid('notification')
    ->name('push.quick-water');
