<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Monitoring\WebHomeActivityTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackWebHomeMetrics
{
    public function __construct(
        private readonly WebHomeActivityTracker $tracker,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        $this->tracker->track($request);

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! (bool) config('app.custom.security.web_home_metrics.enabled', false)) {
            return false;
        }

        if (! is_platform('web')) {
            return false;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;
    }
}
