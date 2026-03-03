<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJsErrorReportRequest;
use App\Services\JsErrorReports\JsErrorReportRecorder;
use Illuminate\Http\JsonResponse;

class JsErrorReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreJsErrorReportRequest $request, JsErrorReportRecorder $recorder): JsonResponse
    {
        $report = $recorder->store($request->validated(), $request);

        return response()->json([
            'id' => $report->id,
            'message' => 'تم استلام البلاغ بنجاح.',
        ], 201);
    }
}
