<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PhilippineDay
{
    public static function bounds(?string $date = null): array
    {
        $date ??= CarbonImmutable::now('Asia/Manila')->toDateString();
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (\Throwable) {
            $start = null;
        }
        if (! $start || $start->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => ['Choose a valid date in YYYY-MM-DD format.']]);
        }

        return [$start->utc(), $start->addDay()->utc(), $date];
    }
}
