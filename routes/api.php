<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AthkarController;
use Illuminate\Support\Facades\Route;

Route::get('/athkar', AthkarController::class)
    ->middleware('throttle:athkar')
    ->name('athkar.index');
