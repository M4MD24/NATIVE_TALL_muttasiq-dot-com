<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Thikr;
use Illuminate\Http\JsonResponse;

class AthkarController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'athkar' => Thikr::cachedDefaults(),
        ]);
    }
}
