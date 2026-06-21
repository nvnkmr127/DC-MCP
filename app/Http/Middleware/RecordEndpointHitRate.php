<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;

class RecordEndpointHitRate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route() ? $request->route()->uri() : $request->path();
        
        Pulse::record('endpoint_hits', $route, 1);
        
        return $next($request);
    }
}
