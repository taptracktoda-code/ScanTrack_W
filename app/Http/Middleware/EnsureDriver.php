<?php

namespace App\Http\Middleware;

use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isDriver() && strcasecmp((string) $user->status, 'Active') === 0, 403);

        $driverId = preg_match('/^DRIVER-(\d+)$/i', (string) $user->user_id, $matches) ? (int) $matches[1] : null;
        $driver = $driverId ? Driver::with('organization')->find($driverId) : null;
        abort_unless($driver, 403);
        abort_if($driver->organization && strcasecmp((string) $driver->organization->status, 'Active') !== 0, 403);

        return $next($request);
    }
}