<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => 'Change your temporary password before using the driver application.',
                'must_change_password' => true,
            ], 403);
        }

        return $next($request);
    }
}