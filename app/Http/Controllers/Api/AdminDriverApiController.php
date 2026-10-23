<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDriverApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $drivers = Driver::query()
            ->with('organization')
            ->orderBy('name')
            ->get()
            ->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'driver_id' => 'DRIVER-' . $driver->id,
                'name' => $driver->name,
                'vehicle_number' => $driver->vehicle_number,
                'organization_id' => $driver->organization_id,
                'organization' => $driver->organization?->name,
                'toda_name' => $driver->organization?->name,
                'status' => $driver->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Drivers retrieved successfully.',
            'drivers' => $drivers,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully.']);
    }
}
