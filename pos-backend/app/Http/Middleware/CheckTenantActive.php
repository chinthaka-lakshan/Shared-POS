<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Terminals authenticated via Sanctum are returned as $request->user()
        if ($user && method_exists($user, 'tenant') && $user->tenant) {
            if (!$user->tenant->is_active) {
                return response()->json(['message' => 'Shop subscription is inactive. Please contact administrator.'], 403);
            }
        }

        return $next($request);
    }
}
