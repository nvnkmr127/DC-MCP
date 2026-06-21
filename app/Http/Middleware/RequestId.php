<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        // Use Laravel 11's Context to automatically share this with all logs 
        // AND automatically propagate it into all queued jobs spawned during this request!
        \Illuminate\Support\Facades\Context::add('trace_id', $requestId);

        $response = $next($request);

        // Echo it back so clients/load balancers can correlate
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
