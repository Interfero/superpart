<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-02.8: correlation ID в ответе API, без stack trace.
 */
class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header('X-Correlation-ID', '');
        $id = $incoming !== '' && strlen($incoming) <= 64
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('correlation_id', $id);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
