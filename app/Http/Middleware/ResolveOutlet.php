<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOutlet
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $outletId = $request->header('X-Outlet-ID');
        if ($outletId) {
            app()->instance('currentOutletId', $outletId);
        }
        return $next($request);
    }
}
