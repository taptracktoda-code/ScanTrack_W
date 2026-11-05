<?php

namespace App\Notifications\Channels;

use App\Models\ExpoPushToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    private const PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function send(object $notifiable, object $notification): void
    {
        $tokens = ExpoPushToken::query()
            ->where('user_id', $notifiable->getKey())
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $data = $notification->toArray($notifiable);
        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $data['title'],
            'body' => $data['message'],
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
            'data' => [
                'notification_type' => $data['type'],
                ...($data['data'] ?? []),
            ],
        ], $tokens);

        foreach (array_chunk($messages, 100) as $offset => $batch) {
            $tokenBatch = array_slice($tokens, $offset * 100, count($batch));

            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(5)
                    ->post(self::PUSH_URL, $batch);
            } catch (ConnectionException $exception) {
                Log::warning('Unable to connect to the Expo Push Service.', [
                    'user_id' => $notifiable->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
                continue;
            }

            if (! $response->successful()) {
                Log::warning('Expo Push Service rejected a notification batch.', [
                    'user_id' => $notifiable->getKey(),
                    'status' => $response->status(),
                ]);
                continue;
            }

            foreach ($response->json('data', []) as $index => $ticket) {
                if (($ticket['status'] ?? null) === 'ok') {
                    continue;
                }

                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    ExpoPushToken::query()->where('token', $tokenBatch[$index] ?? '')->delete();
                }

                Log::warning('Expo Push Service could not deliver a notification.', [
                    'user_id' => $notifiable->getKey(),
                    'error' => $ticket['message'] ?? 'Unknown Expo push ticket error.',
                ]);
            }
        }
    }
}
