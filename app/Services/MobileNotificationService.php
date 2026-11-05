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
        $user->notify(new ScanTrackNotification($title, $message, $type, $data));
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
            ->each(fn (User $manager) => $this->notifyUser($manager, $title, $message, $type, $data));
    }
}
