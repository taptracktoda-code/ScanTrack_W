<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'scantrack:make-super-admin {email} {--name=}';

    protected $description = 'Create the initial Super Admin account using a hidden password prompt.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $validation = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
        ]);
        if ($validation->fails()) {
            $this->error($validation->errors()->first('email'));
            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Super Admin name')));
        if ($name === '') {
            $this->error('A name is required.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (at least 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');
        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Passwords must match and contain at least 12 characters.');
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'SUPER_ADMIN',
            'user_id' => 'ADMIN-' . Str::upper(Str::random(20)),
            'license_number' => '',
            'contact' => '',
            'address' => '',
            'status' => 'Active',
        ]);

        $this->info('Super Admin account created.');

        return self::SUCCESS;
    }
}