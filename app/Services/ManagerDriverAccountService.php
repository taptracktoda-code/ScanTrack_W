<?php

namespace App\Services;

use App\Mail\DriverAccountCreatedMail;
use App\Models\Driver;
use App\Models\QrCode;
use App\Models\TerminalActivity;
use App\Models\TodaOrganization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ManagerDriverAccountService
{
    /**
     * @return array{driver: Driver, email: string, temporary_password: string}
     */
    public function create(array $attributes, User $manager, TodaOrganization $organization): array
    {
        return DB::transaction(function () use ($attributes, $manager, $organization): array {
            $name = trim(implode(' ', array_filter([
                $attributes['first_name'],
                $attributes['middle_name'] ?? null,
                $attributes['last_name'],
            ])));
            $temporaryPassword = Str::password(18, letters: true, numbers: true, symbols: true);
            $driver = Driver::create([
                'name' => $name,
                'first_name' => $attributes['first_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'last_name' => $attributes['last_name'],
                'license_number' => $attributes['license_number'],
                'license_expiration_date' => $attributes['license_expiration_date'],
                'contact_number' => $attributes['contact_number'],
                'address' => $attributes['address'],
                'vehicle_number' => $attributes['plate_number'],
                'organization_id' => $organization->id,
                'registered_by' => $manager->id,
                'status' => 'Active',
            ]);

            Vehicle::create([
                'name' => $name,
                'plate_number' => $attributes['plate_number'],
                'vehicle_type' => $attributes['vehicle_type'],
                'organization_id' => $organization->id,
                'status' => 'Active',
            ]);

            User::create([
                'name' => $name,
                'email' => $attributes['email'],
                'password' => Hash::make($temporaryPassword),
                'role' => User::ROLE_DRIVER,
                'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
                'license_number' => $driver->license_number,
                'contact' => $driver->contact_number,
                'address' => $driver->address,
                'status' => 'Active',
                'must_change_password' => true,
            ]);

            do {
                $qrToken = bin2hex(random_bytes(16));
            } while (QrCode::query()->where('qr_token', $qrToken)->exists());

            QrCode::create([
                'driver_id' => $driver->id,
                'qr_token' => $qrToken,
                'status' => 'Active',
                'generated_at' => now(),
            ]);

            TerminalActivity::create([
                'admin_id' => $manager->id,
                'driver_id' => $driver->id,
                'activity' => 'Driver account created',
                'description' => 'Driver account created and activated by organization manager',
            ]);

            return [
                'driver' => $driver,
                'email' => $attributes['email'],
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    public function sendCredentials(Driver $driver, string $email, string $temporaryPassword): bool
    {
        try {
            Mail::to($email)->send(new DriverAccountCreatedMail($driver, $email, $temporaryPassword));
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
