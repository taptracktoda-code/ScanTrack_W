<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
