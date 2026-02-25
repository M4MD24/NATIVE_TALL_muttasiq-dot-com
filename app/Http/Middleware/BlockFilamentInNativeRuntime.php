<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlockFilamentInNativeRuntime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('nativephp-internal.running')) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
