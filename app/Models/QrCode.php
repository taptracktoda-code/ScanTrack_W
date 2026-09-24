<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrCode extends Model
{
    protected $table = 'qr_codes';

    protected $fillable = [
        'driver_id',
        'qr_token',
        'status',
        'generated_at',
    ];

    protected $casts = ['generated_at' => 'datetime'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}