<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpoPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class MobileNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 20), 1), 50);
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'ScanTrack',
                'message' => $notification->data['message'] ?? '',
                'type' => $notification->data['type'] ?? 'system',
                'data' => $notification->data['data'] ?? [],
                'is_read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'read_at' => $notification->read_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function registerPushToken(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'expo_push_token' => [
                'required',
                'string',
                'max:255',
                'regex:/^(Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]$/',
            ],
        ]);

        ExpoPushToken::query()->updateOrCreate(
            ['token' => $attributes['expo_push_token']],
            ['user_id' => $request->user()->getKey()],
        );

        return response()->json(['success' => true], 201);
    }

    public function removePushToken(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'expo_push_token' => [
                'required',
                'string',
                'max:255',
                'regex:/^(Expo|Exponent)PushToken\[[A-Za-z0-9_-]+\]$/',
            ],
        ]);

        ExpoPushToken::query()
            ->where('user_id', $request->user()->getKey())
            ->where('token', $attributes['expo_push_token'])
            ->delete();

        return response()->json(['success' => true]);
    }
}
