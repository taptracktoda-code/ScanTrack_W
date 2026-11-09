<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DriverLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function scopeRecordedBetween(Builder $query, ?CarbonInterface $start, ?CarbonInterface $end): Builder
    {
        // Older records can have no scan timestamp. Use the same fallback as the attendance table.
        return $query
            ->when($start, fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) >= ?', [$start]))
            ->when($end, fn ($query) => $query->whereRaw('COALESCE(scanned_at, driver_logs.created_at) < ?', [$end]));
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
