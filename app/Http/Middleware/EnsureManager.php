<?php

namespace App\Http\Middleware;

use App\Models\TodaOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = $request->user();
        abort_unless($manager && $manager->isManager() && strcasecmp((string) $manager->status, 'Active') === 0, 403);
        abort_unless(
            TodaOrganization::query()->whereKey($manager->organization_id)->where('status', 'Active')->exists(),
            403
        );

        return $next($request);
    }
}