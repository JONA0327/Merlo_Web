<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Despite the class name (kept for the "superadmin" route-middleware
        // alias), this gates the whole admin.* panel — which Administración
        // now sees identically to superadmin. See User::isAdminStaff().
        abort_unless($request->user()?->isAdminStaff(), 403);

        return $next($request);
    }
}
