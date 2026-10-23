<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('organization');
        $organization = $user->organization;
        $avatarUrl = $user->profile_picture
            ? Storage::disk('public')->url($user->profile_picture)
            : null;

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'profile_picture' => $user->profile_picture,
                'avatar' => $avatarUrl,
                'avatar_url' => $avatarUrl,
                'profile_image_url' => $avatarUrl,
                'must_change_password' => (bool) $user->must_change_password,
                'organization_id' => $user->organization_id,
                'organization_name' => $organization?->name,
                'organization' => $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ] : null,
            ],
        ]);
    }
}
