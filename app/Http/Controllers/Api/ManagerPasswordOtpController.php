<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ManagerPasswordOtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ManagerPasswordOtpController extends Controller
{
    private const OTP_TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function requestCode(Request $request): JsonResponse
    {
        $attributes = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($attributes['email']));
        $manager = $this->activeManager($email);

        if ($manager) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES);
            Cache::put($this->cacheKey($email), [
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt->timestamp,
            ], $expiresAt);

            try {
                Mail::to($manager->email)->send(new ManagerPasswordOtpMail($otp));
            } catch (Throwable $exception) {
                Cache::forget($this->cacheKey($email));
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to send the verification code. Please try again later.',
                ], 503);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'If the email belongs to an active manager account, a verification code will be sent.',
        ]);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ]);
        $email = strtolower(trim($attributes['email']));

        if (! $this->activeManager($email) || ! $this->validOtp($email, $attributes['otp'])) {
            $this->recordFailedAttempt($email);

            return response()->json([
                'success' => false,
                'message' => 'The verification code is invalid or expired.',
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Verification code accepted.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);
        $email = strtolower(trim($attributes['email']));
        $manager = $this->activeManager($email);

        if (! $manager || ! $this->validOtp($email, $attributes['otp'])) {
            $this->recordFailedAttempt($email);

            return response()->json([
                'success' => false,
                'message' => 'The verification code is invalid or expired.',
            ], 422);
        }

        $manager->forceFill([
            'password' => $attributes['password'],
            'must_change_password' => false,
        ])->save();

        Cache::forget($this->cacheKey($email));

        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    private function activeManager(string $email): ?User
    {
        $manager = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (
            ! $manager
            || ! $manager->isManager()
            || strcasecmp((string) $manager->status, 'Active') !== 0
            || ! $manager->organization()->where('status', 'Active')->exists()
        ) {
            return null;
        }

        return $manager;
    }

    private function validOtp(string $email, string $otp): bool
    {
        $record = Cache::get($this->cacheKey($email));

        return is_array($record)
            && ($record['attempts'] ?? self::MAX_ATTEMPTS) < self::MAX_ATTEMPTS
            && ($record['expires_at'] ?? 0) >= now()->timestamp
            && is_string($record['otp_hash'] ?? null)
            && Hash::check($otp, $record['otp_hash'] ?? '');
    }

    private function recordFailedAttempt(string $email): void
    {
        $key = $this->cacheKey($email);
        $record = Cache::get($key);

        if (! is_array($record) || ($record['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($key);

            return;
        }

        $record['attempts'] = ($record['attempts'] ?? 0) + 1;
        if ($record['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return;
        }

        $secondsRemaining = max(1, $record['expires_at'] - now()->timestamp);
        Cache::put($key, $record, now()->addSeconds($secondsRemaining));
    }

    private function cacheKey(string $email): string
    {
        return 'manager-password-otp:'.hash('sha256', $email);
    }
}
