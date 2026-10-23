<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()
            && $request->user()->hasRole(User::ROLE_SUPER_ADMIN)
            && strcasecmp((string) $request->user()->status, 'Active') === 0,
            403
        );

        return $next($request);
    }
}
