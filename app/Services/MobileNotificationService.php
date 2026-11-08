<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use App\Notifications\ScanTrackNotification;

class MobileNotificationService
{
    public function notifyUser(
        User $user,
        string $title,
        string $message,
        string $type,
        array $data = [],
    ): void {
        $notification = new ScanTrackNotification($title, $message, $type, $data);
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            // Persist atomically; external pushes must wait until the queue transaction commits.
            \Illuminate\Support\Facades\Notification::sendNow($user, $notification, ['database']);
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($user, $notification): void {
                app(\App\Notifications\Channels\ExpoPushChannel::class)->send($user, $notification);
            });
        } else {
            $user->notify($notification);
        }
    }

    public function notifyDriver(
        Driver $driver,
        string $title,
        string $message,
        string $type,
        array $data = [],
    ): void {
        $driverAccount = User::query()
            ->whereRaw('LOWER(role) = ?', [strtolower(User::ROLE_DRIVER)])
            ->where('user_id', 'DRIVER-'.strtoupper((string) $driver->getKey()))
            ->first();

        if ($driverAccount) {
            $this->notifyUser($driverAccount, $title, $message, $type, $data);
        }
    }

    public function notifyOrganizationManagers(
        int $organizationId,
        string $title,
        string $message,
        string $type,
        array $data = [],
    ): void {
        User::query()
            ->whereRaw('LOWER(role) = ?', [strtolower(User::ROLE_MANAGER)])
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->each(fn (User $manager) => $this->notifyUser($manager, $title, $message, $type, $data));
    }
}
