<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PublicDriverRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'full_name' => ['required', 'string', 'max:255', 'regex:/^\S+\s+\S+/'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'phone_number' => ['required', 'string', 'max:40'],
            'license_number' => ['required', 'string', 'max:80', 'unique:drivers,license_number'],
            'unit' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $nameParts = preg_split('/\s+/', trim($attributes['full_name']));
        $firstName = array_shift($nameParts);
        $lastName = array_pop($nameParts);
        $middleName = implode(' ', $nameParts) ?: null;
        $fullName = trim($attributes['full_name']);

        $driver = DB::transaction(function () use ($attributes, $firstName, $middleName, $lastName, $fullName): Driver {
            $driver = Driver::create([
                'name' => $fullName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'license_number' => $attributes['license_number'],
                'contact_number' => $attributes['phone_number'],
                'address' => $attributes['address'],
                'status' => 'Pending',
            ]);

            User::create([
                'name' => $fullName,
                'email' => $attributes['email'],
                'password' => Hash::make(Str::random(64)),
                'role' => User::ROLE_DRIVER,
                'user_id' => 'DRIVER-'.strtoupper((string) $driver->id),
                'license_number' => $driver->license_number,
                'contact' => $driver->contact_number,
                'address' => $driver->address,
                'unit' => $attributes['unit'],
                'status' => 'Pending',
                'must_change_password' => true,
            ]);

            return $driver;
        });

        return response()->json([
            'success' => true,
            'message' => 'Driver application submitted for verification.',
            'registration' => [
                'id' => $driver->id,
                'full_name' => $driver->name,
                'email' => $attributes['email'],
                'status' => 'pending_verification',
                'submitted_at' => $driver->created_at,
            ],
        ], 201);
    }
}
