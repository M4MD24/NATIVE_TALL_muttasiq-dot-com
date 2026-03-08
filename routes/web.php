<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
// use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
// use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', HomeController::class)->name('home');

// if (config('nativephp-internal.running') && is_platform('ios')) {
//     Route::get('/docs/updates/images/{path}', function (string $path): BinaryFileResponse {
//         $imagesDirectory = realpath(public_path('docs/updates/images'));
//         if ($imagesDirectory === false) {
//             abort(404);
//         }
    
//         $requestedPath = realpath(public_path('docs/updates/images/'.$path));
//         if (
//             $requestedPath === false
//             || ! str_starts_with($requestedPath, $imagesDirectory.DIRECTORY_SEPARATOR)
//             || ! is_file($requestedPath)
//         ) {
//             abort(404);
//         }
    
//         return response()->file($requestedPath, [
//             'Content-Type' => File::mimeType($requestedPath) ?? 'application/octet-stream',
//         ]);
//     })->where('path', '.*')->name('docs.updates.images.show');
// }
