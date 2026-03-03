<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AthkarController;
use App\Http\Controllers\Api\JsErrorReportController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::get('/'.config('app.custom.native_end_points.athkar'), AthkarController::class)
        ->middleware('throttle:athkar')
        ->name('athkar.index');

    Route::get('/settings', SettingsController::class)
        ->middleware('throttle:settings')
        ->name('settings.index');

    Route::post('/js-error-reports', JsErrorReportController::class)
        ->middleware('throttle:js-error-reports')
        ->name('js-error-reports.store');
});
