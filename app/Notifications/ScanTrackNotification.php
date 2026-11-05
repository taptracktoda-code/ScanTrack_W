<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Notifications\Notification;

class ScanTrackNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $type,
        private readonly array $data = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
