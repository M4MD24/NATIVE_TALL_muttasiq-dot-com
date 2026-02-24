<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AthkarController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::get('/'.config('app.custom.native_end_points.athkar'), AthkarController::class)
        ->middleware('throttle:athkar')
        ->name('athkar.index');

    Route::get('/settings', SettingsController::class)
        ->middleware('throttle:settings')
        ->name('settings.index');
});
